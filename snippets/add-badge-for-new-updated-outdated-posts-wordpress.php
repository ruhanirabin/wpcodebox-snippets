<?php 

// Revisio 2.5
// Add this code to your theme's functions.php file

function custom_post_badges($content) {
    if (is_single() && is_main_query() && get_post_type() == 'post') {
        $post_date = get_the_date('U');
        $updated_date = get_the_modified_date('U');
        $current_date = current_time('timestamp');

        $new_days = 30; // Number of days for "NEW" badge
        $updated_days = 180; // Number of days for "UPDATED" badge
        $outdated_years = 3; // Number of years for "OUTDATED" badge

        $new_threshold = strtotime("-{$new_days} days", $current_date);
        $updated_threshold = strtotime("-{$updated_days} days", $current_date);
        $outdated_threshold = strtotime("-{$outdated_years} years", $current_date);

        $badge = '';

        if ($post_date > $new_threshold) {
            $badge = '<span class="badge new-post">NEW</span>';
        } elseif ($updated_date > $updated_threshold) {
            $badge = '<span class="badge updated-post">UPDATED</span>';
        } elseif ($updated_date < $outdated_threshold) {
            $badge = '<div id="hidden-content"></div><script type="text/javascript">
                        document.getElementById("hidden-content").innerHTML = \'<div class="badge outdated-post">This post might be outdated</div>\';
                      </script>';
        }

        if ($badge) {
            $content = $badge . $content;
        }
    }

    return $content;
}
add_filter('the_content', 'custom_post_badges');

function custom_post_badges_styles() {
    echo '
    <style>
        .badge {
            display: inline-block;
            padding: 5px 10px;
            margin: 10px 0;
            font-weight: bold;
            color: #fff;
            border-radius: 5px;
            text-align: center;            
        }
        .new-post {
            background-color: #28a745;
        }
        .updated-post {
            background-color: #17a2b8;
        }
        .outdated-post {
            display: block;
            width: 90%;
            margin: 10px auto;
            text-align: center;
            background-color: #dc3545;
            color: #fff;
            padding: 10px;
            font-weight: bold;
        }
    </style>
    ';
}
add_action('wp_head', 'custom_post_badges_styles');
