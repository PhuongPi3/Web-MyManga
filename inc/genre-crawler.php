<?php
// Hook admin để import genre
add_action('admin_post_import_manga_genres', function() {
    global $wpdb;

    // Gọi API MangaDex (lấy 1 manga để có tags)
    $data = mangadex_api_get('/manga?limit=5'); // lấy vài cái để có đủ genre

    if (empty($data['data'])) {
        wp_die('❌ Không lấy được manga nào.');
    }

    $imported = [];

    foreach ($data['data'] as $manga) {
        foreach ($manga['attributes']['tags'] as $tag) {
            if ($tag['attributes']['group'] !== 'genre') continue;

            $id = sanitize_text_field($tag['id']);
            $name = sanitize_text_field($tag['attributes']['name']['en'] ?? '(No name)');

            $wpdb->replace(
                "{$wpdb->prefix}manga_genre",
                ['id' => $id, 'name' => $name],
                ['%s', '%s']
            );

            $imported[] = "$name ($id)";
        }
    }

    echo '<h3>✅ Imported genres:</h3>';
    echo '<pre>'.print_r($imported, true).'</pre>';
    echo '<a href="'.admin_url().'">🔙 Quay lại admin</a>';
});
