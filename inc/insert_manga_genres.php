<?php 
function insert_manga_genres($manga_id, $tags) {
  global $wpdb;

  foreach ($tags as $tag) {
    if (!isset($tag['attributes']['name']['en'])) continue;
    $genre_name = $tag['attributes']['name']['en'];

    // Insert genre nếu chưa có
    $genre = $wpdb->get_row($wpdb->prepare(
      "SELECT id FROM {$wpdb->prefix}manga_genre WHERE name = %s",
      $genre_name
    ));

    if (!$genre) {
      $wpdb->insert("{$wpdb->prefix}manga_genre", [
        'name' => $genre_name
      ]);
      $genre_id = $wpdb->insert_id;
    } else {
      $genre_id = $genre->id;
    }

    // Map manga_id ↔ genre_id
    $wpdb->replace("{$wpdb->prefix}manga_genre_map", [
      'manga_id' => $manga_id,
      'genre_id' => $genre_id
    ]);
  }
}
