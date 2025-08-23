<?php
add_action('wp_ajax_load_manga_comments', 'load_manga_comments_ajax');
add_action('wp_ajax_nopriv_load_manga_comments', 'load_manga_comments_ajax');

function load_manga_comments_ajax() {
    global $wpdb;
    
    $manga_id   = sanitize_text_field($_POST['manga_id'] ?? '');
    $chapter_id = sanitize_text_field($_POST['chapter_id'] ?? '');
    $page       = intval($_POST['page'] ?? 1);
    $per_page   = 5;
    $offset     = ($page - 1) * $per_page;

    $comments = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}manga_comments WHERE manga_id = %s AND chapter_id = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $manga_id, $chapter_id, $per_page, $offset
    ));

    ob_start();
    foreach ($comments as $cmt) {
        echo "<div class='comment-item'>
            <img src='{$cmt->avatar_url}' alt='avatar'>
            <div>
                <strong>{$cmt->user_name}</strong> <span class='comment-date'>({$cmt->created_at})</span>
                <p>{$cmt->comment_text}</p>
            </div>
        </div>";
    }
    wp_send_json_success(ob_get_clean());
}
