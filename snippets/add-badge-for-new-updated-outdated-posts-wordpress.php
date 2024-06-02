<?php 

// Revision 2.5
// Add this code to your theme's functions.php file
//
// Post badges are tiny labels that indicate the status of a post. In this case, we’ll add three types of badges:
//     NEW: For posts published within the last 30 days.
//     UPDATED: For posts updated within the previous 180 days.
//     OUTDATED: For posts that haven’t been updated in over three years.
//     How It Works
//     Add the Code to Your Theme: First, you’ll need to add a custom function to your theme’s functions.php file. This function checks the publication and modification dates of each post and adds the appropriate badge based on the defined thresholds.
//     Define the Thresholds: The code uses specific thresholds to determine which badge to display:
//     Posts published within the last 30 days get a “NEW” badge.
//     Posts updated within the last 180 days get an “UPDATED” badge.
//     Posts not updated in over three years get an “OUTDATED” badge.
//     Apply the Styles: The badges are styled using CSS to make them visually distinct and appealing. Each badge has a different color:
//     NEW: Green
//     UPDATED: Blue
//     OUTDATED: Red, with a distinctive style highlighting the post’s age.
//     Hide Outdated Badge from Search Engines: To ensure the “OUTDATED” badge doesn’t affect your SEO, it’s rendered using JavaScript, making it visible to users but hidden from search engines.
//     Your readers can easily see which posts are new, recently updated, or potentially outdated. This simple addition enhances user experience and keeps your content organized and easy to navigate.

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
