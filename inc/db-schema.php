<?php
register_activation_hook(__FILE__, 'mangadex_create_table');

function mangadex_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'manga_list';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        manga_id VARCHAR(255) UNIQUE,
        title VARCHAR(255),
        slug VARCHAR(255),
        status VARCHAR(50),
        created_at DATETIME
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
