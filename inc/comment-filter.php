<?php
add_filter( 'pre_comment_approved', 'mymanga_filter_badwords', 99, 2 );
function mymanga_filter_badwords($approved , $commentdata) {
    $badwords = ['đm', 'cc', 'fuck'];
    foreach ($badwords as $word) {
        if (stripos($commentdata['comment_content'], $word) !== false) {
            return 'spam';
        }
    }
    return $approved;
}
