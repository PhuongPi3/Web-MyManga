<?php
add_action('admin_menu', function() {
    add_menu_page('MangaDex Manager', 'MangaDex', 'manage_options', 'mangadex-manager', 'mymanga_admin_page');
});

function mymanga_admin_page() {
    echo '<h2>MangaDex Admin</h2>';
    echo '<p>Quản lý log API, metadata, người dùng tại đây.</p>';
}

