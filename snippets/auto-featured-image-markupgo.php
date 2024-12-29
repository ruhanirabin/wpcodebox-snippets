<?php
/*
Plugin Name: Auto Featured Image Generator with Retry Logic
Description: Automatically generates and assigns dynamic featured images using MarkupGo API
Version: 2.5
Release: Production
Author: Ruhani Rabin
URL: https://www.ruhanirabin.com

Licence: MIT

Copyright 2024 Ruhani Rabin https://www.ruhanirabin.com

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the “Software”), to deal in 
the Software without restriction, including without limitation the rights to 
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to 
do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

if (!defined('ABSPATH')) exit;

class Auto_Featured_Image_Generator {
    private $api_base_url = 'https://render.markupgo.com/template/';
    private $max_attempts = 3;
    private $plugin_options_key = 'afi_settings';

    public function __construct() {
        add_action('save_post', array($this, 'handle_post_save'), 10, 1);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Initialize default settings if they don't exist
        if (false === get_option($this->plugin_options_key)) {
            add_option($this->plugin_options_key, $this->get_default_settings());
        }
    }

    public function add_admin_menu() {
        add_options_page(
            'Auto Featured Image Settings',
            'Auto Featured Image',
            'manage_options',
            'auto-featured-image-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'auto_featured_image_settings',
            $this->plugin_options_key,
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'afi_main_section',
            'Post Type Settings',
            array($this, 'section_callback'),
            'auto-featured-image-settings'
        );

        foreach (get_post_types(['public' => true], 'objects') as $post_type) {
            add_settings_field(
                'afi_' . $post_type->name,
                $post_type->labels->singular_name,
                array($this, 'post_type_field_callback'),
                'auto-featured-image-settings',
                'afi_main_section',
                array('post_type' => $post_type->name)
            );
        }
    }

    public function sanitize_settings($input) {
        $defaults = $this->get_default_settings();
        $sanitized_input = array();
        
        // Loop through all possible post types to ensure we don't miss any
        foreach ($defaults as $post_type => $default_settings) {
            $sanitized_input[$post_type] = array(
                'enabled' => isset($input[$post_type]['enabled']) ? true : false,
                'template_id' => isset($input[$post_type]['template_id']) 
                    ? sanitize_text_field($input[$post_type]['template_id']) 
                    : ''
            );
        }
        
        return $sanitized_input;
    }

    public function section_callback() {
        echo '<p>Configure which post types should have auto-generated featured images and their respective template IDs using magic URL options in MarkupGo.</p>';
        echo '<p><strong>Note:</strong> Template IDs are specific to your <strong><a href="https://markupgo.com/dashboard" target="blank">MarkupGo</a></strong> account. Make sure to use valid <strong>template IDs</strong> from an active account</p>';
        echo '<p><strong>Plugin by <a href="https://www.ruhanirabin.com" target="blank">RuhaniRabin.com</a></strong>(MIT license). <a href="https://paypal.me/ruhanirabin" target="blank">Donate</a> if this helped you out.</p>';        
    }

    public function post_type_field_callback($args) {
        $defaults = $this->get_default_settings();
        $options = get_option($this->plugin_options_key, $defaults);
        $post_type = $args['post_type'];
        
        // Ensure we have all required array keys
        $options[$post_type] = wp_parse_args($options[$post_type], array(
            'enabled' => false,
            'template_id' => ''
        ));
        
        $enabled = $options[$post_type]['enabled'];
        $template_id = $options[$post_type]['template_id'];
        ?>
        <div class="post-type-settings">
            <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][enabled]" 
                       value="1" 
                       <?php checked($enabled, true); ?>>
                Enable auto featured image generation
            </label>
            <br>
            <label>
                Template ID:
                <input type="text" 
                       name="<?php echo esc_attr($this->plugin_options_key); ?>[<?php echo esc_attr($post_type); ?>][template_id]" 
                       value="<?php echo esc_attr($template_id); ?>"
                       placeholder="Enter MarkupGo template ID"
                       class="regular-text">
            </label>
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
                settings_fields('auto_featured_image_settings');
                do_settings_sections('auto-featured-image-settings');
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

    private function get_default_settings() {
        $defaults = array();
        $post_types = get_post_types(['public' => true], 'names');
        
        foreach ($post_types as $post_type) {
            $defaults[$post_type] = array(
                'enabled' => false,
                'template_id' => ''
            );
        }
        
        return $defaults;
    }

    private function is_post_type_supported($post_type) {
        $defaults = $this->get_default_settings();
        $options = get_option($this->plugin_options_key, $defaults);
        
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

    private function get_final_image_url($api_url) {
        for ($attempt = 1; $attempt <= $this->max_attempts; $attempt++) {
            $response = wp_remote_get($api_url, array(
                'timeout' => 30,
                'redirection' => 5,
                'headers' => array('Accept' => 'application/json'),
                'sslverify' => true
            ));

            if (is_wp_error($response)) {
                error_log('Auto Featured Image Generator: API request failed on attempt ' . $attempt);
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $json_data = json_decode($body, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($json_data['url'])) {
                return $json_data['url'];
            }

            $headers = wp_remote_retrieve_headers($response);
            if (!empty($headers['location'])) {
                return $headers['location'];
            }

            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (strpos($content_type, 'image/') === 0) {
                return $api_url;
            }

            if ($attempt < $this->max_attempts) {
                sleep(2);
            }
        }

        return false;
    }

    private function clean_title($title) {
        $clean_title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5);
        $clean_title = preg_replace('/&#?[a-z0-9]{2,8};/i', '', $clean_title);
        return trim(sanitize_text_field($clean_title));
    }

    private function process_attachment($file_array, $post_id, $post) {
        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            error_log('Auto Featured Image Generator: Failed to create attachment - ' . $attachment_id->get_error_message());
            return false;
        }

        $post_title = $post->post_title;
        $post_excerpt = has_excerpt($post->ID) ? get_the_excerpt($post) : $post_title;
        
        $attachment_data = array(
            'ID' => $attachment_id,
            'post_title' => $post_title,
            'post_excerpt' => $post_excerpt,
            'post_content' => $post_excerpt,
        );
        wp_update_post($attachment_data);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $post_title);

        return $attachment_id;
    }

    public function handle_post_save($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify if this is an auto save routine
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        $post = get_post($post_id);
        if (!$post || !$this->is_post_type_supported($post->post_type) || 
            $post->post_status !== 'publish' || has_post_thumbnail($post_id)) {
            return;
        }

        $template_id = $this->get_template_id($post->post_type);
        if (!$template_id) {
            return;
        }

        $clean_title = $this->clean_title($post->post_title);
        $api_url = sprintf(
            '%s%s.webp?heading=%s&json=true',
            $this->api_base_url,
            $template_id,
            rawurlencode($clean_title)
        );

        $image_url = $this->get_final_image_url($api_url);
        if (!$image_url) {
            error_log('Auto Featured Image Generator: Failed to get image URL for post ' . $post_id);
            return;
        }

        $temp_file = download_url($image_url);
        if (is_wp_error($temp_file)) {
            error_log('Auto Featured Image Generator: Failed to download image - ' . $temp_file->get_error_message());
            return;
        }

        $file_array = array(
            'name' => sanitize_title($post->post_name) . '-' . time() . '.webp',
            'tmp_name' => $temp_file
        );

        $attachment_id = $this->process_attachment($file_array, $post_id, $post);
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
        }

        @unlink($temp_file);
    }
}

// Initialize the plugin
new Auto_Featured_Image_Generator();