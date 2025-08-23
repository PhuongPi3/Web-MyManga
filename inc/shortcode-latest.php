<?php
/**
 * Shortcode: [mangadex_latest]
 * Hiển thị lưới truyện mới cập nhật
 */

add_shortcode('mangadex_latest', function() {
    global $wpdb;

    ob_start();

    $limit = 24;
    $paged = max(1, intval(get_query_var('paged') ?: get_query_var('page') ?: 1));
    $offset = ($paged - 1) * $limit;

    echo '<h2>Latest Updates</h2>';
    echo '<div class="manga-section latest">';

    $table = $wpdb->prefix . 'manga_list';
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");

    $latest = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $limit, $offset)
    );

    foreach ($latest as $manga) {
        $manga_id = esc_attr($manga->manga_id);
        $cover = esc_attr($manga->cover);
        $title = esc_html($manga->title);

        $cover_url = !empty($cover) && strtolower($cover) !== 'default.jpg'
            ? "https://mangadex-proxy.deon3356.workers.dev/?manga_id=$manga_id&filename=$cover"
            : site_url("/wp-content/uploads/2025/07/default-cover.jpg");

        $chapters_table = $wpdb->prefix . 'manga_chapter';
       $chapter_sql = $wpdb->prepare(
            "SELECT chapter_id, chapter_number, title, created_at FROM $chapters_table WHERE manga_id = %s ORDER BY created_at DESC LIMIT 3",
            $manga->manga_id
        );
        $chapters = $wpdb->get_results($chapter_sql);

        echo '<div class="manga-card">';
        echo '<a href="/manga-detail/?id=' . $manga_id . '">';
        echo '<div class="manga-cover latest" style="background-image: url(' . esc_url($cover_url) . ');">';
        echo '<div class="manga-info">';
        echo '<h3 class="manga-title" style="font-size:13px; margin:6px 0 0;">' . $title . '</h3>';
        echo '</div>';
        echo '</div>';
        echo '</a>';

        echo '<div class="manga-chapters">';
        if (!empty($chapters)) {
            foreach ($chapters as $chapter) {
                $raw_title = trim($chapter->title);
                $safe_title = !empty($raw_title) ? esc_html($raw_title) : 'Chapter ' . intval($chapter->chapter_number);
                $chapter_link = esc_url(site_url("/reader/?chapter=" . $chapter->chapter_id));
                $time_diff = human_time_diff(strtotime($chapter->created_at), current_time('timestamp')) . ' ago.';

                echo "<div class='chapter-item'>
                        <a href='$chapter_link'>$safe_title</a>
                        <span class='chapter-time'>$time_diff</span>
                      </div>";
            }
        } else {
            echo "<div class='chapter-item'>Chưa có chương nào</div>";
        }
        echo '</div>';

        echo '</div>';
    }

    echo '</div>'; // manga-section.latest

    // ✅ PHÂN TRANG
    $total_pages = max(1, ceil($total / $limit));
    if ($total_pages > 1) {
        echo '<div class="pagination">';
        $base_url = get_permalink(); // Trang hiện tại
        $range = 2;
        $pages_to_show = [];

        // Luôn hiển thị trang đầu & cuối
        $pages_to_show[] = 1;
        $pages_to_show[] = $total_pages;

        for ($i = $paged - $range; $i <= $paged + $range; $i++) {
            if ($i > 1 && $i < $total_pages) {
                $pages_to_show[] = $i;
            }
        }

        $pages_to_show = array_unique($pages_to_show);
        sort($pages_to_show);
        $last_page = 0;

        foreach ($pages_to_show as $page_num) {
            if ($last_page && $page_num - $last_page > 1) {
                echo "<span class='dots'>...</span>";
            }

            $url = ($page_num == 1)
                ? esc_url($base_url)
                : esc_url(trailingslashit($base_url) . 'page/' . $page_num . '/');

            if ($page_num == $paged) {
                echo "<strong>$page_num</strong>";
            } else {
                echo "<a href='$url'>$page_num</a>";
            }

            $last_page = $page_num;
        }

        echo '</div>';
    }

    ?>
    <style>
    .manga-chapters {
      background: #111;
      padding: 8px;
      color: #ccc;
      font-size: 12px;
      line-height: 1.4;
    }
    .chapter-item {
      display: flex;
      justify-content: space-between;
      border-top: 1px solid rgba(255,255,255,0.1);
      padding: 4px 0;
    }
    .chapter-item:first-child {
      border-top: none;
    }
    .chapter-item a:hover {
      text-decoration: underline;
    }
    .chapter-time {
      opacity: 0.8;
      font-size: 11px;
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
    
    .pagination {
      margin-top: 16px;
      text-align: center;
    }
    .pagination a, .pagination strong {
      margin: 0 4px;
      padding: 4px 8px;
      background: #222;
      color: #ccc;
      text-decoration: none;
      border-radius: 4px;
    }
    .pagination strong {
      background: #444;
      font-weight: bold;
    }
    .manga-section.latest {
      display: grid;
      gap: 16px;
      grid-template-columns: repeat(2, 1fr);
    }
    @media (min-width: 768px) {
      .manga-section.latest {
          grid-template-columns: repeat(3, 1fr);
      }
    }
    @media (min-width: 1024px) {
      .manga-section.latest {
          grid-template-columns: repeat(4, 1fr);
      }
    }
    </style>
    <?php

    return ob_get_clean();
});
