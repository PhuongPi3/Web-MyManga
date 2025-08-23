<?php
/**
 * Shortcode: [manga_search_filter]
 * Combined filter: Genre + Theme + Format + Status + Sort.
 * Searches MangaDex API if keyword is provided.
 */

if (!function_exists('mangadex_api_get')) {
    function mangadex_api_get($endpoint) {
        $response = wp_remote_get("https://api.mangadex.org" . $endpoint);
        if (is_wp_error($response)) return [];
        return json_decode(wp_remote_retrieve_body($response), true);
    }
}

function shortcode_manga_search_filter() {
    global $wpdb;

    $genre_table = $wpdb->prefix . 'manga_genre';
    $genre_map_table = $wpdb->prefix . 'manga_genre_map';
    $theme_table = $wpdb->prefix . 'manga_theme';
    $theme_map_table = $wpdb->prefix . 'manga_theme_map';
    $format_table = $wpdb->prefix . 'manga_format';
    $format_map_table = $wpdb->prefix . 'manga_format_map';
    $manga_table = $wpdb->prefix . 'manga_list';

    $paged = get_query_var('paged') ? get_query_var('paged') : 1;

    $keyword  = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
    $tag      = isset($_GET['tag']) ? trim($_GET['tag']) : '';
    $sort     = isset($_GET['sort']) ? trim($_GET['sort']) : 'latest';
    $status   = isset($_GET['status']) ? trim($_GET['status']) : '';

    $limit    = 20;
    $offset   = ($paged - 1) * $limit;

    $output = '<style>
        .manga-filter-form { max-width:700px;margin:20px auto;border:1px solid #ccc;padding:15px;border-radius:8px;background:#f9f9f9; }
        .manga-filter-form .form-row { display:flex;flex-wrap:wrap;gap:10px;align-items:center; }
        .manga-filter-form input, .manga-filter-form select { padding:8px;border-radius:4px;border:1px solid #ccc;flex:1;min-width:150px; }
        .manga-filter-form button, .manga-filter-form a.button { background-color:orange;color:white;border:none;padding:8px 12px;border-radius:4px;cursor:pointer;text-decoration:none; }
        .manga-filter-form .advanced { margin-top:10px; }
        .manga-filter-form .hidden { display:none; }
        .manga-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:20px; }
        .manga-card a { text-decoration:none;color:inherit; }
        .manga-cover { width:100%;padding-top:140%;background-size:cover;background-position:center;border-radius:8px; }
        .pagination a, .pagination strong { margin:0 4px;text-decoration:none; }
        .manga-chapters { background: #111; padding: 8px; color: #ccc; font-size: 12px; line-height: 1.4; }
        .chapter-item { display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.1); padding: 4px 0; }
        .chapter-item:first-child { border-top: none; }
        .chapter-item a:hover { text-decoration: underline; }
        .chapter-time { opacity: 0.8; font-size: 11px; }
        .manga-chapters .chapter-item a { font-size: 12px; color: #ccc; text-decoration: none; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle; }
        .manga-title {
        font-size: 14px; color: #fff; margin: 0; line-height: 1.2; font-weight: 600;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
        overflow: hidden; text-overflow: ellipsis; transition: 0.3s;
        }

        .manga-card:hover .manga-title {
        -webkit-line-clamp: unset; max-height: none; overflow: visible; white-space: normal;
        }

    </style>';

    $output .= '<form method="get" class="manga-filter-form" id="mangaFilterForm">
        <div class="form-row">
            <input type="text" name="keyword" placeholder="Enter keyword" value="'.esc_attr($keyword).'" />
            <button type="button" onclick="document.getElementById(\'advanced-filters\').classList.toggle(\'hidden\')">Show Filters</button>
            <button type="submit">🔍 Search</button>
            <a class="button" href="'.esc_url(remove_query_arg(['keyword','tag','sort','status','paged'])).'">🔄 Reset</a>
        </div>

        <div id="advanced-filters" class="advanced '.($tag || $sort !== 'latest' || !empty($status) ? '' : 'hidden').'">

            <select name="status">
                <option value="">-- Status --</option>
                <option value="completed" '.selected($status, 'completed', false).'>Completed</option>
                <option value="ongoing" '.selected($status, 'ongoing', false).'>Ongoing</option>
            </select>

            <select name="tag">
                <option value="0">-- Genre / Theme / Format --</option>';

    $genres = $wpdb->get_results("SELECT * FROM $genre_table ORDER BY name ASC");
    foreach ($genres as $g) {
        $sel = ($tag == 'genre_'.$g->id) ? 'selected' : '';
        $output .= "<option value='genre_{$g->id}' $sel>🌟 {$g->name}</option>";
    }

    $themes = $wpdb->get_results("SELECT * FROM $theme_table ORDER BY name ASC");
    foreach ($themes as $t) {
        $sel = ($tag == 'theme_'.$t->id) ? 'selected' : '';
        $output .= "<option value='theme_{$t->id}' $sel>🎭 {$t->name}</option>";
    }

    $formats = $wpdb->get_results("SELECT * FROM $format_table ORDER BY name ASC");
    foreach ($formats as $f) {
        $sel = ($tag == 'format_'.$f->id) ? 'selected' : '';
        $output .= "<option value='format_{$f->id}' $sel>📘 {$f->name}</option>";
    }

    $output .= '</select>

            <select name="sort">
                <option value="latest" '.selected($sort,'latest',false).'>Latest</option>
                <option value="az" '.selected($sort,'az',false).'>A-Z</option>
            </select>
        </div>
    </form>';

    if (!empty($keyword)) {
        $url = "/manga?limit=$limit&offset=$offset&title=" . urlencode($keyword) . "&availableTranslatedLanguage[]=en&includes[]=cover_art";
        $data = mangadex_api_get($url);

        if (empty($data['data'])) {
            $output .= '<p style="text-align:center;">❌ Không tìm thấy truyện trên MangaDex.</p>';
            return $output;
        }

        $output .= '<div class="manga-grid">';
        foreach ($data['data'] as $manga) {
            $id = esc_attr($manga['id']);
            $title = esc_html($manga['attributes']['title']['en'] ?? '(No title)');
            $cover_rel = '';
            foreach ($manga['relationships'] as $rel) {
                if ($rel['type'] === 'cover_art' && !empty($rel['attributes']['fileName'])) {
                    $cover_rel = $rel['attributes']['fileName'];
                    break;
                }
            }
            $cover_url = $cover_rel ? "https://mangadex-proxy.deon3356.workers.dev/?manga_id=$id&filename=$cover_rel"
                                    : site_url("/wp-content/uploads/2025/07/default-cover.jpg");

            $output .= '<div class="manga-card">
                <a href="/manga-detail/?id=' . $id . '">
                    <div class="manga-cover" style="background-image:url(' . esc_url($cover_url) . ');"></div>
                    <div class="manga-info"><h3 class="manga-title">' . $title . '</h3></div>
                </a>
            </div>';
        }
        $output .= '</div>';
        return $output; // ⛔ STOP: KHÔNG FILTER LOCAL nếu có keyword
    }

    // Filter local DB
    $where = '1=1';
    $where_params = [];

    if (!empty($tag) && preg_match('/^(genre|theme|format)_(\d+)$/', $tag, $m)) {
        $type = $m[1];
        $id   = intval($m[2]);

       switch ($type) {
        case 'genre':
            $where .= " AND manga_id IN (SELECT manga_id FROM $genre_map_table WHERE genre_id = %d)";break;
        case 'theme':
            $where .= " AND manga_id IN (SELECT manga_id FROM $theme_map_table WHERE tag_id = %d)";break;
        case 'format':
            $where .= " AND manga_id IN (SELECT manga_id FROM $format_map_table WHERE tag_id = %d)";break;
        }

        $where_params[] = $id;
    }

    if (!empty($status)) {
        $where .= " AND status = %s";
        $where_params[] = $status;
    }

    $order_by = ($sort === 'az') ? 'title ASC' : 'created_at DESC';
    $sql = "SELECT * FROM $manga_table WHERE $where ORDER BY $order_by LIMIT %d OFFSET %d";
    $query_params = array_merge($where_params, [$limit, $offset]);
    $results = $wpdb->get_results($wpdb->prepare($sql, $query_params));

    if (empty($results)) {
        $output .= '<p style="text-align:center;">❌ Không tìm thấy truyện.</p>';
        return $output;
    }

    $output .= '<div class="manga-grid">';
    foreach ($results as $manga) {
        $manga_id = esc_attr($manga->manga_id);
        $title = esc_html($manga->title);
        $cover = trim($manga->cover);
        $cover_url = (!empty($cover) && strtolower($cover) !== 'default.jpg')
            ? "https://mangadex-proxy.deon3356.workers.dev/?manga_id=$manga_id&filename=$cover"
            : site_url("/wp-content/uploads/2025/07/default-cover.jpg");

        $chapters_table = $wpdb->prefix . 'manga_chapter';
        $chapter_sql = $wpdb->prepare(
            "SELECT chapter_id, chapter_number, title, created_at 
             FROM $chapters_table WHERE manga_id = %d 
             ORDER BY created_at DESC LIMIT 3",
            $manga_id
        );
        $chapters = $wpdb->get_results($chapter_sql);

        $output .= "<div class='manga-card'>
            <a href='/manga-detail/?id=$manga_id'>
                <div class='manga-cover' style='background-image:url(".esc_url($cover_url).");'>
                    <div class='manga-info'><h3 class='manga-title'>$title</h3></div>
                </div>
            </a>
            <div class='manga-chapters'>";
        if (!empty($chapters)) {
            foreach ($chapters as $c) {
                $c_title = trim($c->title) ?: 'Chapter ' . intval($c->chapter_number);
                $link = site_url("/reader/?chapter=" . $c->chapter_id);
                $ago = human_time_diff(strtotime($c->created_at), current_time('timestamp')) . ' trước';
                $output .= "<div class='chapter-item'>
                    <a href='".esc_url($link)."'>".esc_html($c_title)."</a>
                    <span class='chapter-time'>$ago</span>
                </div>";
            }
        } else {
            $output .= "<div class='chapter-item'>Chưa có chương nào</div>";
        }
        $output .= "</div></div>";
    }
    $output .= '</div>';

  // Pagination
$sql_total = "SELECT COUNT(*) FROM $manga_table WHERE $where";
$total = $wpdb->get_var($wpdb->prepare($sql_total, $where_params));
$total_pages = max(1, ceil($total / $limit));

if ($total_pages > 1) {
    $output .= '<div class="pagination">';

    $base_url = trailingslashit(get_permalink());
    $args = [];
    if ($tag) $args['tag'] = $tag;
    if ($sort) $args['sort'] = $sort;
    if ($status) $args['status'] = $status;

    $range = 2;
    $pages_to_show = [];

    // Always show first and last
    $pages_to_show[] = 1;
    $pages_to_show[] = $total_pages;

    // Add window around current page
    for ($i = $paged - $range; $i <= $paged + $range; $i++) {
        if ($i > 1 && $i < $total_pages) {
            $pages_to_show[] = $i;
        }
    }

    // Remove duplicates and sort
    $pages_to_show = array_unique($pages_to_show);
    sort($pages_to_show);

    // Print with dots
    $last_page = 0;
    foreach ($pages_to_show as $page_num) {
        if ($last_page && $page_num - $last_page > 1) {
            $output .= "<span class='dots'>...</span>";
        }

        $url_page = ($page_num == 1) ? $base_url : $base_url . 'page/' . $page_num . '/';
        $url_page = esc_url(add_query_arg($args, $url_page));

        if ($page_num == $paged) {
            $output .= "<strong>$page_num</strong>";
        } else {
            $output .= "<a href='$url_page'>$page_num</a>";
        }

        $last_page = $page_num;
    }

    $output .= '</div>';
}


    $output .= '<script>
        document.getElementById("mangaFilterForm").addEventListener("submit", function(e) {
            let urlParams = new URLSearchParams(new FormData(this));
            urlParams.delete("paged");
            let baseUrl = "'.esc_js(trailingslashit(get_permalink())).'";
            let queryString = urlParams.toString();
            this.action = baseUrl + (queryString ? "?" + queryString : "");
        });
    </script>';

    return $output;
}
add_shortcode('manga_search_filter', 'shortcode_manga_search_filter');
