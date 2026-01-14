<?php
/**
 * Plugin Name: RR Category Images
 * Plugin URI: https://www.ruhanirabin.com
 * Description: This adds the ability to associate images with categories in WordPress. This can be used as custom plugin, functions.php or code managers like WPCodeBox.
 * Version: 1.5
 * Date: Nov 21, 2023
 * Author: Ruhani Rabin
 * Author URI: https://www.ruhanirabin.com
 * Works with: Bricks Builder "term" meta field "categtory-image"
 * 
 * WPCodeBox Configuration:
 * 
 * Title: Category Image Support
 * How to run the snippet: Always (On Page Load)
 * Hook/Priority: Root
 * Snippet Order: 10
 * Where to run the snippet: Everywhere
 * 
 * How to use:
 * Once snippet activated or added to functions.php or a custom plugin. You can go to Posts > Categories
 * Edit or Create category screen will have a image field with image upload button. Use this to assign images to categories.
 * 
 * ** KEEP THE CREDITS in this CODE **
 */

/**
 * Adds a new column to the category list table to display the image.
 */
function rradd_add_image_column($columns) {
    $new_columns = array_slice($columns, 0, 1, true) +
                   array('image' => __('Image', 'rradd')) +
                   array_slice($columns, 1, NULL, true);
    return $new_columns;
}
add_filter('manage_edit-category_columns', 'rradd_add_image_column');

/**
 * Renders the image in the custom column on the category list table.
 */
function rradd_category_image_column_content($content, $column_name, $term_id) {
    if ('image' === $column_name) {
        $image_url = get_term_meta($term_id, 'category-image', true);
        $image_url = $image_url ?: 'https://img.sharemyimage.com/2023/12/21/default_not_set.jpeg'; // Replace with your default image path
        return '<img src="' . esc_url($image_url) . '" alt="' . esc_attr(get_cat_name($term_id)) . '" style="width: 50px; height: auto;">';
    }
    return $content;
}
add_filter('manage_category_custom_column', 'rradd_category_image_column_content', 10, 3);

/**
 * Displays the assigned category image or a placeholder on the edit/add category form.
 */
function rradd_show_category_image($term) {
    $image_url = get_term_meta($term->term_id, 'category-image', true);
    $image_url = $image_url ?: 'https://img.sharemyimage.com/2023/12/21/default_not_set.jpeg'; // Replace with your default image path
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label><?php _e('Current Image', 'rradd'); ?></label></th>
        <td>
            <img src="<?php echo esc_url($image_url); ?>" alt="<?php esc_attr_e('Category Image', 'rradd'); ?>" style="width: 100px; height: auto;">
        </td>
    </tr>
    <?php
}
add_action('category_edit_form_fields', 'rradd_show_category_image', 10, 1);



/**
 * Adds an image field to the add new category form.
 */
function rradd_add_category_image_field() {
    ?>
    <div class="form-field">
        <label for="category-image"><?php _e('Image', 'rradd'); ?></label>
        <input type="text" name="category-image" id="category-image" value="" />
        <button type="button" class="button" id="category-image-upload-button"><?php _e('Select Image', 'rradd'); ?></button>
        <p class="description"><?php _e('Choose an image for the category. Save the changes to see the preview.', 'rradd'); ?></p>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $('#category-image-upload-button').click(function(e) {
                e.preventDefault();
                var imageUploader = wp.media({
                    'title': 'Choose Image',
                    'button': {
                        'text': 'Use Image'
                    },
                    'multiple': false
                }).on('select', function() {
                    var attachment = imageUploader.state().get('selection').first().toJSON();
                    $('#category-image').val(attachment.url);
                }).open();
            });
        });
    </script>
    <?php
}
add_action('category_add_form_fields', 'rradd_add_category_image_field');

/**
 * Enqueues the WordPress media uploader script in the admin area.
 */
function rradd_enqueue_media_uploader($hook) {
    // Only enqueue on category add/edit pages
    if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
        return;
    }

    if (!did_action('wp_enqueue_media')) {
        wp_enqueue_media();
    }
}

add_action('admin_enqueue_scripts', 'rradd_enqueue_media_uploader');

/**
 * Adds an image field to the edit category form.
 */
function rradd_edit_category_image_field($term) {
    $image_url = get_term_meta($term->term_id, 'category-image', true);
    ?>
    <tr class="form-field">
        <th scope="row" valign="top"><label for="category-image"><?php _e('Image', 'rradd'); ?></label></th>
        <td>
            <input type="text" name="category-image" id="category-image" value="<?php echo esc_attr($image_url); ?>" />
            <button type="button" class="button" id="category-image-upload-button"><?php _e('Select Image', 'rradd'); ?></button>
            <p class="description"><?php _e('Choose an image for the category. Save the changes to see the preview.', 'rradd'); ?></p>
        </td>
    </tr>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#category-image-upload-button').click(function(e) {
                e.preventDefault();
                var imageUploader = wp.media({
                    'title': 'Choose Image',
                    'button': {
                        'text': 'Use Image'
                    },
                    'multiple': false
                }).on('select', function() {
                    var attachment = imageUploader.state().get('selection').first().toJSON();
                    $('#category-image').val(attachment.url);
                }).open();
            });
        });
    </script>
    <?php
}
add_action('category_edit_form_fields', 'rradd_edit_category_image_field');

/**
 * Saves the category image when a new category is created or edited.
 */
function rradd_save_category_image($term_id) {
    if (isset($_POST['category-image'])) {
        update_term_meta($term_id, 'category-image', esc_url($_POST['category-image']));
    }
}
add_action('created_category', 'rradd_save_category_image');
add_action('edited_category', 'rradd_save_category_image');

/**
 * Retrieves the category image URL for a given term ID.
 */
function rradd_get_category_image($term_id) {
    $image_url = get_term_meta($term_id, 'category-image', true);
    if (!empty($image_url)) {
        return '<img src="' . esc_url($image_url) . '" alt="' . get_cat_name($term_id) . '" />';
    }
    return '';
}
