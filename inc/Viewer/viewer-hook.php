<?php
if (!defined('ABSPATH')) exit;
add_action('template_redirect', 'mangadex_log_manga_view');

function mangadex_log_manga_view() {
    if (empty($_GET['chapter']) || strlen($_GET['chapter']) < 10) return;

    $chapter_id = sanitize_text_field($_GET['chapter']);
    global $wpdb;

    $chapter_table = $wpdb->prefix . 'manga_chapter';
    $viewer_table = $wpdb->prefix . 'manga_viewer';

    $manga_id = $wpdb->get_var($wpdb->prepare(
        "SELECT manga_id FROM $chapter_table WHERE chapter_id = %s",
        $chapter_id
    ));

    if (!$manga_id) return;

    $user_id = get_current_user_id();
    $guest_token = !$user_id ? mangareader_get_guest_token() : null;

    if (!$user_id && !$guest_token) return;

    // Ghi log — nếu trùng, sẽ bị UNIQUE KEY chặn
    $wpdb->insert($viewer_table, [
        'manga_id'    => $manga_id,
        'chapter_id'  => $chapter_id,
        'user_id'     => $user_id ?: null,
        'guest_token' => $guest_token,
        'viewed_at'   => current_time('mysql')
    ]);
}
