<?php
/**
 * Shortcode: [mangadex_home]
 * Hiển thị trang chủ kiểu TruyenDex với slider Swiper.js
 */

add_shortcode('mangadex_home', function() {
    global $wpdb;

    // Enqueue Swiper assets
    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css');
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], null, true);

    ob_start();

    echo '<div class="mangadex-home">';

    // ====== Featured Slider ======
    echo '<h2>🔥 Truyện Đề Cử</h2>';
    echo '<div class="swiper featured-carousel">';
    echo '<div class="swiper-wrapper">';

    // Lấy 10 truyện ngẫu nhiên có cover
    $featured = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}manga_list
        WHERE cover IS NOT NULL AND cover != '' AND LOWER(cover) != 'default.jpg'
        ORDER BY RAND() LIMIT 10"
    );

    foreach ($featured as $manga) {
        $manga_id = esc_attr($manga->manga_id);
        $cover = esc_attr($manga->cover);
        $title = esc_html($manga->title);

        $cover_url = !empty($cover) && strtolower($cover) !== 'default.jpg'
            ? "https://mangadex-proxy.deon3356.workers.dev/?manga_id=$manga_id&filename=$cover"
            : site_url("/wp-content/uploads/2025/07/default-cover.jpg");

       echo '<div class="swiper-slide">';
        echo '<a href="/manga-detail/?id=' . $manga_id . '">';
        echo '<div class="manga-card featured">';
       echo '<div class="manga-cover featured" style="background-image: url(' . esc_url($cover_url) . ');"></div>';

        echo '<div class="manga-info">';
        echo '<h3 class="manga-title">' . $title . '</h3>';
        echo '</div>';
        echo '</div>';
        echo '</a>';
        echo '</div>';

    }

    echo '</div>'; // swiper-wrapper
    echo '</div>'; // swiper

    // ====== Latest Grid ======
    echo '<h2>📌 Truyện Mới Cập Nhật</h2>';
   echo '<div class="manga-section latest">';


    $latest = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}manga_list ORDER BY created_at DESC LIMIT 20");

   foreach ($latest as $manga) {
    $manga_id = esc_attr($manga->manga_id);
    $cover = esc_attr($manga->cover);
    $title = esc_html($manga->title);

    $cover_url = !empty($cover) && strtolower($cover) !== 'default.jpg'
        ? "https://mangadex-proxy.deon3356.workers.dev/?manga_id=$manga_id&filename=$cover"
        : site_url("/wp-content/uploads/2025/07/default-cover.jpg");

    // Lấy 3 chương mới nhất
    $chapters_table = $wpdb->prefix . 'manga_chapter';
    $chapter_sql = $wpdb->prepare(
        "SELECT chapter_id, chapter_number, title, created_at FROM $chapters_table WHERE manga_id = %d ORDER BY created_at DESC LIMIT 3",
        $manga->manga_id
    );
    $chapters = $wpdb->get_results($chapter_sql);

    // Render card
    echo '<div class="manga-card">';
        echo '<a href="/manga-detail/?id=' . $manga_id . '">';
            echo '<div class="manga-cover latest" style="background-image: url(' . esc_url($cover_url) . ');">';
                echo '<div class="manga-info">';
                    echo '<h3 class="manga-title" style="font-size:13px; margin:6px 0 0;">' . $title . '</h3>';
                echo '</div>';
            echo '</div>';
        echo '</a>';

        // Nền đen rời chứa 3 chương
        echo '<div class="manga-chapters">';
            if (!empty($chapters)) {
                foreach ($chapters as $chapter) {
                    $raw_title = trim($chapter->title);
                    $safe_title = !empty($raw_title) ? esc_html($raw_title) : 'Chapter ' . intval($chapter->chapter_number);
                    $chapter_link = esc_url(site_url("/reader/?chapter=" . $chapter->chapter_id));
                    $time_diff = human_time_diff(strtotime($chapter->created_at), current_time('timestamp')) . ' trước';

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


    echo '</div>'; // latest grid
    echo '</div>'; // mangadex-home

    // CSS override để đảm bảo height 300px
echo '<style>
/* === Featured Cover === */
.manga-cover.featured {
  aspect-ratio: 3 / 2;
  width: 100%;
  background: #000 center/contain no-repeat;
  border-radius: 8px;
}

/* === Featured Card & Swiper Slide === */
.manga-card.featured,
.featured-carousel .swiper-slide {
  width: 100%;
  height: auto;
  display: flex;
  flex-direction: column;
}

/* === Swiper Container === */
.featured-carousel {
  padding: 0;
  margin: 0 auto;
  overflow: visible;
  max-width: 100%;
}

/* === Manga Title Overlay === */
.manga-title {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  word-break: break-word;
  white-space: normal;
  transition: all 0.3s ease;
}

.manga-card:hover .manga-title {
  -webkit-line-clamp: unset;
  max-height: none;
  overflow: visible;
  white-space: normal;
}

/* === Chapter List under Image === */
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

 .chapter-item {
        display: flex; justify-content: space-between;
        border-top: 1px solid rgba(255,255,255,0.1);
        padding: 4px 0;
        }

        .chapter-item:first-child { border-top: none; }
        .chapter-item a:hover { text-decoration: underline; }
        .chapter-time { opacity: 0.8; font-size: 11px; }

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

    /* === Lastest === */
    .manga-section.latest {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(2, 1fr); 
    }

    @media (min-width: 768px) {
    .manga-section.latest {
        grid-template-columns: repeat(4, 1fr);
    }
    }

    @media (min-width: 1024px) {
    .manga-section.latest {
        grid-template-columns: repeat(5, 1fr);
    }
}

        
</style>';





    // Swiper init script
    ?>
       <script>
(function() {
    function initSwiper() {
        if (typeof Swiper === 'undefined') {
            setTimeout(initSwiper, 100);
            return;
        }
        new Swiper('.featured-carousel', {
            slidesPerView: 3,
            spaceBetween: 10,
            loop: true,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false
            },
            centeredSlides: false,
            slidesPerGroup: 1,
            breakpoints: {
                0: { slidesPerView: 2 },
                480: { slidesPerView: 2 },
                768: { slidesPerView: 3 },
                1024: { slidesPerView: 4 },
                1440: { slidesPerView: 4 }
            }
        });
    }
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initSwiper, 300);
    });
})();
</script>


    <?php



    return ob_get_clean();
});
