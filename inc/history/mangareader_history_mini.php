<?php
function manga_read_history_mini_shortcode() {
    global $wpdb;

    $history_table = $wpdb->prefix . 'manga_read_history';
    $manga_table   = $wpdb->prefix . 'manga_list';

    $user_id = get_current_user_id();
    $guest_token = !$user_id ? mangareader_get_guest_token() : null;

    $condition = $user_id ? "user_id = %d" : "guest_token = %s";
    $param = $user_id ?: $guest_token;

    $history = $wpdb->get_results(
        $wpdb->prepare("
            SELECT DISTINCT manga_id, MAX(updated_at) as updated_at 
            FROM $history_table 
            WHERE $condition 
            GROUP BY manga_id 
            ORDER BY updated_at DESC 
            LIMIT 4", $param)
    );

    if (empty($history)) return '';

    $manga_ids = array_map(fn($row) => $row->manga_id, $history);
    $placeholders = implode(',', array_fill(0, count($manga_ids), '%s'));

    $manga_data = $wpdb->get_results($wpdb->prepare(
        "SELECT manga_id, title, cover FROM $manga_table WHERE manga_id IN ($placeholders)", ...$manga_ids
    ));

    $mangas = [];
    foreach ($manga_data as $m) $mangas[$m->manga_id] = $m;

    ob_start();

    // CSS
    echo '<style>
    .manga-history-wrapper { margin-top: 48px; margin-bottom: 24px; }
    .manga-history-header {
        font-size: 24px; font-weight: 700; margin-bottom: 16px;
        display: flex; align-items: center; justify-content: space-between;
    }
    .manga-history-header a.see-all {
        font-size: 14px; text-decoration: none; color: #00bfff; transition: color 0.2s;
    }
    .manga-history-header a.see-all:hover { color: #1e90ff; }
    .manga-history-grid {
        display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
    }
    @media (min-width: 768px) {
        .manga-history-grid { grid-template-columns: repeat(4, 1fr); }
    }
    .manga-history-grid a {
        display: block; border-radius: 8px; overflow: hidden;
        background-color: #1a1a1a; box-shadow: 0 0 4px rgba(0,0,0,0.2);
    }
    .manga-history-grid img {
        width: 100%; height: auto; display: block;
        object-fit: cover; border-radius: 8px;
        transition: transform 0.2s ease-in-out;
    }
    .manga-history-grid img:hover { transform: scale(1.02); }
    </style>';

    // HTML
    echo '<div class="manga-history-wrapper">';
    echo '<div class="manga-history-header">';
    echo '<span>Reading History</span>';
    echo '<a class="see-all" href="' . esc_url(site_url('/history/')) . '">See all</a>';
    echo '</div>';
    echo '<div class="manga-history-grid">';

    foreach ($history as $item) {
        $manga = $mangas[$item->manga_id] ?? null;

        // Nếu không có trong DB → gọi API
        if (!$manga) {
            $api_url = "https://api.mangadex.org/manga/{$item->manga_id}?includes[]=cover_art";
            $response = wp_remote_get($api_url);

            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['data'])) {
                    $title = $data['data']['attributes']['title']['en'] ?? 'Unknown Title';
                    $coverFile = '';
                    foreach ($data['data']['relationships'] as $rel) {
                        if ($rel['type'] === 'cover_art') {
                            // Nếu không có fileName → gọi thêm API
                            if (!empty($rel['attributes']['fileName'])) {
                                $coverFile = $rel['attributes']['fileName'];
                            } else {
                                $coverId = $rel['id'];
                                $coverResponse = wp_remote_get("https://api.mangadex.org/cover/{$coverId}");
                                if (!is_wp_error($coverResponse)) {
                                    $coverData = json_decode(wp_remote_retrieve_body($coverResponse), true);
                                    $coverFile = $coverData['data']['attributes']['fileName'] ?? '';
                                }
                            }
                            break;
                        }
                    }

                    $manga = (object)[
                        'manga_id' => $item->manga_id,
                        'title'    => $title,
                        'cover'    => $coverFile ?: null
                    ];
                    $mangas[$item->manga_id] = $manga;
                }
            }
        }

        if (!$manga) continue;

        $cover = (is_string($manga->cover) && strtolower($manga->cover) !== 'default.jpg')
            ? "https://mangadex-proxy.deon3356.workers.dev/?manga_id={$manga->manga_id}&filename={$manga->cover}"
            : site_url('/wp-content/uploads/2025/07/default-cover.jpg');

        $detail_url = esc_url(site_url('/manga-detail/?id=' . $manga->manga_id));

        echo '<a href="' . $detail_url . '">';
        echo '<img src="' . esc_url($cover) . '" alt="' . esc_attr($manga->title) . '">';
        echo '</a>';
    }

    echo '</div></div>';

    return ob_get_clean();
}
add_shortcode('manga_read_history_mini', 'manga_read_history_mini_shortcode');
