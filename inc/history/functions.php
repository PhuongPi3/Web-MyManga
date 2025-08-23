<?php
function mangareader_save_read_history($manga_id, $chapter_id) {
    global $wpdb;

    $table = $wpdb->prefix . 'manga_read_history';
    $now = current_time('mysql');

    $user_id = get_current_user_id();
    $guest_token = !$user_id ? mangareader_get_guest_token() : null;

    // ✅ Xử lý riêng biệt khách và người dùng
    if ($user_id) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND manga_id = %s",
            $user_id, $manga_id
        ));
    } elseif ($guest_token) {
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE guest_token = %s AND manga_id = %s",
            $guest_token, $manga_id
        ));
    } else {
        return; // Không có token → bỏ qua
    }

    // ✅ Nếu đã có -> update
    if ($existing) {
        $wpdb->update($table, [
            'chapter_id' => $chapter_id,
            'updated_at' => $now
        ], ['id' => $existing->id]);
    } else {
        // ✅ Nếu chưa có -> insert
        $wpdb->insert($table, [
            'user_id' => $user_id ?: null,
            'guest_token' => $guest_token,
            'manga_id' => $manga_id,
            'chapter_id' => $chapter_id,
            'updated_at' => $now
        ]);

        // ✅ Ghi log nếu lỗi
        if ($wpdb->last_error) {
             error_log("❌ Insert history error: " . $wpdb->last_error);
        } else {
            error_log("✅ Inserted guest history: guest_token=$guest_token");
        }
    }
    error_log("📥 Save history called: manga_id=$manga_id, chapter_id=$chapter_id");
}

function mangareader_get_guest_token() {
    return $_COOKIE['manga_guest_token'] ?? null;
}

function mangareader_delete_old_guest_history() {
    global $wpdb;
    $table = $wpdb->prefix . 'manga_read_history';
    $threshold = gmdate('Y-m-d H:i:s', strtotime('-14 days'));
    $wpdb->query(
        $wpdb->prepare("DELETE FROM $table WHERE guest_token IS NOT NULL AND updated_at < %s", $threshold)
    );
}
