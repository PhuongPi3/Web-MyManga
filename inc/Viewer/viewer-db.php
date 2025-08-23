<?php
if (!defined('ABSPATH')) exit;

function mangadex_create_viewer_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'manga_viewer';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        manga_id VARCHAR(255) NOT NULL,
        user_id BIGINT,
        guest_token VARCHAR(255),
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
