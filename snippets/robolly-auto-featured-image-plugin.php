<?php
/*
Plugin Name: AFI - Robolly
Description: Automatically generates and assigns dynamic featured images using Robolly API
Version: 1.2
Release: Production
Author: Ruhani Rabin
URL: https://www.ruhanirabin.com

Licence: MIT

Copyright 2026 Ruhani Rabin https://www.ruhanirabin.com

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in 
the Software without restriction, including without limitation the rights to 
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to 
do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

CHANGELOG:
Version 1.2 (2026-01-15)
- Added support for extracting first image from post content (per post-type control)
- Added minimum image width validation (600px default, configurable per post-type)
- Added image parameter name configuration for Robolly templates (per post-type)
- Added automatic image dimension detection
- Added support for sending images to Robolly API via URL parameter
- Enhanced error logging for image extraction process
- Added fallback behavior when no suitable image is found
- Added version management and automatic settings migration
- Per post-type configuration for image extraction feature
- Added manual save/update support with duplicate prevention
- Plugin now works on save/update events, not just publish
- Smart tracking prevents multiple generations for same post
- Auto-reset generation flag when featured image is manually deleted
- Allow regeneration by simply deleting featured image and saving again

Version 1.1
- Added image metadata generation settings
- Added support for custom post types
- Improved error handling and retry logic

Version 1.0
- Initial release
*/

if (!defined('ABSPATH')) exit;

class AFI_Robolly_Generator {
    private $api_base_url = 'https://api.robolly.com/templates/';
    private $max_attempts = 3;
    private $plugin_options_key = 'afi_robolly_settings';
    private $plugin_version = '1.2';

    public function __construct() {
        add_action('save_post', array($this, 'handle_post_save'), 10, 1);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // MODIFICATION 16: Reset generation flag when featured image is deleted
        add_action('delete_post_meta', array($this, 'handle_thumbnail_deletion'), 10, 4);
        
        // MODIFICATION 17: Add manual regeneration button in post editor
        add_action('post_submitbox_misc_actions', array($this, 'add_regenerate_button'));
        add_action('admin_post_afi_regenerate_featured_image', array($this, 'handle_manual_regeneration'));
        add_action('admin_notices', array($this, 'show_admin_notices'));
        
        // Add meta box as fallback if sidebar button doesn't show
        add_action('add_meta_boxes', array($this, 'add_regenerate_meta_box'));
        
        // Handle initial installation or upgrade
        $this->maybe_upgrade();
    }

    /**
     * Add manual regeneration button in post editor sidebar
     */
    public function add_regenerate_button() {
        global $post;
        
        if (!$post) {
            return;
        }
        
        // Check if this post type is supported
        if (!$this->is_post_type_supported($post->post_type)) {
            return;
        }
        
        $already_generated = get_post_meta($post->ID, '_afi_robolly_generated', true);
        $generated_time = get_post_meta($post->ID, '_afi_robolly_generated_time', true);
        $has_thumbnail = has_post_thumbnail($post->ID);
        ?>
        <div class="misc-pub-section afi-robolly-regenerate" style="border-top: 1px solid #ddd; padding: 10px 12px;">
            <span class="dashicons dashicons-format-image" style="color: #2271b1;"></span>
            <strong>AFI Robolly:</strong><br>
            <?php if ($already_generated): ?>
                <span style="color: #46b450;">✓ Auto-generated</span>
                <?php if ($generated_time): ?>
                    <br><small style="color: #666;"><?php echo esc_html(human_time_diff(strtotime($generated_time), current_time('timestamp'))); ?> ago</small>
                <?php endif; ?>
            <?php else: ?>
                <span style="color: #999;">Not yet generated</span><br>
                <small style="color: #666;">Save to auto-generate</small>
            <?php endif; ?>
            
            <?php if ($has_thumbnail || $already_generated): ?>
                <br><br>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=afi_regenerate_featured_image&post_id=' . $post->ID), 'afi_regenerate_' . $post->ID)); ?>" 
                   class="button button-small"
                   onclick="return confirm('<?php echo $has_thumbnail ? 'This will delete the current featured image and generate a new one.' : 'This will reset the generation flag and create a new featured image.'; ?> Continue?');">
                    🔄 <?php echo $has_thumbnail ? 'Regenerate' : 'Reset & Generate'; ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Add meta box as fallback if sidebar doesn't work
     */
    public function add_regenerate_meta_box() {
        $post_types = get_post_types(['public' => true], 'names');
        
        foreach ($post_types as $post_type) {
            if ($this->is_post_type_supported($post_type)) {
                add_meta_box(
                    'afi_robolly_regenerate',
                    'AFI Robolly - Featured Image',
                    array($this, 'render_regenerate_meta_box'),
                    $post_type,
                    'side',
                    'low'
                );
            }
        }
    }

    /**
     * Render the regenerate meta box
     */
    public function render_regenerate_meta_box($post) {
        $already_generated = get_post_meta($post->ID, '_afi_robolly_generated', true);
        $generated_time = get_post_meta($post->ID, '_afi_robolly_generated_time', true);
        $has_thumbnail = has_post_thumbnail($post->ID);
        ?>
        <div class="afi-robolly-meta-box">
            <p>
                <strong>Status:</strong>
                <?php if ($already_generated): ?>
                    <span style="color: #46b450;">✓ Auto-generated</span>
                    <?php if ($generated_time): ?>
                        <br><small><?php echo esc_html(human_time_diff(strtotime($generated_time), current_time('timestamp'))); ?> ago</small>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="color: #999;">Not yet generated</span>
                <?php endif; ?>
            </p>
            
            <?php if ($has_thumbnail || $already_generated): ?>
                <p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=afi_regenerate_featured_image&post_id=' . $post->ID), 'afi_regenerate_' . $post->ID)); ?>" 
                       class="button button-primary button-large"
                       onclick="return confirm('<?php echo $has_thumbnail ? 'This will delete the current featured image and generate a new one.' : 'This will reset the generation flag and create a new featured image.'; ?> Continue?');">
                        🔄 <?php echo $has_thumbnail ? 'Force Regenerate' : 'Reset & Generate'; ?>
                    </a>
                </p>
                <p class="description">
                    <?php if ($has_thumbnail): ?>
                        Click to delete current image and generate a new one with the latest template.
                    <?php else: ?>
                        The generation flag is set but no image exists. Click to reset and regenerate.
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <p class="description">
                    Save this post to automatically generate a featured image.
                </p>
            <?php endif; ?>
        </div>
        <style>
            .afi-robolly-meta-box p { margin: 10px 0; }
        </style>
        <?php
    }

    /**
     * Show admin notices after regeneration
     */
    public function show_admin_notices() {
        if (isset($_GET['afi_regenerated']) && $_GET['afi_regenerated'] == '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>AFI Robolly:</strong> Featured image regenerated successfully!</p>
            </div>
            <?php
        }
    }

    /**
     * Handle manual regeneration request
     */
    public function handle_manual_regeneration() {
        // Verify nonce and permissions
        if (!isset($_GET['post_id']) || !isset($_GET['_wpnonce'])) {
            wp_die('Invalid request');
        }
        
        $post_id = intval($_GET['post_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'afi_regenerate_' . $post_id)) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('You do not have permission to edit this post');
        }
        
        // Delete current featured image and reset flags
        delete_post_thumbnail($post_id);
        delete_post_meta($post_id, '_afi_robolly_generated');
        delete_post_meta($post_id, '_afi_robolly_generated_time');
        
        // Trigger regeneration by calling the handler directly
        $this->handle_post_save($post_id);
        
        // Redirect back to post editor
        wp_redirect(admin_url('post.php?action=edit&post=' . $post_id . '&afi_regenerated=1'));
        exit;
    }

    /**
     * Reset generation flag when featured image (_thumbnail_id) is deleted
     * This allows regeneration after manual deletion
     */
    public function handle_thumbnail_deletion($meta_ids, $post_id, $meta_key, $meta_value) {
        if ($meta_key === '_thumbnail_id') {
            delete_post_meta($post_id, '_afi_robolly_generated');
            delete_post_meta($post_id, '_afi_robolly_generated_time');
            error_log('AFI Robolly: Featured image deleted for post ' . $post_id . ', generation flag reset');
        }
    }

    /**
     * Check if plugin needs to be upgraded and migrate settings
     */
    private function maybe_upgrade() {
        $saved_version = get_option('afi_robolly_version', '0');
        
        // First time installation
        if (false === get_option($this->plugin_options_key)) {
            add_option($this->plugin_options_key, $this->get_default_settings());
            update_option('afi_robolly_version', $this->plugin_version);
            error_log('AFI Robolly: Fresh installation completed - v' . $this->plugin_version);
            return;
        }
        
        // Upgrade from older version
        if (version_compare($saved_version, $this->plugin_version, '<')) {
            $this->upgrade_settings($saved_version);
            update_option('afi_robolly_version', $this->plugin_version);
            error_log('AFI Robolly: Upgraded from v' . $saved_version . ' to v' . $this->plugin_version);
        }
    }

    /**
     * Upgrade settings from older versions
     */
    private function upgrade_settings($from_version) {
        $options = get_option($this->plugin_options_key, array());
        $defaults = $this->get_default_settings();
        $updated = false;
        
        // Add new settings that don't exist (merge with defaults)
        foreach ($defaults as $key => $value) {
            if (!isset($options[$key])) {
                $options[$key] = $value;
                $updated = true;
            }
        }
        
        if ($updated) {
            update_option($this->plugin_options_key, $options);
            error_log('AFI Robolly: Settings upgraded successfully');
        }
    }

    public function add_admin_menu() {
        add_options_page(
            'AFI Robolly Settings',
            'AFI Robolly',
            'manage_options',
            'afi-robolly-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'afi_robolly_settings',
            $this->plugin_options_key,
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'afi_api_section',
            'API Settings',
            array($this, 'api_section_callback'),
            'afi-robolly-settings'
        );

        add_settings_field(
            'afi_api_key',
            'Robolly API Key',
            array($this, 'api_key_field_callback'),
            'afi-robolly-settings',
            'afi_api_section'
        );

        // Image metadata settings section
        add_settings_section(
            'afi_metadata_section',
            'Image Metadata Settings',
            array($this, 'metadata_section_callback'),
            'afi-robolly-settings'
        );

        add_settings_field(
            'afi_generate_alt',
            'Generate ALT Text',
            array($this, 'generate_alt_field_callback'),
            'afi-robolly-settings',
            'afi_metadata_section'
        );

        add_settings_field(
            'afi_generate_title',
            'Generate Image Title',
            array($this, 'generate_title_field_callback'),
            'afi-robolly-settings',
            'afi_metadata_section'
        );

        add_settings_field(
            'afi_generate_description',
            'Generate Image Description',
            array($this, 'generate_description_field_callback'),
            'afi-robolly-settings',
            'afi_metadata_section'
        );

        add_settings_section(
            'afi_main_section',
            'Post Type Settings',
            array($this, 'section_callback'),
            'afi-robolly-settings'
        );

        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            add_settings_field(
                'afi_' . $post_type->name,
                $post_type->labels->singular_name,
                array($this, 'post_type_field_callback'),
                'afi-robolly-settings',
                'afi_main_section',
                array('post_type' => $post_type->name)
            );
        }
    }

    public function api_section_callback() {
        echo '<p>Enter your Robolly API Key. This is required for the plugin to function.</p>';
    }

    public function api_key_field_callback() {
        $options = get_option($this->plugin_options_key);
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        ?>
        <input type="text" 
               name="<?php echo esc_attr($this->plugin_options_key); ?>[api_key]" 
               value="<?php echo esc_attr($api_key); ?>"
               class="regular-text"
               required>
        <?php
    }

    public function metadata_section_callback() {
        echo '<p>Configure how image metadata should be generated from post title.</p>';
    }

    public function generate_alt_field_callback() {
        $options = get_option($this->plugin_options_key);
        $generate_alt = isset($options['generate_alt']) ? $options['generate_alt'] : true;
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo esc_attr($this->plugin_options_key); ?>[generate_alt]" 
                   value="1" 
                   <?php checked($generate_alt, true); ?>>
            Generate ALT text from post title
        </label>
        <p class="description">Recommended for SEO and accessibility.</p>
        <?php
    }

    public function generate_title_field_callback() {
        $options = get_option($this->plugin_options_key);
        $generate_title = isset($options['generate_title']) ? $options['generate_title'] : true;
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo esc_attr($this->plugin_options_key); ?>[generate_title]" 
                   value="1" 
                   <?php checked($generate_title, true); ?>>
            Generate image title from post title
        </label>
        <?php
    }

    public function generate_description_field_callback() {
        $options = get_option($this->plugin_options_key);
        $generate_description = isset($options['generate_description']) ? $options['generate_description'] : true;
        ?>
        <label>
            <input type="checkbox" 
                   name="<?php echo esc_attr($this->plugin_options_key); ?>[generate_description]" 
                   value="1" 
                   <?php checked($generate_description, true); ?>>
            Generate image description from post title/excerpt
        </label>
        <?php
    }

    public function section_callback() {
        echo '<p>Configure which post types should have auto-generated featured images and their respective template IDs using Robolly.</p>';
        echo '<p><strong>Note:</strong> Template IDs are specific to your <strong><a href="https://robolly.com/dashboard" target="blank">Robolly</a></strong> account. Make sure to use valid <strong>template IDs</strong> from an active account</p>';
        echo '<p class="description"><strong>Post Image Extraction:</strong> Enable per post-type to use the first image from post content in your Robolly template.</p>';
        echo '<p><strong>Plugin by <a href="https://www.ruhanirabin.com" target="blank">RuhaniRabin.com</a></strong>(MIT license). <a href="https://paypal.me/ruhanirabin" target="blank">Donate</a> if this helped you out.</p>';
    }

    public function post_type_field_callback($args) {
        $defaults = $this->get_default_settings();
        $options = get_option($this->plugin_options_key, $defaults);
        $post_type = $args['post_type'];
        
        $options[$post_type] = wp_parse_args($options[$post_type], array(
            'enabled' => false,
            'template_id' => '',
            'use_post_image' => false,
            'image_param_name' => 'image',
            'min_image_width' => 600,
            'trigger_on' => 'publish' // New option: 'publish' or 'save'
        ));
        
        $enabled = $options[$post_type]['enabled'];
        $template_id = $options[$post_type]['template_id'];
        $use_post_image = $options[$post_type]['use_post_image'];
        $image_param_name = $options[$post_type]['image_param_name'];
        $min_image_width = $options[$post_type]['min_image_width'];
        $trigger_on = $options[$post_type]['trigger_on'];
        ?>
        <div class="post-type-settings">
            <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][enabled]" 
                       value="1" 
                       <?php checked($enabled, true); ?>>
                <strong>Enable auto featured image generation</strong>
            </label>
            <br>
            <label>
                Template ID:
                <input type="text" 
                       name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][template_id]" 
                       value="<?php echo esc_attr($template_id); ?>"
                       placeholder="Enter Robolly template ID"
                       class="regular-text">
            </label>
            
            <div style="margin-top: 10px;">
                <label style="display: block; margin-bottom: 5px;"><strong>Generate image when:</strong></label>
                <label style="display: inline-block; margin-right: 20px;">
                    <input type="radio" 
                           name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][trigger_on]" 
                           value="publish" 
                           <?php checked($trigger_on, 'publish'); ?>>
                    On publish only
                </label>
                <label style="display: inline-block;">
                    <input type="radio" 
                           name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][trigger_on]" 
                           value="save" 
                           <?php checked($trigger_on, 'save'); ?>>
                    On any save/update
                </label>
                <p class="description" style="margin: 5px 0 0 0;">Choose when to automatically generate featured images. "On any save/update" will generate on manual saves, but only once per post.</p>
            </div>
            
            <div class="post-image-settings" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">
                <label>
                    <input type="checkbox" 
                           name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][use_post_image]" 
                           value="1" 
                           <?php checked($use_post_image, true); ?>>
                    <strong>Use first post image in template</strong>
                </label>
                <p class="description" style="margin: 5px 0 10px 25px;">Extract and send the first suitable image from post content to Robolly</p>
                
                <div style="margin-left: 25px;">
                    <label style="display: inline-block; margin-right: 20px;">
                        Image parameter name:
                        <input type="text" 
                               name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][image_param_name]" 
                               value="<?php echo esc_attr($image_param_name); ?>"
                               placeholder="image"
                               style="width: 150px;">
                    </label>
                    
                    <label style="display: inline-block;">
                        Min width:
                        <input type="number" 
                               name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][min_image_width]" 
                               value="<?php echo esc_attr($min_image_width); ?>"
                               min="100"
                               max="5000"
                               step="50"
                               style="width: 80px;">
                        <span>px</span>
                    </label>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('afi_robolly_settings');
                do_settings_sections('afi-robolly-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>

        <style>
            .post-type-settings {
                margin-bottom: 15px;
                padding: 10px;
                background: #fff;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .post-type-settings input[type="text"] {
                margin-top: 5px;
                width: 100%;
                max-width: 400px;
            }
            .post-type-settings label {
                display: block;
                margin-bottom: 5px;
            }
        </style>
        <?php
    }

    // MODIFICATION 6: Updated default settings - removed global image settings, now per post-type
    private function get_default_settings() {
        $defaults = array(
            'api_key' => '',
            'generate_alt' => true,
            'generate_title' => true,
            'generate_description' => true
        );
        $post_types = get_post_types(['public' => true], 'names');
        
        foreach ($post_types as $post_type) {
            $defaults[$post_type] = array(
                'enabled' => false,
                'template_id' => '',
                'use_post_image' => false,
                'image_param_name' => 'image',
                'min_image_width' => 600,
                'trigger_on' => 'publish'
            );
        }
        
        return $defaults;
    }

    // MODIFICATION 7: Updated sanitization - now handles per post-type image settings
    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        $sanitized_input['api_key'] = sanitize_text_field($input['api_key']);
        $sanitized_input['generate_alt'] = isset($input['generate_alt']) ? true : false;
        $sanitized_input['generate_title'] = isset($input['generate_title']) ? true : false;
        $sanitized_input['generate_description'] = isset($input['generate_description']) ? true : false;

        foreach (get_post_types(['public' => true], 'names') as $post_type) {
            $sanitized_input[$post_type] = array(
                'enabled' => isset($input[$post_type]['enabled']) ? true : false,
                'template_id' => isset($input[$post_type]['template_id']) 
                    ? sanitize_text_field($input[$post_type]['template_id']) 
                    : '',
                'use_post_image' => isset($input[$post_type]['use_post_image']) ? true : false,
                'image_param_name' => isset($input[$post_type]['image_param_name']) 
                    ? sanitize_text_field($input[$post_type]['image_param_name']) 
                    : 'image',
                'min_image_width' => isset($input[$post_type]['min_image_width']) 
                    ? absint($input[$post_type]['min_image_width']) 
                    : 600,
                'trigger_on' => isset($input[$post_type]['trigger_on']) && in_array($input[$post_type]['trigger_on'], array('publish', 'save'))
                    ? $input[$post_type]['trigger_on']
                    : 'publish'
            );
        }
        
        return $sanitized_input;
    }

    private function is_post_type_supported($post_type) {
        $options = get_option($this->plugin_options_key, array());
        return isset($options[$post_type]['enabled']) && 
               $options[$post_type]['enabled'] && 
               !empty($options[$post_type]['template_id']);
    }

    private function get_template_id($post_type) {
        $options = get_option($this->plugin_options_key, array());
        return isset($options[$post_type]['template_id']) 
            ? $options[$post_type]['template_id'] 
            : false;
    }

    // MODIFICATION 8: New method to extract first suitable image from post content
    /**
     * Extract the first image URL from post content that meets minimum width requirement
     * 
     * @param string $content Post content
     * @param int $min_width Minimum width in pixels
     * @return string|false Image URL or false if none found
     */
    private function extract_first_image($content, $min_width = 600) {
        if (empty($content)) {
            return false;
        }

        // Parse HTML content
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML parsing warnings
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        
        if ($images->length === 0) {
            error_log('AFI Robolly: No images found in post content');
            return false;
        }

        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            
            if (empty($src)) {
                continue;
            }

            // Convert relative URLs to absolute
            if (strpos($src, 'http') !== 0) {
                $src = site_url($src);
            }

            // Get actual image dimensions
            $dimensions = $this->get_image_dimensions($src);
            
            if (!$dimensions) {
                error_log('AFI Robolly: Could not determine dimensions for image: ' . $src);
                continue;
            }

            // Check if image meets minimum width requirement
            if ($dimensions['width'] >= $min_width) {
                error_log('AFI Robolly: Found suitable image (' . $dimensions['width'] . 'x' . $dimensions['height'] . '): ' . $src);
                return esc_url_raw($src);
            } else {
                error_log('AFI Robolly: Image too small (' . $dimensions['width'] . 'px < ' . $min_width . 'px): ' . $src);
            }
        }

        error_log('AFI Robolly: No images meeting minimum width requirement found');
        return false;
    }

    // MODIFICATION 9: New method to get actual image dimensions
    /**
     * Get actual dimensions of an image from URL or attachment ID
     * 
     * @param string $image_url Image URL
     * @return array|false Array with 'width' and 'height' or false on failure
     */
    private function get_image_dimensions($image_url) {
        // First, try to get attachment ID if it's a local image
        $attachment_id = attachment_url_to_postid($image_url);
        
        if ($attachment_id) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            if ($metadata && isset($metadata['width']) && isset($metadata['height'])) {
                return array(
                    'width' => $metadata['width'],
                    'height' => $metadata['height']
                );
            }
        }

        // If not in media library or metadata unavailable, get dimensions directly
        $image_path = $this->url_to_path($image_url);
        
        if ($image_path && file_exists($image_path)) {
            $size = @getimagesize($image_path);
            if ($size !== false) {
                return array(
                    'width' => $size[0],
                    'height' => $size[1]
                );
            }
        }

        // Last resort: try to get dimensions via remote request (slower)
        $response = wp_remote_head($image_url, array('timeout' => 5));
        if (!is_wp_error($response)) {
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (strpos($content_type, 'image') !== false) {
                // Download temporarily to check dimensions
                $temp_file = download_url($image_url, 5);
                if (!is_wp_error($temp_file)) {
                    $size = @getimagesize($temp_file);
                    @unlink($temp_file);
                    if ($size !== false) {
                        return array(
                            'width' => $size[0],
                            'height' => $size[1]
                        );
                    }
                }
            }
        }

        return false;
    }

    // MODIFICATION 10: New helper method to convert URL to file path
    /**
     * Convert image URL to local file path
     * 
     * @param string $url Image URL
     * @return string|false Local file path or false
     */
    private function url_to_path($url) {
        $upload_dir = wp_upload_dir();
        
        // Check if it's a local upload
        if (strpos($url, $upload_dir['baseurl']) === 0) {
            return str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
        }

        return false;
    }

    /**
     * Check if an image with the same filename already exists in media library
     * 
     * @param string $filename The filename to check
     * @return int|false Attachment ID if exists, false otherwise
     */
    private function image_exists_in_library($filename) {
        global $wpdb;
        
        $attachment = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
            '%' . $wpdb->esc_like($filename) . '%'
        ));
        
        return $attachment ? (int) $attachment : false;
    }

    /**
     * Get final image URL from Robolly API with retry logic and error handling
     */
    private function get_final_image_url($api_url, $api_key) {
        $last_error = '';
        
        for ($attempt = 1; $attempt <= $this->max_attempts; $attempt++) {
            $response = wp_remote_get($api_url, array(
                'timeout' => 30,
                'redirection' => 5,
                'headers' => array(
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key
                ),
                'sslverify' => true
            ));

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                error_log('AFI Robolly: API request failed on attempt ' . $attempt . ' - ' . $last_error);
                
                if ($attempt < $this->max_attempts) {
                    sleep(2);
                }
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                $last_error = 'HTTP ' . $response_code;
                error_log('AFI Robolly: API returned status ' . $response_code . ' on attempt ' . $attempt);
                
                if ($attempt < $this->max_attempts) {
                    sleep(2);
                }
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $json_data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $last_error = 'JSON decode error: ' . json_last_error_msg();
                error_log('AFI Robolly: ' . $last_error);
                
                if ($attempt < $this->max_attempts) {
                    sleep(2);
                }
                continue;
            }
            
            if (isset($json_data['url']) && !empty($json_data['url'])) {
                return $json_data['url'];
            } else {
                $last_error = 'No URL in response';
                error_log('AFI Robolly: API response missing URL on attempt ' . $attempt);
                
                if ($attempt < $this->max_attempts) {
                    sleep(2);
                }
            }
        }

        error_log('AFI Robolly: Failed to get image URL after ' . $this->max_attempts . ' attempts. Last error: ' . $last_error);
        return false;
    }

    /**
     * Clean and sanitize title for use in image generation
     */
    private function clean_title($title) {
        $clean_title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5);
        $clean_title = preg_replace('/&#?[a-z0-9]{2,8};/i', '', $clean_title);
        $clean_title = strip_tags($clean_title);
        return trim(sanitize_text_field($clean_title));
    }

    /**
     * Generate sanitized lowercase filename from post title
     */
    private function generate_filename_from_title($post_title, $post_id) {
        // Clean the title
        $clean_title = $this->clean_title($post_title);
        
        // Convert to lowercase and sanitize
        $filename = strtolower($clean_title);
        $filename = sanitize_title($filename);
        
        // Remove any remaining special characters
        $filename = preg_replace('/[^a-z0-9-]/', '-', $filename);
        
        // Remove multiple consecutive dashes
        $filename = preg_replace('/-+/', '-', $filename);
        
        // Trim dashes from start and end
        $filename = trim($filename, '-');
        
        // Limit length to prevent issues
        $filename = substr($filename, 0, 200);
        
        // Add post ID and timestamp to ensure uniqueness
        $filename .= '-' . $post_id . '-' . time();
        
        return $filename . '.jpg';
    }

    /**
     * Process and attach the downloaded image with proper metadata
     */
    private function process_attachment($file_array, $post_id, $post) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('AFI Robolly: Failed to create attachment - ' . $attachment_id->get_error_message());
            return false;
        }

        $options = get_option($this->plugin_options_key);
        $post_title = $this->clean_title($post->post_title);
        $post_excerpt = has_excerpt($post->ID) ? get_the_excerpt($post) : $post_title;
        
        // Prepare attachment data based on settings
        $attachment_data = array(
            'ID' => $attachment_id,
        );
        
        if (isset($options['generate_title']) && $options['generate_title']) {
            $attachment_data['post_title'] = $post_title;
        }
        
        if (isset($options['generate_description']) && $options['generate_description']) {
            $attachment_data['post_excerpt'] = $post_excerpt;
            $attachment_data['post_content'] = $post_excerpt;
        }
        
        // Update attachment post
        if (!empty($attachment_data) && count($attachment_data) > 1) {
            $update_result = wp_update_post($attachment_data);
            if (is_wp_error($update_result)) {
                error_log('AFI Robolly: Failed to update attachment metadata - ' . $update_result->get_error_message());
            }
        }
        
        // Set ALT text if enabled
        if (isset($options['generate_alt']) && $options['generate_alt']) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_title);
        }

        return $attachment_id;
    }

    /**
     * Main handler for post save event
     */
    public function handle_post_save($post_id) {
        // Prevent execution during autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Prevent execution during AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Get post object
        $post = get_post($post_id);
        if (!$post) {
            error_log('AFI Robolly: Could not retrieve post object for ID ' . $post_id);
            return;
        }

        // Check if post type is supported
        if (!$this->is_post_type_supported($post->post_type)) {
            return;
        }

        // MODIFICATION 13: Check trigger setting and post status
        $options = get_option($this->plugin_options_key);
        $trigger_on = isset($options[$post->post_type]['trigger_on']) 
            ? $options[$post->post_type]['trigger_on'] 
            : 'publish';

        // Check if we should process based on trigger setting
        $should_process = false;
        
        if ($trigger_on === 'publish') {
            // Process if status is publish (covers both new publish and updates to published posts)
            if ($post->post_status === 'publish') {
                $should_process = true;
            }
        } else {
            // Process on any save for draft, pending, future, or publish
            if (in_array($post->post_status, array('draft', 'publish', 'future', 'pending'))) {
                $should_process = true;
            }
        }
        
        if (!$should_process) {
            error_log('AFI Robolly: Post status "' . $post->post_status . '" does not trigger generation (trigger setting: ' . $trigger_on . ')');
            return;
        }

        // MODIFICATION 14: Check if already auto-generated to prevent duplicates
        $already_generated = get_post_meta($post_id, '_afi_robolly_generated', true);
        if ($already_generated) {
            error_log('AFI Robolly: Featured image already auto-generated for post ' . $post_id . ', skipping');
            return;
        }

        // CRITICAL: Only generate if post doesn't have a featured image
        if (has_post_thumbnail($post_id)) {
            error_log('AFI Robolly: Post ' . $post_id . ' already has a featured image, skipping');
            return;
        }

        // Verify API key exists
        $api_key = isset($options['api_key']) ? trim($options['api_key']) : '';
        if (empty($api_key)) {
            error_log('AFI Robolly: API key is not set. Please configure in settings.');
            return;
        }

        // Get template ID for this post type
        $template_id = $this->get_template_id($post->post_type);
        if (!$template_id) {
            error_log('AFI Robolly: No template ID configured for post type ' . $post->post_type);
            return;
        }

        // Clean and prepare title for API
        $clean_title = $this->clean_title($post->post_title);
        if (empty($clean_title)) {
            error_log('AFI Robolly: Post title is empty for post ID ' . $post_id);
            return;
        }

        // MODIFICATION 11: Build API URL with optional image parameter (per post-type)
        // Note: Robolly requires URL parameters to be properly encoded
        $api_params = array();
        $api_params['title'] = $clean_title;
        $api_params['json'] = '1';

        // Check if this specific post type should extract and include post image
        if (isset($options[$post->post_type]['use_post_image']) && $options[$post->post_type]['use_post_image']) {
            $min_width = isset($options[$post->post_type]['min_image_width']) 
                ? $options[$post->post_type]['min_image_width'] 
                : 600;
            $image_url = $this->extract_first_image($post->post_content, $min_width);
            
            if ($image_url) {
                $image_param_name = isset($options[$post->post_type]['image_param_name']) 
                    ? $options[$post->post_type]['image_param_name'] 
                    : 'image';
                $api_params[$image_param_name] = $image_url;
                error_log('AFI Robolly: Including post image in API request for ' . $post->post_type . ': ' . $image_url);
            } else {
                error_log('AFI Robolly: No suitable image found in ' . $post->post_type . ' content, proceeding with title only');
            }
        }

        // Build final API URL with proper encoding for Robolly
        // http_build_query automatically handles the URL encoding correctly
        $query_string = http_build_query($api_params, '', '&', PHP_QUERY_RFC3986);
        $api_url = sprintf(
            '%s%s/render?%s',
            $this->api_base_url,
            $template_id,
            $query_string
        );

        error_log('AFI Robolly: API URL: ' . $api_url);

        // Get image URL from Robolly API
        $image_url = $this->get_final_image_url($api_url, $api_key);
        if (!$image_url) {
            error_log('AFI Robolly: Failed to get image URL for post ' . $post_id . ' after ' . $this->max_attempts . ' attempts');
            return;
        }

        // Download the image
        $temp_file = download_url($image_url);
        if (is_wp_error($temp_file)) {
            error_log('AFI Robolly: Failed to download image from ' . $image_url . ' - ' . $temp_file->get_error_message());
            return;
        }

        // Verify the downloaded file exists and is readable
        if (!file_exists($temp_file) || !is_readable($temp_file)) {
            error_log('AFI Robolly: Downloaded file is not accessible: ' . $temp_file);
            @unlink($temp_file);
            return;
        }

        // Generate filename from post title (sanitized lowercase)
        $filename = $this->generate_filename_from_title($post->post_title, $post_id);

        // Check if image with same filename already exists
        $existing_attachment = $this->image_exists_in_library($filename);
        if ($existing_attachment) {
            error_log('AFI Robolly: Image with similar filename already exists (ID: ' . $existing_attachment . '). Using existing image.');
            set_post_thumbnail($post_id, $existing_attachment);
            @unlink($temp_file);
            return;
        }

        // Prepare file array for media_handle_sideload
        $file_array = array(
            'name' => $filename,
            'tmp_name' => $temp_file
        );

        // Process and attach the image
        $attachment_id = $this->process_attachment($file_array, $post_id, $post);
        
        if ($attachment_id && !is_wp_error($attachment_id)) {
            // Set as featured image
            $thumbnail_set = set_post_thumbnail($post_id, $attachment_id);
            
            if ($thumbnail_set) {
                // MODIFICATION 15: Mark as auto-generated to prevent duplicates
                update_post_meta($post_id, '_afi_robolly_generated', true);
                update_post_meta($post_id, '_afi_robolly_generated_time', current_time('mysql'));
                
                error_log('AFI Robolly: Successfully generated and set featured image (ID: ' . $attachment_id . ') for post ' . $post_id);
                
                // Force refresh the post to ensure thumbnail is properly set
                clean_post_cache($post_id);
            } else {
                error_log('AFI Robolly: Failed to set featured image for post ' . $post_id . ' - set_post_thumbnail returned false');
                // Even if set fails, try direct meta update as fallback
                update_post_meta($post_id, '_thumbnail_id', $attachment_id);
                error_log('AFI Robolly: Attempted direct _thumbnail_id update for post ' . $post_id);
            }
        } else {
            if (is_wp_error($attachment_id)) {
                error_log('AFI Robolly: Failed to process attachment for post ' . $post_id . ' - Error: ' . $attachment_id->get_error_message());
            } else {
                error_log('AFI Robolly: Failed to process attachment for post ' . $post_id . ' - Unknown error');
            }
        }

        // Clean up temporary file
        @unlink($temp_file);
    }
}

// Initialize the plugin
new AFI_Robolly_Generator();