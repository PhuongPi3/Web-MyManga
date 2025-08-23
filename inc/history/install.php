<?php
function mangareader_create_tables() {
    global $wpdb;
    $table = $wpdb->prefix . 'manga_read_history';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) DEFAULT NULL,
        guest_token VARCHAR(255) DEFAULT NULL,
        manga_id VARCHAR(255) NOT NULL,
        chapter_id VARCHAR(255) NOT NULL,
        updated_at DATETIME NOT NULL,
        UNIQUE KEY unique_read (user_id, guest_token, manga_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
