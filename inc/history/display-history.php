<?php
function mangareader_display_user_history() {
    global $wpdb;

    $history_table = $wpdb->prefix . 'manga_read_history';
    $manga_table   = $wpdb->prefix . 'manga_list';
    $chapter_table = $wpdb->prefix . 'manga_chapter';

    $user_id = get_current_user_id();
    $guest_token = !$user_id ? mangareader_get_guest_token() : null;

    $condition = $user_id ? "user_id = %d" : "guest_token = %s";
    $param = $user_id ?: $guest_token;

    // ✅ Fix: dùng history_page thay vì page
    $paged = max(1, intval($_GET['history_page'] ?? 1));
    $limit = 20;
    $offset = ($paged - 1) * $limit;

    // Tổng số dòng
    $total_sql = $wpdb->prepare("SELECT COUNT(*) FROM $history_table WHERE $condition", $param);
    $total_rows = $wpdb->get_var($total_sql);

    // Truy vấn chính
    $history = $wpdb->get_results(
        $wpdb->prepare("
            SELECT manga_id, chapter_id, updated_at 
            FROM $history_table 
            WHERE $condition 
            ORDER BY updated_at DESC 
            LIMIT %d OFFSET %d", $param, $limit, $offset)
    );

    if (empty($history)) return '<p>📖 Bạn chưa đọc truyện nào.</p>';

    // Manga & Chapter
    $manga_ids = array_unique(array_map(fn($h) => $h->manga_id, $history));
    $chapter_ids = array_unique(array_map(fn($h) => $h->chapter_id, $history));

    $placeholders = implode(',', array_fill(0, count($manga_ids), '%s'));
    $manga_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT manga_id, title, cover FROM $manga_table WHERE manga_id IN ($placeholders)", ...$manga_ids
    ));
    $mangas = [];

    
    foreach ($manga_rows as $manga) $mangas[$manga->manga_id] = $manga;

    $placeholders = implode(',', array_fill(0, count($chapter_ids), '%s'));
    $chapter_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT chapter_id, chapter_number, title, created_at FROM $chapter_table WHERE chapter_id IN ($placeholders)", ...$chapter_ids
    ));
    $chapters = [];
    foreach ($chapter_rows as $chapter) $chapters[$chapter->chapter_id] = $chapter;

    ob_start();

    // Giao diện
    echo '<style>
    .manga-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    @media (min-width: 768px) { .manga-grid { grid-template-columns: repeat(4, 1fr); } }
    @media (min-width: 1024px) { .manga-grid { grid-template-columns: repeat(5, 1fr); } }

    .manga-card {
        background: #000;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .manga-cover {
        width: 100%; padding-top: 140%;
        background-size: cover; background-position: center;
    }
    .manga-info {
        position: absolute; bottom: 0; background: rgba(0,0,0,0.6);
        width: 100%; padding: 8px; box-sizing: border-box;
    }
    .manga-title {
        font-size: 14px; color: #fff; margin: 0; line-height: 1.3; font-weight: 600;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .manga-chapters {
        background: #111; color: #ccc; font-size: 12px; padding: 8px;
    }
    .chapter-item {
        display: flex; justify-content: space-between;
        padding: 4px 0; border-top: 1px solid rgba(255,255,255,0.1);
    }
    .chapter-item:first-child { border-top: none; }
    .chapter-item a {
        text-decoration: none; color: #ccc;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1;
    }
    .chapter-time {
        font-size: 11px; opacity: 0.7; padding-left: 6px; white-space: nowrap;
    }
    .pagination {
        display: flex; justify-content: center; flex-wrap: wrap;
        gap: 8px; margin: 20px 0;
    }
    .pagination a, .pagination strong {
        padding: 6px 12px; text-decoration: none; border-radius: 4px;
        background: #222; color: #fff; font-size: 14px;
    }
    .pagination a:hover { background: #444; }
    .pagination strong { background: #ff9800; color: #000; }
     .manga-chapters {
        background: #111; padding: 8px; color: #ccc;
        font-size: 12px; line-height: 1.4;
        }
            .manga-chapters .chapter-item a {
        font-size: 12px;
        color: #ccc;
        text-decoration: none;
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: inline-block;
        vertical-align: middle;
        }

    </style>';

    echo '<div class="manga-grid">';

    foreach ($history as $row) {
    $manga = $mangas[$row->manga_id] ?? null;
    $chapter = $chapters[$row->chapter_id] ?? null;

    // Nếu manga chưa có trong DB, gọi API để lấy thông tin
    if (!$manga) {
        $api_url = "https://api.mangadex.org/manga/{$row->manga_id}?includes[]=cover_art";
        $response = wp_remote_get($api_url);

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($data['data'])) {
                $title = $data['data']['attributes']['title']['en'] ?? 'Unknown title';
                $coverFile = '';
                foreach ($data['data']['relationships'] as $rel) {
                    if ($rel['type'] === 'cover_art') {
                        $coverFile = $rel['attributes']['fileName'] ?? '';
                        break;
                    }
                }

                // Gán tạm vào biến $manga giả lập
                $manga = (object)[
                    'title' => $title,
                    'cover' => $coverFile ?: 'default.jpg'
                ];

                // Gán vào mảng $mangas để dùng lại nếu trùng manga_id
                $mangas[$row->manga_id] = $manga;
            }
        }
    }

    $title = $manga->title ?? 'Không rõ';
    $cover = $manga->cover ?? 'default.jpg';
    $chapter_text = $chapter && $chapter->title ? $chapter->title : 'Chapter ' . ($chapter->chapter_number ?? '?');
    $created_at = $chapter->created_at ?? $row->updated_at;
    $time_ago = human_time_diff(strtotime($created_at), current_time('timestamp')) . ' trước';

    $cover_url = (strtolower($cover) !== 'default.jpg')
        ? "https://mangadex-proxy.deon3356.workers.dev/?manga_id={$row->manga_id}&filename=$cover"
        : site_url('/wp-content/uploads/2025/07/default-cover.jpg');

    echo '<div class="manga-card">';
    echo '<a href="' . esc_url("/manga-detail/?id={$row->manga_id}") . '">';
    echo '<div class="manga-cover" style="background-image:url(' . esc_url($cover_url) . ');">';
    echo '<div class="manga-info"><h3 class="manga-title" title="' . esc_attr($title) . '">' . esc_html($title) . '</h3></div>';
    echo '</div></a>';
    echo '<div class="manga-chapters">';
    echo '<div class="chapter-item">';
    echo '<a href="' . esc_url("/reader/?chapter={$row->chapter_id}") . '">' . esc_html($chapter_text) . '</a>';
    echo '<span class="chapter-time">' . esc_html($time_ago) . '</span>';
    echo '</div></div>';
    echo '</div>';
}


    echo '</div>';

    // ✅ Phân trang đã sửa
    $total_pages = ceil($total_rows / $limit);
    if ($total_pages > 1) {
        echo '<div class="pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $paged) {
                echo "<strong>$i</strong>";
            } else {
                $url = esc_url(add_query_arg('history_page', $i, get_permalink()));
                echo "<a href='$url'>$i</a>";
            }
        }
        echo '</div>';
    }

    return ob_get_clean();
}
add_shortcode('manga_read_history', 'mangareader_display_user_history');
