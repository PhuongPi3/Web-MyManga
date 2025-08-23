<?php
function shortcode_mangadex_reader() {
    global $wpdb;
    $output = '';

    // 1️⃣ Get chapter ID from URL
    if (!isset($_GET['chapter'])) return '❌ Missing chapter ID.';
    $chapter_id = sanitize_text_field($_GET['chapter']);

    // Lấy manga_id và tiêu đề chapter, manga từ DB, nếu không có thì lấy từ API
    $manga_id = null;
    $chapter_title = '(Unknown Chapter)';
    $manga_title = '(Unknown Title)';
    $manga_link = '#';

    // Thử lấy từ DB
    $chapter_info = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}manga_chapter WHERE chapter_id = %s",
        $chapter_id
    ));

    if ($chapter_info) {
        $manga_id = $chapter_info->manga_id;
        $chapter_title = $chapter_info->title ?: 'Chapter ' . $chapter_info->chapter_number;

        $manga = $wpdb->get_row($wpdb->prepare(
            "SELECT title, manga_id FROM {$wpdb->prefix}manga_list WHERE manga_id = %s",
            $manga_id
        ));
        if ($manga) {
            $manga_title = esc_html($manga->title);
            $manga_link = "/manga-detail/?id=" . esc_attr($manga_id);
        }
    }

    // Nếu không có DB info, lấy từ API
    if (!$manga_id) {
        $chapter_api = mangadex_api_get("/chapter/$chapter_id");
        if (!empty($chapter_api['data'])) {
            $chapter_data = $chapter_api['data'];
            $attributes = $chapter_data['attributes'] ?? [];
            $chapter_title = $attributes['title'] ?: 'Chapter ' . ($attributes['chapter'] ?? '(N/A)');
            foreach ($chapter_data['relationships'] ?? [] as $rel) {
                if ($rel['type'] === 'manga') {
                    $manga_id = $rel['id'];
                    break;
                }
            }
            if ($manga_id) {
                $manga_api = mangadex_api_get("/manga/$manga_id");
                if (!empty($manga_api['data'])) {
                    $titles = $manga_api['data']['attributes']['title'] ?? [];
                    if (!empty($titles['en'])) {
                        $manga_title = esc_html($titles['en']);
                    } elseif (!empty($titles)) {
                        $first_title = reset($titles);
                        $manga_title = esc_html($first_title);
                    }
                    $manga_link = "/manga-detail/?id=" . esc_attr($manga_id);
                }
            }
        }
    }

    // 👉 Save read history nếu có manga_id
    if (function_exists('mangareader_save_read_history') && $manga_id) {
        mangareader_save_read_history($manga_id, $chapter_id);
            // 👁‍🗨 Ghi view nếu chưa tồn tại trong hôm nay
       // Lưu lịch sử đọc
        mangareader_save_read_history($manga_id, $chapter_id);

        // ➕ Ghi view nếu chưa có trong hôm nay
        $user_id = get_current_user_id();
        $guest_token = !$user_id ? mangareader_get_guest_token() : null;
        $today = date('Y-m-d');

        $already_viewed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}manga_viewer
            WHERE manga_id = %s AND chapter_id = %s AND viewed_at = %s AND " . ($user_id ? "user_id = %d" : "guest_token = %s"),
            $manga_id, $chapter_id, $today, ($user_id ?: $guest_token)
        ));

        if (!$already_viewed) {
            $wpdb->insert("{$wpdb->prefix}manga_viewer", [
                'manga_id'    => $manga_id,
                'chapter_id'  => $chapter_id,
                'user_id'     => $user_id ?: null,
                'guest_token' => $guest_token,
                'viewed_at'   => $today,
            ]);

            // 👁‍🗨 Tăng view → nếu không có trong DB thì thêm mới (tên và ID tối thiểu)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}manga_list WHERE manga_id = %s",
                $manga_id
            ));

            if ($existing) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}manga_list SET views = views + 1 WHERE manga_id = %s",
                    $manga_id
                ));
            } 

        }


    }

    // 2️⃣ Call MangaDex API to get images
    $api = mangadex_api_get("/at-home/server/$chapter_id");

    if (empty($api['chapter'])) {
        return '❌ Chapter not found or server returned empty data.';
    }

    $base_url = $api['baseUrl'] ?? '';
    $hash     = $api['chapter']['hash'] ?? '';
    $pages    = $api['chapter']['data'] ?? [];

    // 3️⃣ No images → try dataSaver
    if (empty($hash)) {
        if (current_user_can('manage_options')) {
            $output .= '<pre>' . print_r($api, true) . '</pre>';
        }
        return $output . '<p>⚠️ Chapter has no images or has been removed.</p>';
    }

    if (empty($pages)) {
        $pages = $api['chapter']['dataSaver'] ?? [];
        if (!empty($pages)) {
            $output .= '<p>⚠️ Using compressed images (dataSaver).</p>';
        }
    }

    if (empty($pages)) {
        return $output . '<p>❌ No images found in this chapter.</p>';
    }

    // 📌 Display header
    $output .= "<div class='chapter-header' style='margin-bottom:20px; text-align:center;'>
        <h2 style='font-size:24px; font-weight:bold; margin-bottom:5px;'>
            <a href='$manga_link' style='color:#4f46e5; text-decoration:none;'>$manga_title</a>
        </h2>
        <div style='font-size:18px; color:#888;'>$chapter_title</div>
    </div>";

    // 4️⃣ Display images
    $output .= '<div class="reader">';
    foreach ($pages as $img) {
        $url = "$base_url/data/$hash/$img";
        $output .= "<img src='$url' alt='Manga Page' style='width:100%;margin-bottom:10px;'>";
    }
    $output .= '</div>';

    // 5️⃣ Navigation
$chapters = [];

if ($manga_id) {
    // Ưu tiên lấy từ DB
    $chapters = $wpdb->get_results($wpdb->prepare(
        "SELECT chapter_id, chapter_number FROM {$wpdb->prefix}manga_chapter WHERE manga_id = %s ORDER BY chapter_number ASC",
        $manga_id
    ));

    // Nếu không có thì lấy từ API
    if (empty($chapters)) {
        $chapter_feed = get_transient("mdx_chapter_list_$manga_id");
        if (!$chapter_feed) {
            $chapter_feed = [];
            $offset = 0;
            do {
                $api = mangadex_api_get("/manga/$manga_id/feed?limit=100&translatedLanguage[]=en&order[chapter]=asc&offset=$offset");
                foreach ($api['data'] ?? [] as $ch) {
                    $chapter_feed[] = [
                        'id' => $ch['id'],
                        'number' => $ch['attributes']['chapter'] ?? '0'
                    ];
                }
                $offset += 100;
            } while (!empty($api['data']) && count($api['data']) === 100);
            set_transient("mdx_chapter_list_$manga_id", $chapter_feed, HOUR_IN_SECONDS * 6);
        }

        $chapters = array_map(function ($ch) {
            return (object)[
                'chapter_id' => $ch['id'],
                'chapter_number' => $ch['number']
            ];
        }, $chapter_feed);
    }

    // Xác định vị trí
    if (!empty($chapters)) {
        $index = array_search($chapter_id, array_column($chapters, 'chapter_id'));

        $output .= '<div class="chapter-nav" style="margin:20px 0;">';
        if ($index > 0) {
            $prev_id = $chapters[$index - 1]->chapter_id;
            $output .= "<a href='?chapter=$prev_id'>⬅️ Previous Chapter</a> ";
        }
        if ($index < count($chapters) - 1) {
            $next_id = $chapters[$index + 1]->chapter_id;
            $output .= "<a href='?chapter=$next_id'>Next Chapter ➡️</a>";
        }
        $output .= '</div>';
    }

    // Back to manga detail
    $output .= "<div style='margin-top:20px; text-align:center;'>
        <a href='/manga-detail/?id=$manga_id' style='display:inline-block;padding:10px 20px;background:#444;color:#fff;border-radius:5px;text-decoration:none;'>Back to Manga Detail</a>
    </div>";
} else {
    $output .= '<p>⚠️ Chapter not found in the database → navigation disabled.</p>';
}


    // ===================== 💬 Bình luận =====================
    ob_start();

    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $user_name = $user_id ? $current_user->display_name : 'Guest';
    $avatar_url = $user_id ? get_avatar_url($user_id) : 'https://ui-avatars.com/api/?name=Guest';

    // 👉 Handle comment post
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manga_comment_nonce']) && wp_verify_nonce($_POST['manga_comment_nonce'], 'submit_manga_comment')) {
        $comment_text = sanitize_textarea_field($_POST['comment_text'] ?? '');

        if (!empty($comment_text)) {
            $wpdb->insert($wpdb->prefix . 'manga_comments', [
                'manga_id'     => $manga_id,
                'chapter_id'   => $chapter_id,
                'user_id'      => $user_id,
                'user_name'    => $user_name,
                'avatar_url'   => $avatar_url,
                'comment_text' => $comment_text,
                'created_at'   => current_time('mysql')
            ]);
            wp_safe_redirect(add_query_arg(null, null));
            exit;
        }
    }

    echo "<button id='toggle-comments' style='margin:30px 0;padding:10px 20px;background:#10b981;color:white;border:none;border-radius:6px;cursor:pointer;'>💬 See Comments</button>";

echo "<div id='chapter-comments' style='margin-top:20px;display:none;'>";
    echo "<h3 style='font-size:20px; font-weight:bold; margin-bottom:15px;'>Comments</h3>";

    // 💬 Comment Form
    if (is_user_logged_in()) {
        echo '<form method="post" style="margin-bottom:30px;">';
        wp_nonce_field('submit_manga_comment', 'manga_comment_nonce');
        echo '<textarea name="comment_text" rows="3" placeholder="Write your comment..." style="width:100%;padding:10px;border-radius:5px;margin-bottom:10px;" required></textarea>';
        echo '<br><button type="submit" style="padding:10px 20px;background:#4f46e5;color:#fff;border:none;border-radius:5px;">Submit Comment</button>';
        echo '</form>';
    } else {
        echo "<p>🔒 <a href='/wp-login.php'>Log in</a> to comment.</p>";
    }

    // 🗃️ Load existing comments
    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}manga_comments WHERE chapter_id = %s ORDER BY created_at DESC",
        $chapter_id
    ));

    // Hiển thị bình luận với nút xóa
    if ($comments) {
        foreach ($comments as $cmt) {
            $time = date('d/m/Y H:i', strtotime($cmt->created_at));
            echo "<div style='border-top:1px solid #333;padding-top:10px;margin-bottom:10px;'>";
            echo "<div style='display:flex;align-items:center;gap:10px;margin-bottom:5px;'>";
            echo "<img src='" . esc_url($cmt->avatar_url) . "' style='width:40px;height:40px;border-radius:50%;object-fit:cover;'>";
            $user_display = $wpdb->get_var($wpdb->prepare(
            "SELECT display_name FROM {$wpdb->prefix}users WHERE ID = %d",
            $cmt->user_id
        ));
        $user_nicename = $wpdb->get_var($wpdb->prepare(
            "SELECT user_nicename FROM {$wpdb->prefix}users WHERE ID = %d",
            $cmt->user_id
        ));
        $username = $user_display ?: $user_nicename;

        echo "<strong>" . esc_html($username) . "</strong>";

            echo "<span style='margin-left:auto;font-size:12px;color:#aaa;'>$time</span>";

            // Nút xóa: chỉ cho admin hoặc chủ comment mới được xóa
            if (current_user_can('manage_options') || ($user_id && $user_id == $cmt->user_id)) {
                $delete_url = wp_nonce_url(
                    admin_url('admin-post.php?action=delete_manga_comment&comment_id=' . intval($cmt->id)),
                    'delete_manga_comment_' . intval($cmt->id)
                );
                echo " <a href='$delete_url' style='color:red; margin-left:10px;' onclick='return confirm(\"BAre you sure to delete this comment?\")'>[	Delete]</a>";
            }

            echo "</div>";
            echo "<div style='margin-left:50px;'>" . esc_html($cmt->comment_text) . "</div>";
            echo "</div>";
        }
    } else {
        echo "<p style='color:#aaa;'>	No comments yet.</p>";
    }
    echo <<<EOD
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggleBtn = document.getElementById("toggle-comments");
        const commentBox = document.getElementById("chapter-comments");
        if (toggleBtn && commentBox) {
            toggleBtn.addEventListener("click", function() {
                if (commentBox.style.display === "none") {
                    commentBox.style.display = "block";
                    toggleBtn.textContent = "🔽 Hide Comments";
                } else {
                    commentBox.style.display = "none";
                    toggleBtn.textContent = "💬 See Comments";
                }
            });
        }
    });
    </script>
    EOD;

    echo "</div>";


    $output .= ob_get_clean();

    return $output;
}
add_shortcode('mangadex_reader', 'shortcode_mangadex_reader');


// Optional: Auto-run on "reader" page
add_filter('the_content', function($content) {
    if (is_page('reader') && isset($_GET['chapter'])) {
        return shortcode_mangadex_reader();
    }
    return $content;
});
