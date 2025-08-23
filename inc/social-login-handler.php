<?php
// 👉 Xử lý khi user đăng ký bằng Google (Nextend Social Login)
add_action('nsl_register_user', function ($user_id, $provider, $nsl_user) {
    if ($provider !== 'google') return;

    // Lấy thông tin user từ Nextend
    $google_id = $nsl_user->get_identifier();
    $email     = $nsl_user->get_email();
    $name      = $nsl_user->get_name();
    $avatar    = $nsl_user->get_avatar_url();

    // Gán role là "user" nếu không phải admin
    $user = get_user_by('ID', $user_id);
    if ($user && !in_array('administrator', (array) $user->roles)) {
        wp_update_user([
            'ID'   => $user_id,
            'role' => 'user',
        ]);
    }

    // Lưu thông tin Google vào usermeta
    update_user_meta($user_id, 'google_id', $google_id);
    update_user_meta($user_id, 'avatar_url', $avatar);
    update_user_meta($user_id, 'custom_name', $name);

    // Ghi log kiểm tra
    error_log('✅ Google login usermeta saved: ' . print_r([
        'user_id'   => $user_id,
        'email'     => $email,
        'name'      => $name,
        'avatar'    => $avatar,
        'google_id' => $google_id,
    ], true));
}, 10, 3);


// 👉 Chặn non-admin truy cập wp-admin
add_action('admin_init', function () {
    if (!current_user_can('administrator') && is_admin() && !wp_doing_ajax()) {
        wp_redirect(home_url());
        exit;
    }
});


// 👉 Redirect lại khi login bằng Google nếu cần
add_action('template_redirect', function () {
    if (isset($_GET['loginSocial']) && is_page('login')) {
        wp_redirect(home_url('/?loginSocial=' . $_GET['loginSocial']));
        exit;
    }
});


// 👉 Hàm lấy thông tin người dùng hiện tại (thay cho bảng custom)
function get_current_manga_user_data() {
    if (!is_user_logged_in()) return null;

    $user = wp_get_current_user();

    return [
        'id'        => $user->ID,
        'username'  => $user->user_login,
        'email'     => $user->user_email,
        'name'      => get_user_meta($user->ID, 'custom_name', true),
        'avatar'    => get_user_meta($user->ID, 'avatar_url', true),
        'google_id' => get_user_meta($user->ID, 'google_id', true),
    ];
}
// 👉 Ghi đè URL redirect sau khi login qua Google (Nextend Social Login)
add_filter('nsl_login_redirect_url', function ($redirect_url, $user_id, $provider) {
    return home_url('/'); // Hoặc đổi thành trang bạn muốn, như /dashboard/
}, 10, 3);
