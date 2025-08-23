<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_mangadex_submit_rating', 'mangadex_submit_rating');

function mangadex_submit_rating() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You need to log in to submit a rating.');
    }

    $user_id = get_current_user_id();
    $manga_id = sanitize_text_field($_POST['manga_id']);
    $rating = intval($_POST['rating']);

    if ($rating < 1 || $rating > 5) {
        wp_send_json_error('Invalid rating value.');
    }

    global $wpdb;
    $table = $wpdb->prefix . 'manga_rating';

    $wpdb->query($wpdb->prepare(
        "INSERT INTO $table (user_id, manga_id, rating) VALUES (%d, %s, %d)
         ON DUPLICATE KEY UPDATE rating = %d, created_at = NOW()",
         $user_id, $manga_id, $rating, $rating
    ));

    wp_send_json_success('Rating submitted successfully.');
}
