<?php
if (!defined('ABSPATH')) exit;

function mangadex_create_rating_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'manga_rating';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        manga_id VARCHAR(255) NOT NULL,
        rating TINYINT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id, manga_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
