<?php
if (!defined('ABSPATH')) exit;

function mangadex_rating_ui($atts) {
    global $wpdb;

    $manga_id = esc_attr($atts['id'] ?? '');
    if (!$manga_id) return '';

    $table = $wpdb->prefix . 'manga_rating';
    $user_id = get_current_user_id();

    $stats = $wpdb->get_results(
        $wpdb->prepare("
            SELECT rating, COUNT(*) as count 
            FROM $table 
            WHERE manga_id = %s 
            GROUP BY rating
        ", $manga_id)
    );

    $total_votes = 0;
    $total_score = 0;
    $breakdown = array_fill(1, 5, 0);

    foreach ($stats as $row) {
        $breakdown[(int)$row->rating] = (int)$row->count;
        $total_votes += (int)$row->count;
        $total_score += (int)$row->rating * (int)$row->count;
    }

    $avg_rating = $total_votes ? round($total_score / $total_votes, 1) : 0;

    $user_rating = $user_id ? (int)$wpdb->get_var($wpdb->prepare("
        SELECT rating FROM $table WHERE manga_id = %s AND user_id = %d
    ", $manga_id, $user_id)) : 0;

    ob_start(); ?>
    <style>
    .rating-ui { display: flex; flex-direction: column; gap: 8px; padding: 8px 0; }
    .stars { display: flex; gap: 5px; font-size: 28px; }
    .star { cursor: pointer; color: #aaa; transition: color 0.2s; }
    .star.selected, .star.hovered { color: #facc15; }
    .rating-summary {
        font-size: 14px; color: #ccc;
        position: relative;
        display: inline-block;
        cursor: help;
    }
    .rating-breakdown {
        display: none;
        position: absolute;
        top: 120%; left: 0;
        background: #222; color: #fff;
        border: 1px solid #444;
        padding: 10px; font-size: 13px;
        z-index: 10; white-space: nowrap;
        border-radius: 8px;
    }
    .rating-summary:hover .rating-breakdown {
        display: block;
    }
    </style>

    <div class="rating-ui" data-manga-id="<?= esc_attr($manga_id) ?>">
       <div class="stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <?php
            $is_selected = $user_id
                ? ($i <= $user_rating)
                : ($i <= floor($avg_rating));
            ?>
            <span class="star <?= $is_selected ? 'selected' : '' ?>" data-score="<?= $i ?>">★</span>
        <?php endfor; ?>
        </div>

        <div class="rating-summary">
            <?= $avg_rating ?>/5 (<?= $total_votes ?> votes)
            <div class="rating-breakdown">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <?= $i ?>★: <?= $breakdown[$i] ?> vote(s)<br>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <script>
    const isLoggedIn = <?= is_user_logged_in() ? 'true' : 'false' ?>;
    document.querySelectorAll('.rating-ui').forEach(wrapper => {
        const stars = wrapper.querySelectorAll('.star');
        const mangaId = wrapper.dataset.mangaId;
        let selectedRating = <?= $user_rating ?>;

        stars.forEach(star => {
            const score = parseInt(star.dataset.score);

            star.addEventListener('mouseover', () => {
                stars.forEach(s => s.classList.toggle('hovered', parseInt(s.dataset.score) <= score));
            });
            star.addEventListener('mouseout', () => {
                stars.forEach(s => s.classList.remove('hovered'));
            });
            star.addEventListener('click', () => {
                if (!isLoggedIn) {
                    alert("You need to log in to rate.");
                    return;
                }

                selectedRating = score;

                // Update UI
                stars.forEach(s => {
                    s.classList.toggle('selected', parseInt(s.dataset.score) <= selectedRating);
                });

                // Send AJAX
                fetch("<?= admin_url('admin-ajax.php') ?>", {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'mangadex_submit_rating',
                        manga_id: mangaId,
                        rating: selectedRating
                    })
                }).then(res => res.json()).then(data => {
                    alert(data.data || data.message);
                    location.reload(); // Reload to update stats
                });
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('manga_rating', 'mangadex_rating_ui');

function fetch_manga_title_from_api($manga_id) {
    $url = "https://api.mangadex.org/manga/{$manga_id}";
    $response = wp_remote_get($url);
    if (is_wp_error($response)) return 'Unknown';

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($data['data'])) return 'Unknown';

    $attributes = $data['data']['attributes'];
    return $attributes['title']['en'] ?? array_values($attributes['title'])[0] ?? 'No Title';
}
