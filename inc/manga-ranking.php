<?php
if (!defined('ABSPATH')) exit;

function mangadex_manga_ranking_shortcode() {
    global $wpdb;
    $table = $wpdb->prefix . 'manga_list';

    // Dữ liệu top view
    $top_view = $wpdb->get_results("
        SELECT manga_id, title, cover, views
        FROM $table
        ORDER BY views DESC
        LIMIT 10
    ");

    // Dữ liệu top rating
    $top_rating = $wpdb->get_results("
        SELECT manga_id, title, cover, rating_total, rating_count,
        (rating_total / rating_count) as avg_rating
        FROM $table
        WHERE rating_count > 0
        ORDER BY avg_rating DESC
        LIMIT 10
    ");

    ob_start();
    ?>

    <div class="ranking-container text-white bg-neutral-900 rounded-lg p-4">
        <h2 class="text-xl font-bold mb-4">🏆 Bảng xếp hạng tháng này</h2>
        <div class="flex space-x-2 mb-4">
            <button class="tab-btn bg-orange-500 px-4 py-1 rounded font-semibold" data-tab="top">Top</button>
            <button class="tab-btn bg-neutral-700 px-4 py-1 rounded" data-tab="rating">Yêu thích</button>
            <button class="tab-btn bg-neutral-700 px-4 py-1 rounded" data-tab="new">Mới</button>
        </div>

        <div class="tab-content" id="tab-top">
            <?php echo mangadex_render_ranking_list($top_view, 'views'); ?>
        </div>
        <div class="tab-content hidden" id="tab-rating">
            <?php echo mangadex_render_ranking_list($top_rating, 'rating'); ?>
        </div>
        <div class="tab-content hidden" id="tab-new">
            <p class="text-neutral-400">Tính năng đang phát triển...</p>
        </div>
    </div>

    <style>
        .ranking-container .active-tab {
            background-color: #ea580c !important;
        }
    </style>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const tabs = document.querySelectorAll(".tab-btn");
            const contents = document.querySelectorAll(".tab-content");

            tabs.forEach(tab => {
                tab.addEventListener("click", () => {
                    tabs.forEach(t => t.classList.remove("bg-orange-500", "active-tab"));
                    contents.forEach(c => c.classList.add("hidden"));

                    tab.classList.add("bg-orange-500", "active-tab");
                    const selectedTab = document.getElementById("tab-" + tab.dataset.tab);
                    selectedTab.classList.remove("hidden");
                });
            });
        });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('manga_ranking', 'mangadex_manga_ranking_shortcode');

// UI render function
function mangadex_render_ranking_list($list, $type = 'views') {
    ob_start();
    echo '<ul class="space-y-3">';
    foreach ($list as $i => $manga):
        $rank = $i + 1;
        $link = site_url("/manga-detail/?id={$manga->manga_id}");
        $score = $type === 'views' 
            ? number_format($manga->views / 1000, 1) . 'k'
            : '★ ' . round($manga->rating_total / $manga->rating_count, 1);
        ?>
        <li class="flex items-start gap-3">
            <div class="text-2xl font-bold w-6"><?= $rank ?></div>
            <a href="<?= esc_url($link) ?>" class="flex items-center gap-3">
                <img src="<?= esc_url($manga->cover) ?>" alt="" class="w-[50px] h-[75px] object-cover rounded">
                <div>
                    <div class="font-semibold"><?= esc_html($manga->title) ?></div>
                    <div class="text-neutral-400 text-sm"><?= $score ?></div>
                </div>
            </a>
        </li>
    <?php endforeach;
    echo '</ul>';
    return ob_get_clean();
}
