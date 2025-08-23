<?php
function mangadex_top_chart_shortcode() {
    global $wpdb;

    // ✅ Top view từ bảng viewer
    $top_view = $wpdb->get_results("
        SELECT 
            v.manga_id, 
            ml.title, 
            ml.cover, 
            COUNT(*) as total_view
        FROM {$wpdb->prefix}manga_viewer v
        LEFT JOIN {$wpdb->prefix}manga_list ml ON v.manga_id = ml.manga_id
        GROUP BY v.manga_id
        ORDER BY total_view DESC
        LIMIT 10
    ");

    // ✅ Top rating giữ nguyên
    $rating_sql = "
    SELECT 
        r.manga_id,
        AVG(r.rating) as avg_rating,
        COUNT(r.id) as total_vote,
        ml.title,
        ml.cover
    FROM {$wpdb->prefix}manga_rating r
    LEFT JOIN {$wpdb->prefix}manga_list ml ON r.manga_id = ml.manga_id
    GROUP BY r.manga_id
    ORDER BY avg_rating DESC, total_vote DESC
    LIMIT 10
    ";
    $top_rating = $wpdb->get_results($rating_sql);


    ob_start();
    ?>
    <style>
        /* Giữ nguyên style như cũ */
        .top-chart-wrapper {
            background: #fff;
            padding: 16px;
            border-radius: 12px;
            max-width: 900px;
            margin: 0 auto;
            border: 1px solid #ddd;
        }
        .top-chart-tabs {
            justify-content: center;
            flex-wrap: wrap;
            display: flex;
            gap: 32px;
            margin-bottom: 16px;
        }
        .top-chart-tabs button {
            background: none;
            color: #333;
            font-weight: bold;
            border: none;
            cursor: pointer;
            padding: 8px 16px;
            font-size: 18px;
            border-bottom: 2px solid transparent;
            transition: all 0.2s ease;
        }
        .top-chart-tabs button.active {
            border-bottom: 2px solid #facc15;
            color: #000;
        }
        .top-chart-list {
            display: none;
        }
        .top-chart-list.active {
            display: block;
        }
        .top-chart-item {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            background: #f9f9f9;
            padding: 8px;
            border-radius: 8px;
            border: 1px solid #eee;
        }
        .top-chart-item img {
            width: 64px;
            height: 96px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .top-chart-info {
            flex: 1;
        }
        .top-chart-info h4 {
            margin: 0 0 4px;
            font-size: 16px;
            color: #222;
        }
        .top-chart-info span {
            color: #666;
            font-size: 14px;
        }
        @media (max-width: 480px) {
            .top-chart-tabs {
                gap: 12px;
            }

            .top-chart-tabs button {
                font-size: 16px;
                padding: 6px 12px;
            }

            .top-chart-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .top-chart-item img {
                width: 100%;
                height: auto;
                max-width: 120px;
                align-self: center;
            }

            .top-chart-info {
                text-align: center;
                width: 100%;
                margin-top: 8px;
            }
        }
    </style>
    <div class="top-chart-wrapper">
        <div class="top-chart-tabs">
            <button class="tab-btn active" data-tab="view">Top View</button>
            <button class="tab-btn" data-tab="rating">Top Like</button>
        </div>
        <div class="top-chart-list view-list active">
            <?php foreach ($top_view as $index => $manga): ?>
    <?php
        if (!$manga->title || !$manga->cover) {
            $fetched = fetch_manga_from_api($manga->manga_id);
            $manga->title = $fetched['title'];
            $manga->cover = $fetched['cover'];
        }
    ?>

                <div class="top-chart-item">
                    <img src="<?= esc_url(get_manga_cover_url($manga->manga_id, $manga->cover)) ?>" alt="cover">
                    <div class="top-chart-info">
                        <h4>#<?= $index + 1 ?>. <?= esc_html($manga->title ?: $manga->manga_id) ?></h4>
                        <span><?= number_format($manga->total_view) ?> views</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="top-chart-list rating-list">
           <?php foreach ($top_rating as $index => $manga): ?>
    <?php
        if (!$manga->title || !$manga->cover) {
            $fetched = fetch_manga_from_api($manga->manga_id);
            $manga->title = $fetched['title'];
            $manga->cover = $fetched['cover'];
        }
    ?>
    <div class="top-chart-item">
        <img src="<?= esc_url(get_manga_cover_url($manga->manga_id, $manga->cover)) ?>" alt="cover">
        <div class="top-chart-info">
            <h4>#<?= $index + 1 ?>. <?= esc_html($manga->title ?: $manga->manga_id) ?></h4>
            <span><?= round($manga->avg_rating, 2) ?>/5 (<?= $manga->total_vote ?> votes)</span>
        </div>
    </div>
<?php endforeach; ?>

        </div>
    </div>

    <script>
        document.querySelectorAll(".top-chart-tabs button").forEach(btn => {
            btn.addEventListener("click", () => {
                document.querySelectorAll(".top-chart-tabs button").forEach(b => b.classList.remove("active"));
                btn.classList.add("active");

                const tab = btn.dataset.tab;
                document.querySelector(".top-chart-list.view-list").classList.toggle("active", tab === "view");
                document.querySelector(".top-chart-list.rating-list").classList.toggle("active", tab === "rating");
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('manga_top_chart', 'mangadex_top_chart_shortcode');

// ✅ Hàm cover (nếu không có thì lấy ảnh mặc định)
function get_manga_cover_url($manga_id, $filename = '') {
    if ($filename) {
        return "https://mangadex-proxy.deon3356.workers.dev/?manga_id={$manga_id}&filename={$filename}";
    }
    return site_url("/wp-content/uploads/2025/07/default-cover.jpg");
}

function fetch_manga_from_api($manga_id) {
    $url = "https://api.mangadex.org/manga/{$manga_id}?includes[]=cover_art";
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return ['title' => 'Unknown', 'cover' => ''];

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($data['data'])) return ['title' => 'Unknown', 'cover' => ''];

    $attributes = $data['data']['attributes'];
    $title = $attributes['title']['en'] ?? array_values($attributes['title'])[0] ?? 'No Title';

    // Tìm cover art
    $cover = '';
    foreach ($data['data']['relationships'] as $rel) {
        if ($rel['type'] === 'cover_art') {
            $cover = $rel['attributes']['fileName'];
            break;
        }
    }

    return [
        'title' => $title,
        'cover' => $cover
    ];
}
