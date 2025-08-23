<?php
/**
 * Shortcode: [mangadex_featured]
 * Hiển thị slider truyện đề cử với Swiper.js
 */

add_shortcode('mangadex_featured', function() {
    global $wpdb;

    wp_enqueue_style('swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css');
    wp_enqueue_script('swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], null, true);

    ob_start();

    echo '<h2>🔥 Recommended </h2>';
    echo '<div class="swiper featured-carousel">';
    echo '<div class="swiper-wrapper">';

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

    // CSS giống như bạn đã có, hoặc có thể tách ra file CSS riêng nếu muốn

    ?>
    <style>
    .manga-cover.featured {
      aspect-ratio: 3 / 2;
      width: 100%;
      background: #000 center/contain no-repeat;
      border-radius: 8px;
    }
    .manga-card.featured,
    .featured-carousel .swiper-slide {
      width: 100%;
      height: auto;
      display: flex;
      flex-direction: column;
    }
    .featured-carousel {
      padding: 0;
      margin: 0 auto;
      overflow: visible;
      max-width: 100%;
    }
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
    </style>
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
                    768: { slidesPerView: 4 },
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
