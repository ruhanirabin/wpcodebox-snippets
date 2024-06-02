<?php

/*
Plugin Name: Dynamic Date Replace
Description: Adds DATE display shortcode functionality to WordPress sites for displaying dynamic date information. It supports shortcodes for the current, previous, and next month names, as well as the current, previous, and next year in a numeric 4-digit format. Users can implement these shortcodes in various areas of their WordPress site including post titles, content, excerpts, and menu items. By using these shortcodes, the plugin dynamically updates the date information based on the current date, ensuring content remains relevant and up-to-date without manual adjustments.
Version: 3.1
Author: Ruhani Rabin
Date: Sunday, June 02, 2024
Compatible with: WordPress 6.4.x

you can use the following shortcodes:

[current_month]: Displays the current month name.
[previous_month]: Displays the previous month name.
[next_month]: Displays the next month name.
[current_year]: Displays the current year in numeric 4-digit format.
[previous_year]: Displays the previous year.
[next_year]: Displays the next year.

Examples of Using Shortcodes:
Current Month in a Post Title: "Events Happening in [current_month]"

Displays as "Events Happening in November" (if the current month is November)
Previous and Next Year in Content: "Our year-end sale of [previous_year] was a success, and we're looking forward to an even bigger event in [next_year]."

Displays as "Our year-end sale of 2022 was a success, and we're looking forward to an even bigger event in 2024."
Current Year in a Menu Item: "Annual Report [current_year]"

Appears as "Annual Report 2023" in the menu.
Next Month in an Excerpt: "Join our webinar next month: [next_month] Highlights."

Shows up as "Join our webinar next month: December Highlights" if it's currently November.

*/

// Function to add shortcode support for the current month name
function current_month_shortcode($atts) {
    $current_month = date_i18n('F');
    return $current_month;
}
add_shortcode('current_month', 'current_month_shortcode');

// Function to add shortcode support for the previous month name
function previous_month_shortcode($atts) {
    $previous_month = date_i18n('F', strtotime('-1 month'));
    return $previous_month;
}
add_shortcode('previous_month', 'previous_month_shortcode');

// Function to add shortcode support for the next month name
function next_month_shortcode($atts) {
    $next_month = date_i18n('F', strtotime('+1 month'));
    return $next_month;
}
add_shortcode('next_month', 'next_month_shortcode');

// Function to add shortcode support for the current year in numeric 4-digit format
function current_year_shortcode($atts) {
    $current_year = date('Y');
    return $current_year;
}
add_shortcode('current_year', 'current_year_shortcode');

// Function to add shortcode support for the previous year
function previous_year_shortcode($atts) {
    $previous_year = date('Y', strtotime('-1 year'));
    return $previous_year;
}
add_shortcode('previous_year', 'previous_year_shortcode');

// Function to add shortcode support for the next year
function next_year_shortcode($atts) {
    $next_year = date('Y', strtotime('+1 year'));
    return $next_year;
}
add_shortcode('next_year', 'next_year_shortcode');

// Function to enable shortcode processing for the_title
function enable_shortcode_in_the_title($title) {
    return do_shortcode($title);
}
add_filter('the_title', 'enable_shortcode_in_the_title');

// Function to enable shortcode processing for single_post_title
function enable_shortcode_in_single_post_title($title) {
    return do_shortcode($title);
}
add_filter('single_post_title', 'enable_shortcode_in_single_post_title');

// Function to enable shortcode processing for wp_title
function enable_shortcode_in_wp_title($title) {
    return do_shortcode($title);
}
add_filter('wp_title', 'enable_shortcode_in_wp_title');

// Function to enable shortcode processing for the_excerpt
function enable_shortcode_in_the_excerpt($excerpt) {
    return do_shortcode($excerpt);
}
add_filter('the_excerpt', 'enable_shortcode_in_the_excerpt');

// Function to enable shortcode processing for menu items
function enable_shortcode_in_menu_items($menu_item) {
    $menu_item = do_shortcode($menu_item);
    return $menu_item;
}
add_filter('wp_nav_menu_items', 'enable_shortcode_in_menu_items');