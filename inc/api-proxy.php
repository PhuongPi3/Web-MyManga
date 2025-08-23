<?php
function mangadex_api_get($path) {
    $url = 'https://summer-block-b6e0.deon3356.workers.dev' . $path;
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return [];
    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}
