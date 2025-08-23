<?php

function shortcode_mangadex_detail() {
    if (!isset($_GET['id'])) return 'Missing ID.';
    $id = sanitize_text_field($_GET['id']);
    $data = mangadex_api_get("/manga/$id");
    if (empty($data['data'])) return 'Manga not found.';

    $manga = $data['data'];
    $title = $manga['attributes']['title']['en'] ?? 'No title';
    $desc = $manga['attributes']['description']['en'] ?? 'No description';

    // Cover
    $cover_url = site_url("/wp-content/uploads/2025/07/default-cover.jpg");
    foreach ($manga['relationships'] as $rel) {
        if ($rel['type'] === 'cover_art') {
            $coverId = $rel['id'];
            $coverData = mangadex_api_get("/cover/$coverId");
            $filename = $coverData['data']['attributes']['fileName'] ?? '';
            if ($filename) {
                $cover_url = "https://mangadex-proxy.deon3356.workers.dev/?manga_id=$id&filename=$filename";
            }
            break;
        }
    }
if (!function_exists('map_mangadex_tag_id_to_local_id')) {
    function map_mangadex_tag_id_to_local_id($uuid, $group) {
        global $wpdb;

        $map_table = match ($group) {
            'genre' => 'wpoz_manga_genre',
            'theme' => 'wpoz_manga_theme',
            'format' => 'wpoz_manga_format',
            default => null,
        };

        if (!$map_table) return null;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$map_table} WHERE uuid = %s",
            $uuid
        ));
    }
}



if (!function_exists('get_tag_type_prefix')) {
    function get_tag_type_prefix($group) {
        return match ($group) {
            'genre' => 'genre_',
            'theme' => 'theme_',
            'format' => 'format_',
            default => '',
        };
    }
}


    // Tags
    $tags = $manga['attributes']['tags'] ?? [];
   $genres = [];
foreach ($tags as $tag) {
    $name = $tag['attributes']['name']['en'] ?? '';
    $group = $tag['attributes']['group'] ?? '';
    $uuid = $tag['id'];
    $prefix = get_tag_type_prefix($group);
    $local_id = map_mangadex_tag_id_to_local_id($uuid, $group);

    if ($local_id) {
        $full_tag = $prefix . $local_id;
        $genres[] = "<a href='/genre-list/?keyword=&status=&tag=$full_tag&sort=latest' style='color:#0ea5e9;'>$name</a>";
    }
}


    // Chapters (để lấy link)
    $chapters = mangadex_api_get("/chapter?limit=2&manga=$id&order[chapter]=asc")['data'] ?? [];
    $first_chap = $chapters[0]['id'] ?? '';
    $last_chap = end($chapters)['id'] ?? '';

    // Lịch sử đọc
    if (is_user_logged_in()) {
        global $wpdb;
        $uid = get_current_user_id();
        $user_continue_chap = $wpdb->get_var($wpdb->prepare(
            "SELECT chapter_id FROM {$wpdb->prefix}manga_read_history WHERE user_id = %d AND manga_id = %s ORDER BY updated_at DESC LIMIT 1",
            $uid, $id
        ));
    } else {
        $user_continue_chap = ''; // fallback cho khách
    }

    // Nếu chưa từng đọc -> gán về chapter đầu tiên
    if (empty($user_continue_chap)) {
        $user_continue_chap = $first_chap;
    }


    // Giao diện
    $output = "<div class='manga-detail-wrapper'>";

    // Detail đầu trang (100%)
    $output .= "<div class='detail-header flex-row'>
        <div class='cover' style='background-image:url($cover_url)'></div>
        <div class='detail-info'>
            <h1 class='title'>$title</h1>
            <p class='description'>$desc</p>
            <div class='genres'>" . implode(', ', $genres) . "</div>
            <div class='buttons'>";

            //rating

    $output .= do_shortcode('[manga_rating id="' . esc_attr($id) . '"]');


    // Nút
    $output .= "<a href='/reader/?chapter=$first_chap' class='btn'>Read Now</a>";
    $output .= "<a href='/reader/?chapter=$last_chap' class='btn'>Latest Chapter</a>";
    $output .= "<a href='/reader/?chapter=$user_continue_chap' class='btn'>Continue Reading</a>";

    $output .= "</div></div></div>";

    // Dưới chia 2 cột desktop (chapter+comment 66%, recommend 33%)
    $output .= "<div class='content-lower flex-row'>
        <div class='left-col'>
            " . shortcode_manga_chapter_list($id) . shortcode_manga_comments($id) . "
        </div>
        <div class='right-col'>" . shortcode_manga_recommend($id, $tags) . "</div>
    </div>";

    $output .= "</div>"; // manga-detail-wrapper end

    return $output;
}

function shortcode_manga_chapter_list($id) {
     $page = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
    $sort = ($_GET['sort'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
    $sort_icon = $sort === 'asc' ? '⬆⬇' : '⬇⬆';
    $toggle_sort = $sort === 'asc' ? 'desc' : 'asc';
    $limit = 20;

    // Lấy tổng trước để tính offset
    $res_total = mangadex_api_get("/chapter?limit=1&manga=$id");
    $total = $res_total['total'] ?? 0;
    $total_pages = ceil($total / $limit);

    $offset = ($sort === 'asc') ? max(0, $total - $page * $limit) : ($page - 1) * $limit;

    $res = mangadex_api_get("/chapter?limit=$limit&offset=$offset&manga=$id&order[chapter]=$sort");
    $chapters = $res['data'] ?? [];
    $output = '
    <style>
    .chapter-list ul {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #ccc;
        border-radius: 8px;
        padding: 10px;
        margin-top: 10px;
    }
    .chapter-list li {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    .chapter-list li:last-child {
        border-bottom: none;
    }
    .chapter-list .date {
        color: #888;
        font-size: 0.9em;
    }
    .chapter-list h2 {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .chapter-list .pagination {
        margin-top: 15px;
        text-align: center;
    }
    .chapter-list .pagination a,
    .chapter-list .pagination span {
        display: inline-block;
        margin: 0 5px;
        padding: 6px 10px;
        border-radius: 4px;
        text-decoration: none;
        border: 1px solid #ccc;
        color: #fff;
    }
    .chapter-list .pagination a.active {
        background-color: #0ea5e9;
        color: #fff;
        font-weight: bold;
    }
    .comment-form textarea:disabled {
    background-color: #1f2937;
    color: #9ca3af;
    resize: none;
}
    </style>';

    $output .= "<div class='chapter-list'>
        <h2>
            Chapter List 
            <a href='" . esc_url(add_query_arg(['id' => $id, 'pg' => $page, 'sort' => $toggle_sort])) . "' style='text-decoration:none; color:inherit;'>
                <span style='font-size:1.2em;'>$sort_icon</span>
            </a>
        </h2>
        <ul>";

    foreach ($chapters as $chap) {
        $chap_id = $chap['id'];
        $chap_title = $chap['attributes']['title'] ?? '';
        $chap_num = $chap['attributes']['chapter'] ?? '';
        $display_title = trim("Chapter $chap_num" . ($chap_title ? " - $chap_title" : ''));
        $date = date('d/m/Y', strtotime($chap['attributes']['publishAt']));
        $output .= "<li><a href='/reader/?chapter=$chap_id'>$display_title</a> <span class='date'>$date</span></li>";
    }

    $output .= "</ul>";

    // Pagination
    if ($total_pages > 1) {
        $output .= "<div class='pagination'>";
        $dot_before = false;
        $dot_after = false;

        $base_url = remove_query_arg(['pg'], get_permalink());
        $current_params = $_GET;
        unset($current_params['pg']);

        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)) {
                $params = array_merge($current_params, ['pg' => $i]);
                $url = esc_url(add_query_arg($params, $base_url));
                $active = ($i == $page) ? 'active' : '';
                $output .= "<a href='$url' class='$active'>$i</a>";
            } elseif ($i < $page - 1 && !$dot_before) {
                $output .= "<span>...</span>";
                $dot_before = true;
            } elseif ($i > $page + 1 && !$dot_after) {
                $output .= "<span>...</span>";
                $dot_after = true;
            }
        }

        $output .= "</div>";
    }

    $output .= "</div>"; // chapter-list end
    return $output;
}




//recomend
function shortcode_manga_recommend($id, $tags) {
    if (empty($tags)) return '';
    $tag_ids = array_map(fn($t) => $t['id'], $tags);
    shuffle($tag_ids);
    $related = mangadex_api_get("/manga?limit=10&includedTags[]={$tag_ids[0]}&order[latestUploadedChapter]=desc");

    $output = "<div class='related'><h2>Recommended Manga</h2><div class='related-list'>";

    $related_manga = array_filter($related['data'] ?? [], function ($manga_item) use ($id) {
        return $manga_item['id'] !== $id;
    });
    foreach ($related['data'] as $manga) {
        $rel_id = $manga['id'];
        $rel_title = $manga['attributes']['title']['en'] ?? 'No title';
        if (mb_strlen($rel_title) > 50) {
            $rel_title = mb_substr($rel_title, 0, 47) . '...';
        }
        $latest_chap = $manga['attributes']['latestUploadedChapter'] ?? '';

      $rel_tags = [];
    foreach ($manga['attributes']['tags'] as $tag) {
        $name = $tag['attributes']['name']['en'] ?? '';
        $group = $tag['attributes']['group'] ?? '';
        $uuid = $tag['id'];
        $prefix = get_tag_type_prefix($group);
        $local_id = map_mangadex_tag_id_to_local_id($uuid, $group);

        if ($local_id) {
            $full_tag = $prefix . $local_id;
            $rel_tags[] = "<a href='/genre-list/?keyword=&status=&tag=$full_tag&sort=latest' style='color:#0ea5e9;'>$name</a>";
        }
    }
    shuffle($rel_tags);
    $limited_tags = array_slice($rel_tags, 0, 3);


        $cover_url = site_url('/wp-content/uploads/2025/07/default-cover.jpg');
        foreach ($manga['relationships'] as $rel) {
            if ($rel['type'] === 'cover_art') {
                $cover = mangadex_api_get("/cover/{$rel['id']}");
                $file = $cover['data']['attributes']['fileName'] ?? '';
                if ($file) $cover_url = "https://mangadex-proxy.deon3356.workers.dev/?manga_id=$rel_id&filename=$file";
                break;
            }
        }

       $output .= "<div class='related-item'>
        <a href='/manga-detail/?id=$rel_id' class='related-cover' style='background-image:url($cover_url)'></a>
        <div class='related-info'>
            <a href='/manga-detail/?id=$rel_id' class='related-title' title='" . esc_attr($rel_title) . "'>$rel_title</a>
            <div class='related-tags'>" . implode(', ', $limited_tags) . "</div>
        </div>
    </div>";

    }
    $output .= "</div></div>";
    return $output;
}

function render_comment_thread(&$comments, $parent_id = null, $level = 0, &$rendered_ids = []) {
    if ($level >= 3) return '';

    $html = '';

    foreach ($comments as $cmt) {
        if ($cmt->parent_id != $parent_id || isset($rendered_ids[$cmt->id])) continue;

        $rendered_ids[$cmt->id] = true;

        $is_deleted = empty(trim($cmt->comment_text));
        $has_visible_child = has_visible_children($comments, $cmt->id);

        // Nếu là comment gốc (parent_id = 0) và toàn bộ thread đều đã xóa → bỏ qua hoàn toàn
        if ($cmt->parent_id == 0 && is_fully_deleted_thread($comments, $cmt->id)) {
            continue;
        }

        // Nếu comment bị xóa và không có con → bỏ qua
        if ($is_deleted && !$has_visible_child) continue;

        $html .= "<div class='comment-item' style='margin-left: " . ($level * 20) . "px;'>";

        if ($is_deleted) {
            $html .= "<div class='comment-content' style='font-style: italic; color: #999;'>[This comment has been deleted]</div>";
        } else {
            $username = esc_html($cmt->user_name);
            $chapter_str = $cmt->chapter_id ? get_chapter_title_by_uuid($cmt->chapter_id) : 'General';
            $time = date('d/m/Y H:i', strtotime($cmt->created_at));

            $del_link = ($cmt->user_id == get_current_user_id() || current_user_can('manage_options')) ? "
                <form method='post' onsubmit='return confirm(\"Are you sure you want to delete this comment?\");' style='display:inline;'>
                    <input type='hidden' name='delete_comment_id' value='{$cmt->id}'>
                    <button type='submit' class='delete-link' style='color:red; background:none; border:none; cursor:pointer; font-size:0.8em;'>Delete</button>
                </form>
            " : "";

            $html .= "
                <img src='{$cmt->avatar_url}' alt='avatar'>
                <div class='comment-content'>
                    <div class='comment-header'>
                        <strong>{$username}</strong> <span class='chapter-tag'>{$chapter_str}</span> {$del_link}
                    </div>
                    <div class='comment-text'>" . esc_html($cmt->comment_text) . "</div>
                    <div class='comment-footer'>{$time}</div>";

            if ($level < 2) {
                $html .= "<a href='#' class='reply-link' data-comment-id='{$cmt->id}'>Reply</a>
                          <div class='reply-box' id='reply-box-{$cmt->id}'></div>";
            }

            $html .= "</div>"; // end .comment-content
        }

        $html .= "</div>"; // end .comment-item

        // render các comment con
        $html .= render_comment_thread($comments, $cmt->id, $level + 1, $rendered_ids);
    }

    return $html;
}

// ✅ Kiểm tra xem một comment gốc và toàn bộ thread của nó đã bị xóa chưa
function is_fully_deleted_thread($comments, $parent_id) {
    foreach ($comments as $cmt) {
        if ($cmt->id == $parent_id || $cmt->parent_id == $parent_id) {
            if (!empty(trim($cmt->comment_text))) {
                return false;
            }
            if (!is_fully_deleted_thread($comments, $cmt->id)) {
                return false;
            }
        }
    }
    return true;
}

// ✅ Kiểm tra xem có comment con nào còn hiển thị không
function has_visible_children($comments, $parent_id) {
    foreach ($comments as $cmt) {
        if ($cmt->parent_id == $parent_id) {
            $is_deleted = empty(trim($cmt->comment_text));
            if (!$is_deleted || has_visible_children($comments, $cmt->id)) {
                return true;
            }
        }
    }
    return false;
}








//comment
function shortcode_manga_comments($id) {
    global $wpdb, $current_user;
     if (!session_id()) {
            session_start();
        }
    wp_get_current_user();
    

    $chapter = $_GET['chapter'] ?? '';
    $output = "<div class='manga-comments'>";
    $output .= "<h2 class='comment-section-title'>Comments</h2>";
    $output .= "<div id='chapter-comments'>"; // Không còn display:none



    if ($chapter) {
        $chapter_title = get_chapter_title_by_uuid($chapter);
        $output .= "<h3 class='chapter-comment-title'>$chapter_title</h3>";
    }

    // Delete comment if owner
    if (is_user_logged_in() && isset($_POST['delete_comment_id'])) {
    $cid = intval($_POST['delete_comment_id']);
        $owner = $wpdb->get_var("SELECT user_id FROM {$wpdb->prefix}manga_comments WHERE id = $cid");
        if ($owner == get_current_user_id() || current_user_can('manage_options')) {
            // Kiểm tra có comment con không
            $has_children = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}manga_comments WHERE parent_id = $cid");

            if ($has_children > 0) {
                // Chỉ set comment_text rỗng
                $wpdb->update(
                    "{$wpdb->prefix}manga_comments",
                    ['comment_text' => ''],
                    ['id' => $cid]
                );
            } else {
                // Xóa hẳn
                $wpdb->delete("{$wpdb->prefix}manga_comments", ['id' => $cid]);
            }
        }

        // Redirect để tránh xóa lặp
         $redirect_url = add_query_arg('deleted', 1, remove_query_arg('delete_comment', $_SERVER['REQUEST_URI']));
    wp_redirect($redirect_url);
    exit;

    }

    // Form submit
    if (is_user_logged_in()) {
        // Khởi tạo session nếu chưa có
       if (isset($_POST['submit_comment']) && !empty($_POST['user_comment'])) {
        $comment_text = sanitize_text_field($_POST['user_comment']);

        // Nếu bị toxic thì từ chối
        if (check_comment_toxicity($comment_text) || filter_obscenities($comment_text)) {
            $redirect_url = add_query_arg('comment_warning', 1, $_SERVER['REQUEST_URI']);
            wp_redirect($redirect_url);
            exit;
        } else {
            // Nhận đúng parent_id từ form (0 nếu không có)
            $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;


            $wpdb->insert(
                $wpdb->prefix . 'manga_comments',
                [
                    'manga_id'    => $id,
                    'chapter_id'  => $chapter,
                    'user_id'     => get_current_user_id(),
                    'user_name'   => $current_user->display_name ?: $current_user->user_nicename,
                    'avatar_url'  => get_avatar_url($current_user->ID),
                    'comment_text'=> $comment_text,
                    'created_at'  => current_time('mysql'),
                    'parent_id'   => $parent_id,
                ]
            );

            $redirect_url = remove_query_arg('delete_comment', $_SERVER['REQUEST_URI']);
            wp_redirect($redirect_url);
            exit;
        }
    }


        // Kiểm tra và hiển thị thông báo nếu có
        if (isset($_GET['comment_warning'])) {
          $output .= "<p class='warning-message'>Your comment contains inappropriate language and was not posted.</p>";
        }
        if (isset($_GET['deleted'])) {
            $output .= "<p class='warning-message' style='color: green;'>Comment deleted successfully.</p>";
        }

        // Comment form
        $parent_id = isset($_GET['reply_to']) ? intval($_GET['reply_to']) : null;
        $output .= "<form method='post' class='comment-form'>
            <textarea name='user_comment' placeholder='Write a comment...' required></textarea>
            <input type='hidden' name='parent_id' value='" . esc_attr($parent_id) . "'>
            <input type='submit' name='submit_comment' value='Submit Comment'>
        </form>";

    } else {
        // Guest view
        $output .= "<div class='comment-form disabled'>
            <textarea placeholder='Please log in to comment...' disabled></textarea>
            <p class='login-prompt'>You must <a href='/wp-login.php'>log in</a> to comment.</p>
        </div>";
    }

    // Lazy load: only display the first 5 comments initially
    $comments_per_page = 5;
    $page = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
    $offset = ($page - 1) * $comments_per_page;

    // Get total comments
    $total_comments = $wpdb->get_var($wpdb->prepare(
         "SELECT COUNT(*) FROM {$wpdb->prefix}manga_comments WHERE manga_id = %s AND parent_id = 0",
        $id
    ));
    $total_pages = ceil($total_comments / $comments_per_page);

    // Get comments for the current page
    if ($chapter) {
    $all_comments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}manga_comments WHERE manga_id = %s AND (chapter_id = %s OR chapter_id IS NULL) ORDER BY created_at ASC",
        $id,
        $chapter
    ));
} else {
    $all_comments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}manga_comments WHERE manga_id = %s ORDER BY created_at ASC",
        $id
    ));
}



    $comments = [];
    $unique_check = [];

    foreach ($all_comments as $cmt) {
        // Tạo key kiểm tra trùng: user_id + chapter_id + nội dung (bỏ khoảng trắng)
        $key = $cmt->user_id . '|' . $cmt->chapter_id . '|' . trim(preg_replace('/\s+/', ' ', $cmt->comment_text));
        if (!isset($unique_check[$key])) {
            $unique_check[$key] = true;
            $comments[] = $cmt;
        }
    }

    // Display comments
   $output .= render_comment_thread($comments, 0);



    // Display "Load More" button
    if ($total_pages > $page) {
        $output .= "<button class='load-more-comments' data-page='" . ($page + 1) . "'>Load More Comments</button>";
    }

    $output .= "</div>";

    // Add styling
    $output .= "
     <style>
     .warning-message { color: red; font-weight: bold; margin-top: 10px; };

        .manga-comments { margin-top: 20px; }
        .comment-form textarea {
            width: 100%;
            min-height: 100px;
            border: 1px solid #ccc;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            resize: vertical;
        }
        .comment-form input[type=submit] {
            background-color: #0ea5e9;
            border: none;
            padding: 8px 16px;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }
        .comment-form.disabled textarea {
            background: #f9f9f9;
            color: #aaa;
            cursor: not-allowed;
        }
        .login-prompt {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .comment-item {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .comment-item img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        .comment-item .comment-date {
            color: #999;
            font-size: 0.8em;
        }
        .delete-link {
            color: red;
            font-size: 0.8em;
            margin-left: 10px;
        }
        .comment-section-title {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #0ea5e9;
            font-weight: bold;
            border-bottom: 2px solid #0ea5e9;
            padding-bottom: 5px;
        }

        .chapter-comment-title {
            font-size: 1.2em;
            color: #555;
            margin-bottom: 15px;
        }
        .comment-item {
            color: #ddd;
        }

        .comment-item strong {
            color: #60a5fa;
        }

        .comment-item .chapter-tag {
            color: #9ca3af;
            font-style: italic;
        }

        .comment-item .comment-text {
            color: #000;
        }

        .comment-item .comment-footer {
            color: #a1a1aa;
            font-size: 0.85em;
        }
        .reply-form textarea {
        width: 100%;
        min-height: 80px;
        border: 1px solid #ccc;
        border-radius: 6px;
        padding: 10px;
        margin: 10px 0;
        resize: vertical;
    }
    .reply-form input[type=submit] {
        background-color: #10b981;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
    }

   </style>";


   $output .= <<<EOD
   
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

    // --- Reply form ---
    let currentReplyBox = null;

    document.querySelectorAll(".reply-link").forEach(function(link) {
        link.addEventListener("click", function(e) {
            e.preventDefault();
            const commentId = this.dataset.commentId;
            const replyBox = document.getElementById("reply-box-" + commentId);

            // Remove previous form if open
            if (currentReplyBox && currentReplyBox !== replyBox) {
                currentReplyBox.innerHTML = "";
            }

            // Toggle off if same box
            if (replyBox.innerHTML !== "") {
                replyBox.innerHTML = "";
                currentReplyBox = null;
                return;
            }

            // ----- Tạo form bằng DOM (tránh lỗi innerHTML value) -----
            const form = document.createElement("form");
            form.method = "post";
            form.className = "reply-form";

            // Avatar + textarea block
           form.innerHTML = `
                <div style="display:flex; gap:10px; align-items:flex-start;">
                   
                    <textarea name="user_comment" placeholder="Write a reply..." required></textarea>
                </div>
                <input type="submit" name="submit_comment" value="Submit Reply">
                `;

            // Hidden input for parent_id
            const hiddenInput = document.createElement("input");
            hiddenInput.type = "hidden";
            hiddenInput.name = "parent_id";
            hiddenInput.value = commentId;
            form.appendChild(hiddenInput);

            // Append to reply box
            replyBox.innerHTML = "";
            replyBox.appendChild(form);
            currentReplyBox = replyBox;

            // Auto scroll
            replyBox.scrollIntoView({ behavior: "smooth", block: "center" });

            // Esc to close
            document.addEventListener("keydown", function escListener(evt) {
                if (evt.key === "Escape") {
                    replyBox.innerHTML = "";
                    currentReplyBox = null;
                    document.removeEventListener("keydown", escListener);
                }
            });
        });
    });
  

});

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll(".delete-link").forEach(function(link) {
            link.addEventListener("click", function(e) {
                if (!confirm("Are you sure you want to delete this comment?")) {
                    e.preventDefault();
                }
            });
        });
    });
    </script>
EOD;



    $output .= "</div>"; // Đóng #chapter-comments

    return $output;
}


//chan cmt
function check_comment_toxicity($comment_text) {
    $api_key = 'XXXXXXX';  // Thay YOUR_GOOGLE_API_KEY bằng API key của bạn

    // Tạo request body cho Perspective API
    $data = [
        'comment' => [
            'text' => $comment_text
        ],
        'languages' => ['en', 'vi'], // Kiểm tra cả tiếng Anh và Tiếng Việt
    ];

    $url = 'https://commentanalyzer.googleapis.com/v1alpha1/comments:analyze?key=' . $api_key;

    // Gửi request HTTP POST tới Perspective API
    $response = wp_remote_post($url, [
        'body'    => json_encode($data),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    // Kiểm tra kết quả và trả về mức độ độc hại của bình luận
    if (is_wp_error($response)) {
        return false;  // Nếu có lỗi, giả sử là không độc hại
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    // Kiểm tra mức độ toxicity
    $toxicity_score = $result['attributeScores']['TOXICITY']['summaryScore']['value'];
    
    return $toxicity_score > 0.7; // Nếu toxicity > 0.7 thì coi là bình luận xấu
}
// Hàm lọc các từ ngữ tục tĩu và viết tắt
function filter_obscenities($text) {
    $bad_words = [
        'l_ồ_n', 'l.o.n', 'đ**', 'f.u.c.k', 'stfu', 'bitch', 'cc', 'dm', 'shit', 'fuck', 'cc' ,'lon', 'clm','đmm','đm','dm','qq','sh!t','fu'
    ];

    foreach ($bad_words as $bad_word) {
        if (preg_match('/' . str_replace('/', '\/', $bad_word) . '/i', $text)) {
            return true; // Phát hiện từ ngữ tục tĩu
        }
    }
    return false; // Không có từ ngữ tục tĩu
}



add_shortcode('mangadex_detail', 'shortcode_mangadex_detail');
add_filter('the_content', function($content) {
    if (is_page('manga-detail') && isset($_GET['id'])) {
        return shortcode_mangadex_detail();
    }
    return $content;
});


function get_chapter_title_by_uuid($uuid) {
    global $wpdb;

    // 1. Lấy từ DB nếu có
    $chapter = $wpdb->get_row($wpdb->prepare(
        "SELECT chapter_number, title FROM {$wpdb->prefix}manga_chapter WHERE chapter_id = %s LIMIT 1",
        $uuid
    ));

    if ($chapter) {
        $number = $chapter->chapter_number;
        $title = $chapter->title;
    } else {
        // 2. Gọi API nếu không có
        $api_data = mangadex_api_get("/chapter/$uuid");
        if (!isset($api_data['data'])) return 'Unknown Chapter';

        $attr = $api_data['data']['attributes'];
        $number = $attr['chapter'] ?? null;
        $title = $attr['title'] ?? '';
        $manga_id = '';
        foreach ($api_data['data']['relationships'] as $rel) {
            if ($rel['type'] === 'manga') {
                $manga_id = $rel['id'];
                break;
            }
        }

        // 3. Lưu vào DB nếu có thông tin
        if ($manga_id && $number !== null) {
            $wpdb->insert($wpdb->prefix . 'manga_chapter', [
                'manga_id' => $manga_id,
                'chapter_id' => $uuid,
                'chapter_number' => $number,
                'created_at' => current_time('mysql'),
                'title' => $title,
            ]);
        }
    }

    // 4. Kết hợp hiển thị
    $display = '';
    if ($number !== null && $number !== '') {
        $display .= "Chapter $number";
    }
    if ($title) {
        $display .= " - $title";
    }

    return $display ?: 'Unknown Chapter';
}



