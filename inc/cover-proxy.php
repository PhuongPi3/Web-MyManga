<?php
$remote_url_512 = "https://uploads.mangadex.org/covers/$manga_id/$filename";
$remote_url_original = str_replace('.512.jpg', '.jpg', $remote_url_512); // fallback nếu là jpg
$remote_url_original = str_replace('.512.jpg', '.png', $remote_url_512); // fallback nếu là png

$try = [$remote_url_512, $remote_url_original];
$found = false;

foreach ($try as $url) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if ($http_code == 200) {
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);

    if (preg_match('/Content-Type: (.*)/i', $header, $matches)) {
      $content_type = trim($matches[1]);
    } else {
      $content_type = 'image/jpeg';
    }

    header("Content-Type: $content_type");
    echo $body;
    $found = true;
    break;
  }
  curl_close($ch);
}

if (!$found) {
  header("Location: /wp-content/uploads/default-cover.jpg");
  exit;
}
