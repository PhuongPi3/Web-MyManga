<?php
/*
Plugin Name: MangaDex API
Description: Lấy dữ liệu MangaDex, lưu DB, hiển thị qua shortcode.
Version: 1.0
*/

if (!defined('ABSPATH')) exit;

// Tải tất cả các file phụ
foreach ([
    'shortcode-detail',
    'shortcode-reader',
    'cron-job',
    'admin-menu',
    'api-proxy',
    'comment-filter',
    'user-action',
    'shortcode-home',
    'shortcode_manga_search_filter',
    'genre-crawler',
    'shortcode-latest',
    'shortcode-featured',
    'social-login-handler',
    'ajax-comment-handler',
   
] as $file) {
    require_once plugin_dir_path(__FILE__) . "inc/$file.php";
}

// Định nghĩa nếu chưa có
if (!defined('MANGAREADER_PLUGIN_DIR')) {
    define('MANGAREADER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Nạp module history
require_once MANGAREADER_PLUGIN_DIR . 'inc/history/install.php';
require_once MANGAREADER_PLUGIN_DIR . 'inc/history/functions.php';
require_once MANGAREADER_PLUGIN_DIR . 'inc/history/display-history.php';
require_once MANGAREADER_PLUGIN_DIR . 'inc/history/mangareader_history_mini.php';



//nap moudule viewer+ rating
register_activation_hook(__FILE__, function () {
    require_once plugin_dir_path(__FILE__) . 'inc/Rating/rating-db.php';
    require_once plugin_dir_path(__FILE__) . 'inc/Viewer/viewer-db.php';
    mangadex_create_rating_table();
    mangadex_create_viewer_table();
});


// Tải logic
require_once __DIR__ . '/inc/Rating/rating-ajax.php';
require_once __DIR__ . '/inc/Rating/shortcode-rating.php';
require_once __DIR__ . '/inc/Viewer/viewer-hook.php';

//top
require_once __DIR__ . '/inc/Top/top_ranking.php';



function mangadex_create_all_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $prefix = $wpdb->prefix;

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_list (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        manga_id VARCHAR(255) NOT NULL UNIQUE,
        title VARCHAR(255),
        slug VARCHAR(255),
        status VARCHAR(50),
        created_at DATETIME,
        cover VARCHAR(255)
    ) $charset_collate;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_chapter (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        manga_id VARCHAR(255),
        chapter_id VARCHAR(255) UNIQUE,
        chapter_number VARCHAR(50),
        title VARCHAR(255),
        created_at DATETIME
    ) $charset_collate;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_genre (
        id VARCHAR(255) NOT NULL PRIMARY KEY,
        name VARCHAR(255)
    ) $charset_collate;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_genre_map (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        manga_id VARCHAR(255),
        genre_id VARCHAR(255)
    ) $charset_collate;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_comments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        manga_id VARCHAR(255),
        chapter_id VARCHAR(255),
        user_id BIGINT UNSIGNED,
        user_name VARCHAR(255),
        avatar_url TEXT,
        comment_text TEXT,
        parent_id BIGINT DEFAULT 0,
        created_at DATETIME
    ) $charset_collate;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_read_history (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NULL,
        guest_token VARCHAR(255) NULL,
        manga_id VARCHAR(255),
        chapter_id VARCHAR(255),
        updated_at DATETIME,
        UNIQUE KEY uniq_read (user_id, guest_token, manga_id)
    ) $charset_collate;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_bookmarks (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        manga_id VARCHAR(255),
        added_at DATETIME
    ) $charset_collate;");

    dbDelta("CREATE TABLE IF NOT EXISTS {$prefix}manga_users (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        wp_user_id BIGINT UNSIGNED NOT NULL,
        google_id VARCHAR(255) UNIQUE,
        name VARCHAR(255),
        email VARCHAR(255),
        avatar TEXT,
        created_at DATETIME
    ) $charset_collate;");
}

// XÓA DỮ LIỆU KHÁCH SAU 14 NGÀY
if (!wp_next_scheduled('mangareader_cleanup_old_guests')) {
    wp_schedule_event(time(), 'daily', 'mangareader_cleanup_old_guests');
}
add_action('mangareader_cleanup_old_guests', 'mangareader_delete_old_guest_history');

// CSS
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'mangadex-list',
        plugin_dir_url(__FILE__) . 'assets/css/mangadex-list.css',
        [],
        '1.0'
    );
});

// MENU DROPDOWN: Genres, Themes, Formats
add_filter('wp_nav_menu_items', function ($items, $args) {
    if ($args->theme_location !== 'primary') return $items;

    global $wpdb;
    $genres  = $wpdb->get_results("SELECT id, name FROM wpoz_manga_genre ORDER BY name ASC");
    $themes  = $wpdb->get_results("SELECT id, name FROM wpoz_manga_theme ORDER BY name ASC");
    $formats = $wpdb->get_results("SELECT id, name FROM wpoz_manga_format ORDER BY name ASC");

    $base = site_url('/genre-list/?keyword=&sort=latest&tag=');

    $html = '<li class="menu-item menu-item-has-children mega-menu-item">';
    $html .= '<a href="#">Tag</a><div class="tag-mega-menu">';

    foreach (array_chunk($genres, 25) as $i => $chunk) {
        $html .= '<div class="mega-col">';
        if ($i === 0) $html .= '<strong>Genres</strong>';
        foreach ($chunk as $g) {
            $html .= "<a href='" . esc_url($base . 'genre_' . intval($g->id)) . "'>" . esc_html($g->name) . "</a>";
        }
        $html .= '</div>';
    }

    foreach (array_chunk($themes, 25) as $i => $chunk) {
        $html .= '<div class="mega-col">';
        if ($i === 0) $html .= '<strong>Themes</strong>';
        foreach ($chunk as $t) {
            $html .= "<a href='" . esc_url($base . 'theme_' . intval($t->id)) . "'>" . esc_html($t->name) . "</a>";
        }
        $html .= '</div>';
    }

    foreach (array_chunk($formats, 25) as $i => $chunk) {
        $html .= '<div class="mega-col">';
        if ($i === 0) $html .= '<strong>Formats</strong>';
        foreach ($chunk as $f) {
            $html .= "<a href='" . esc_url($base . 'format_' . intval($f->id)) . "'>" . esc_html($f->name) . "</a>";
        }
        $html .= '</div>';
    }

    $html .= '</div></li>';

    // 🧩 Chèn sau <li id="menu-item-78">
    $needle = 'id="menu-item-78"';
    $pos = strpos($items, $needle);
    if ($pos !== false) {
        // Tìm vị trí đóng thẻ </li> của menu-item-78
        $end_pos = strpos($items, '</li>', $pos);
        if ($end_pos !== false) {
            $end_pos += 5; // Bao gồm cả chiều dài '</li>'
            $items = substr_replace($items, $html, $end_pos, 0);
        }
    }

    return $items;
}, 10, 2);


// Đăng ký user Google
add_action('nsl_register_user', function ($user_id, $provider, $nsl_user) {
    if ($provider !== 'google') return;
    global $wpdb;
    $table = $wpdb->prefix . 'manga_users';

    $google_id = $nsl_user->get_identifier();
    $email     = $nsl_user->get_email();
    $name      = $nsl_user->get_name();
    $avatar    = $nsl_user->get_avatar_url();
    $user      = get_user_by('ID', $user_id);
    $username  = $user ? $user->user_login : '';

    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE wp_user_id = %d", $user_id));
    if (!$exists) {
        $wpdb->insert($table, [
            'wp_user_id' => $user_id,
            'google_id'  => $google_id ?: null,
            'name'       => $name,
            'email'      => $email,
            'avatar'     => $avatar,
            'created_at' => current_time('mysql'),
        ]);
    }
});

// Ẩn thanh admin frontend nếu không phải admin
add_action('after_setup_theme', function () {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
});

// Tùy chỉnh ảnh đại diện
add_filter('get_avatar_url', function ($url, $id_or_email, $args) {
    global $wpdb;
    $user_id = 0;

    if (is_numeric($id_or_email)) {
        $user_id = $id_or_email;
    } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
        $user_id = $id_or_email->user_id;
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        $user_id = $user ? $user->ID : 0;
    }

    if ($user_id) {
        $user = $wpdb->get_row($wpdb->prepare("SELECT user_url FROM wpoz_users WHERE ID = %d", $user_id));
        if ($user && !empty($user->user_url)) {
            return esc_url($user->user_url);
        }
    }

    return $url;
}, 10, 3);



// Tạo bảng khi cài
register_activation_hook(__FILE__, function () {
    if (function_exists('manga_top_create_table')) {
        manga_top_create_table();
    }
});
add_action('init', function () {
    if (isset($_GET['test_crawl'])) {
        truyendex_crawl_top_manga('followed');
        truyendex_crawl_top_manga('rating');
        echo 'Đã crawl xong.';
        exit;
    }
});


//token guest 
add_action('init', 'mangareader_ensure_guest_token');

function mangareader_ensure_guest_token() {
    if (!is_user_logged_in() && !isset($_COOKIE['manga_guest_token'])) {
        $token = wp_generate_uuid4();

        // Set cookie cho khách, thời hạn 14 ngày
        setcookie('manga_guest_token', $token, time() + (14 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

        // Cho phép dùng luôn trong request hiện tại
        $_COOKIE['manga_guest_token'] = $token;
    }
}

if ( ! function_exists( 'wp_get_current_user' ) ) {
    require_once ABSPATH . 'wp-includes/pluggable.php';
}

//xoa binh luan
add_action('admin_post_delete_manga_comment', function() {
    if (!isset($_GET['comment_id'])) wp_die('Missing comment ID');
    $comment_id = intval($_GET['comment_id']);
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_manga_comment_' . $comment_id)) {
        wp_die('Nonce verification failed');
    }
    global $wpdb;
    $current_user = wp_get_current_user();

    // Lấy bình luận
    $comment = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}manga_comments WHERE id = %d", $comment_id));
    if (!$comment) wp_die('Comment not found');

    // Kiểm tra quyền: admin hoặc chủ comment
    if (!current_user_can('manage_options') && $comment->user_id != $current_user->ID) {
        wp_die('Bạn không có quyền xóa bình luận này.');
    }

    // Xóa bình luận
    $wpdb->delete($wpdb->prefix . 'manga_comments', ['id' => $comment_id]);

    // Redirect về trang trước
    wp_safe_redirect(wp_get_referer() ?: home_url());
    exit;
});


function vip_membership_promo_shortcode() {
    if ( is_user_logged_in() && function_exists('pms_get_subscription_plans') ) {
        $user_id = get_current_user_id();
        $subscriptions = pms_get_member_subscriptions( $user_id );

        foreach ( $subscriptions as $sub ) {
            $plan = pms_get_subscription_plan( $sub->subscription_plan_id );$slug = isset($plan->slug) ? strtolower($plan->slug) : '';
$name = isset($plan->name) ? strtolower($plan->name) : '';

if ( $slug === 'vip' || $name === 'vip' )  {
                return ''; // Ẩn nếu đã là thành viên gói VIP
            }
        }
    }

    ob_start();
    ?>
    <div class="vip-promo-box" style="max-width: 700px; margin: 0 auto; background: #1e1e1e; color: #ffffff; padding: 30px; border-radius: 16px; text-align: center; box-shadow: 0 0 10px rgba(0,0,0,0.4); font-family: sans-serif;">
        <h2 style="font-size: 36px; font-weight: 700; color: #f1f1f1; margin-bottom: 16px;">🔥 VIP Membership Deal!</h2>
        <p style="font-size: 18px; margin-bottom: 10px;">Unlock full access to our AI Chatbot and premium features.</p>
        <p style="font-size: 20px; font-weight: 600; color: #00ffa2; margin-bottom: 0;">Special Offer: <del style="color: #888;">500,000đ</del> →</p>
        <p style="font-size: 36px; font-weight: bold; background: linear-gradient(90deg, #ffdd00, #ff8800); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin: 5px 0 10px;">249,000đ</p>
        <p style="font-size: 16px;">One-time payment for 1 month of VIP access</p>
        <a href="/product/vip-membership" style="display: inline-block; margin-top: 20px; padding: 14px 28px; font-size: 18px; background: #ff4757; color: white; border-radius: 8px; text-decoration: none; font-weight: 600;">Upgrade Now</a>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('vip_membership_promo', 'vip_membership_promo_shortcode');

