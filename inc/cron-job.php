<?php
/**
 * Cron Job: Crawl MangaDex → DB (Genre + Theme + Format chuẩn)
 */

add_action('admin_post_run_manga_cron', 'mangadex_cron_task_safe');

function mangadex_cron_task_safe() {
    global $wpdb;

    $manga_table      = 'wpoz_manga_list';
    $chapter_table    = 'wpoz_manga_chapter';
    $genre_table      = 'wpoz_manga_genre';
    $theme_table      = 'wpoz_manga_theme';
    $format_table     = 'wpoz_manga_format';

    $genre_map_table  = 'wpoz_manga_genre_map';
    $theme_map_table  = 'wpoz_manga_theme_map';
    $format_map_table = 'wpoz_manga_format_map';

    echo "<pre>🔄 Bắt đầu crawl MangaDex...\n</pre>";

    $offset   = 0;
    $per_page = 10; // Crawl nhỏ để test

    while (true) {
        $url  = "/manga?limit=$per_page&offset=$offset&order[latestUploadedChapter]=desc&availableTranslatedLanguage[]=en&includes[]=cover_art";
        $data = mangadex_api_get($url);

        if (empty($data['data'])) {
            echo "<pre>✅ Hết manga.</pre>";
            break;
        }

        foreach ($data['data'] as $manga) {
            $manga_id = $manga['id'];
            $title    = $manga['attributes']['title']['en'] ?? '(No title)';
            $slug     = sanitize_title($title);
            $created  = $manga['attributes']['createdAt'] ?? '';
            $status   = $manga['attributes']['status'] ?? '';

            // === Lấy cover an toàn ===
            $cover_filename = '';
            $has_cover_art  = false;

            if (!empty($manga['relationships'])) {
                foreach ($manga['relationships'] as $rel) {
                    if ($rel['type'] === 'cover_art') {
                        $has_cover_art = true;
                        if (!empty($rel['attributes']['fileName'])) {
                            $cover_filename = $rel['attributes']['fileName'];
                        } else {
                            $cover_id = $rel['id'] ?? '';
                            if ($cover_id) {
                                $cover_data     = mangadex_api_get("/cover/$cover_id");
                                $cover_filename = $cover_data['data']['attributes']['fileName'] ?? '';
                            }
                        }
                        break;
                    }
                }
            }

            // Fallback
            if (!$has_cover_art || empty($cover_filename)) {
                $manga_detail = mangadex_api_get("/manga/$manga_id?includes[]=cover_art");
                if (!empty($manga_detail['data']['relationships'])) {
                    foreach ($manga_detail['data']['relationships'] as $rel) {
                        if ($rel['type'] === 'cover_art') {
                            $cover_id = $rel['id'] ?? '';
                            if ($cover_id) {
                                $cover_data     = mangadex_api_get("/cover/$cover_id");
                                $cover_filename = $cover_data['data']['attributes']['fileName'] ?? '';
                            }
                            break;
                        }
                    }
                }
            }

            if (empty($cover_filename)) {
                $cover_filename = 'default.jpg';
            }

            echo "<pre>✔️ Lưu manga: $title | Cover: $cover_filename</pre>";

            // === Lưu manga
            $wpdb->replace(
                $manga_table,
                [
                    'manga_id'   => $manga_id,
                    'title'      => $title,
                    'slug'       => $slug,
                    'status'     => $status,
                    'created_at' => $created,
                    'cover'      => $cover_filename,
                ]
            );

            // === Lưu Genre / Theme / Format ===
            if (!empty($manga['attributes']['tags'])) {
                foreach ($manga['attributes']['tags'] as $tag) {
                    $tag_name  = $tag['attributes']['name']['en'] ?? '';
                    $tag_group = $tag['attributes']['group'] ?? '';

                    if (empty($tag_name) || empty($tag_group)) continue;

                    switch ($tag_group) {
                        case 'genre':
                            $table    = $genre_table;
                            $map_table = $genre_map_table;
                            break;
                        case 'theme':
                            $table    = $theme_table;
                            $map_table = $theme_map_table;
                            break;
                        case 'format':
                            $table    = $format_table;
                            $map_table = $format_map_table;
                            break;
                        default:
                            continue 2; // Bỏ qua các group khác
                    }

                    // Tìm trong bảng
                    $uuid = $tag['id']; // Đây là UUID từ MangaDex

                    // Kiểm tra tag đã tồn tại theo name
                    $row = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM $table WHERE name = %s",
                        $tag_name
                    ));

                    if (!$row) {
                        $wpdb->insert($table, [
                            'name' => $tag_name,
                            'uuid' => $uuid
                        ]);
                        $tag_id = $wpdb->insert_id;
                    } else {
                        // Cập nhật uuid nếu chưa có hoặc khác
                        $wpdb->update($table, ['uuid' => $uuid], ['id' => $row->id]);
                        $tag_id = $row->id;
                    }

                }
            }

            // === Crawl chapters ===
            $chap_offset  = 0;
            $chap_per_page = 50;
            $max_chapters  = 50;

            while (true) {
                $chap_data = mangadex_api_get("/chapter?manga=$manga_id&limit=$chap_per_page&offset=$chap_offset&translatedLanguage[]=en&order[chapter]=asc");

                if (empty($chap_data['data'])) break;

                foreach ($chap_data['data'] as $chap) {
                    $chapter_id      = $chap['id'];
                    $chapter_number  = trim((string)($chap['attributes']['chapter'] ?? '0'));
                    $chapter_title   = $chap['attributes']['title'] ?? '';
                    $chapter_created = $chap['attributes']['createdAt'] ?? '';

                    $wpdb->replace(
                        $chapter_table,
                        [
                            'chapter_id'     => $chapter_id,
                            'manga_id'       => $manga_id,
                            'chapter_number' => $chapter_number,
                            'title'          => $chapter_title,
                            'created_at'     => $chapter_created,
                        ]
                    );
                    echo "<pre>   ├─ Lưu chapter: #$chapter_number</pre>";
                }

                $chap_offset += $chap_per_page;
                if ($chap_offset >= $max_chapters) break;

                flush();
                sleep(1);
            }

            flush();
            sleep(1);
        }

        $offset += $per_page;
        if ($offset >= 100) break; // Giới hạn test
    }


    
    echo "<pre>🎉 DONE</pre>";
    die;
}
