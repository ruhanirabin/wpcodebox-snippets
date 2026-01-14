<?php
/*
Plugin Name: Product Review Schema UI
Description: Configurable schema markup generator for product reviews
Version: 2.1
Author: Ruhani Rabin
Author URI: https://github.com/ruhanirabin
*/

if (!defined("ABSPATH")) {
    exit();
}

class Product_Review_Schema {
    private static $instance = null;
    private $options;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->options = get_option('prs_settings', $this->get_default_settings());
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_head', [$this, 'output_schema']);
    }

    public function get_default_settings() {
        return [
            'post_type' => 'ai-tool',
            'field_product_name' => 'tool_name',
            'field_description' => 'short_description',
            'field_rating' => 'editor_rating',
            'field_price' => 'starting_price',
            'field_link' => 'website_url',
            'field_pros' => 'product_pros_repeater',
            'field_cons' => 'product_cons_repeater',
            'field_verdict' => 'product_verdict',
            'rating_min' => 5,
            'rating_max' => 91,
            'price_valid_duration' => '1_year',
            'currency' => 'USD'
        ];
    }

public function add_admin_menu() {
        // Check if the parent menu exists
        global $menu;
        $parent_slug = 'rr-tools';
        $parent_menu_exists = false;
        
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === $parent_slug) {
                $parent_menu_exists = true;
                break;
            }
        }

        // Add parent menu if it doesn't exist
        if (!$parent_menu_exists) {
            add_menu_page(
                'RR Tools', // Page title
                'RR Tools', // Menu title
                'manage_options', // Capability
                $parent_slug, // Menu slug
                function() { // Function to display the page
                    echo '<div class="wrap"><h1>RR Tools</h1><p>Welcome to RR Tools suite.</p></div>';
                },
                'dashicons-code-standards', // Icon (you can change this)
                30 // Position
            );
        }

        // Add submenu
        add_submenu_page(
            $parent_slug, // Parent slug
            'Product Review Schema', // Page title
            'Product Review Schema', // Menu title
            'manage_options', // Capability
            'product-review-schema', // Menu slug
            [$this, 'render_settings_page'] // Function to display the page
        );

        // If this is the first submenu, remove the default submenu item that duplicates the parent
        if (!$parent_menu_exists) {
            global $submenu;
            if (isset($submenu[$parent_slug])) {
                unset($submenu[$parent_slug][0]);
            }
        }
    }

    public function register_settings() {
        register_setting('prs_settings_group', 'prs_settings');

        add_settings_section(
            'prs_main_section',
            'Field Mapping Settings',
            null,
            'product-review-schema'
        );

        // Add settings fields
        $this->add_settings_fields();
    }

    private function add_settings_fields() {
        $fields = [
            'post_type' => 'Post Type Slug',
            'field_product_name' => 'Product Name Field',
            'field_description' => 'Description Field',
            'field_rating' => 'Rating Field',
            'field_price' => 'Price Field',
            'field_link' => 'Product URL Field',
            'field_pros' => 'Pros Field (Repeater)',
            'field_cons' => 'Cons Field (Repeater)',
            'field_verdict' => 'Verdict Field'
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [$this, 'render_field'],
                'product-review-schema',
                'prs_main_section',
                ['field' => $key]
            );
        }

        // Add additional settings
        add_settings_field(
            'rating_range',
            'Rating Count Range',
            [$this, 'render_rating_range'],
            'product-review-schema',
            'prs_main_section'
        );

        add_settings_field(
            'price_valid_duration',
            'Price Valid Duration',
            [$this, 'render_price_duration'],
            'product-review-schema',
            'prs_main_section'
        );

        add_settings_field(
            'currency',
            'Currency',
            [$this, 'render_currency'],
            'product-review-schema',
            'prs_main_section'
        );
    }

    public function render_field($args) {
        $field = $args['field'];
        $value = isset($this->options[$field]) ? $this->options[$field] : '';
        ?>
        <input type="text" 
               name="prs_settings[<?php echo esc_attr($field); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
        />
        <?php
        if (in_array($field, ['field_pros', 'field_cons'])) {
            echo '<p class="description">Must be an ACF Repeater field with a sub-field named "' . 
                 ($field === 'field_pros' ? 'product_pros_text' : 'product_cons_text') . 
                 '"</p>';
        }
    }

    public function render_rating_range() {
        $min = isset($this->options['rating_min']) ? $this->options['rating_min'] : 5;
        $max = isset($this->options['rating_max']) ? $this->options['rating_max'] : 91;
        ?>
        <input type="number" 
               name="prs_settings[rating_min]" 
               value="<?php echo esc_attr($min); ?>" 
               min="1" 
               max="100" 
               style="width: 80px;"
        /> 
        to 
        <input type="number" 
               name="prs_settings[rating_max]" 
               value="<?php echo esc_attr($max); ?>" 
               min="1" 
               max="100" 
               style="width: 80px;"
        />
        <?php
    }

    public function render_price_duration() {
        $duration = isset($this->options['price_valid_duration']) 
            ? $this->options['price_valid_duration'] 
            : '1_year';
        ?>
        <select name="prs_settings[price_valid_duration]">
            <option value="1_month" <?php selected($duration, '1_month'); ?>>1 Month</option>
            <option value="6_months" <?php selected($duration, '6_months'); ?>>6 Months</option>
            <option value="1_year" <?php selected($duration, '1_year'); ?>>1 Year</option>
            <option value="3_years" <?php selected($duration, '3_years'); ?>>3 Years</option>
        </select>
        <?php
    }

    public function render_currency() {
        $currency = isset($this->options['currency']) ? $this->options['currency'] : 'USD';
        ?>
        <input type="text" 
               name="prs_settings[currency]" 
               value="<?php echo esc_attr($currency); ?>" 
               class="regular-text"
        />
        <p class="description">Enter currency code (e.g., USD, EUR, GBP)</p>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Product Review Schema Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('prs_settings_group');
                do_settings_sections('product-review-schema');
                submit_button();
                ?>
            </form>
            <div class="notice notice-info">
                <p><strong>Important Notes:</strong></p>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li>All fields should correspond to Advanced Custom Fields field names.</li>
                    <li>Pros field must be an ACF Repeater field with a sub-field named "product_pros_text".</li>
                    <li>Cons field must be an ACF Repeater field with a sub-field named "product_cons_text".</li>
                    <li>The Verdict field should be a text or WYSIWYG field.</li>
                    <li>Make sure all field names are exactly as they appear in ACF.</li>
                </ul>
            </div>
        </div>
        <?php
    }

    public function output_schema() {
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        $post_type = $this->options['post_type'];
        if (!is_singular($post_type)) {
            return;
        }

        $schema = $this->get_product_review_schema();
        if (!empty($schema)) {
            printf(
                '<script type="application/ld+json">%s</script>',
                wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
    }

    private function get_product_review_schema() {
        $post = get_post();
        $fields = $this->options;

        // Get field values
        $product_name = get_field($fields['field_product_name'], $post->ID);
        $rating = get_field($fields['field_rating'], $post->ID);
        $price = get_field($fields['field_price'], $post->ID);
        $product_link = get_field($fields['field_link'], $post->ID);
        $short_desc = get_field($fields['field_description'], $post->ID);
        $verdict = get_field($fields['field_verdict'], $post->ID);

        // Get featured image
        $image_url = has_post_thumbnail($post->ID) 
            ? get_the_post_thumbnail_url($post->ID, 'full') 
            : '';

        // Generate random rating count
        $rating_count = rand(
            (int)$fields['rating_min'], 
            (int)$fields['rating_max']
        );

        // Base schema structure
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "Product",
            "name" => $product_name,
            "image" => $image_url,
            "description" => $short_desc,
            "brand" => $product_name,
            "review" => [
                "@type" => "Review",
                "name" => get_the_title() . " review",
                "reviewRating" => [
                    "@type" => "Rating",
                    "ratingValue" => $rating,
                    "bestRating" => 5,
                ],
                "author" => [
                    "@type" => "Person",
                    "name" => $this->get_author_name($post->post_author),
                ],
                "reviewBody" => $this->get_review_body($verdict),
            ],
            "aggregateRating" => [
                "@type" => "AggregateRating",
                "ratingValue" => $rating,
                "ratingCount" => $rating_count,
                "bestRating" => 5,
            ],
        ];

        // Add pros if available
        $pros = $this->get_repeater_items($fields['field_pros'], $post->ID);
        if (!empty($pros)) {
            $schema["review"]["positiveNotes"] = [
                "@type" => "ItemList",
                "itemListElement" => $this->format_list_items($pros),
            ];
        }

        // Add cons if available
        $cons = $this->get_repeater_items($fields['field_cons'], $post->ID);
        if (!empty($cons)) {
            $schema["review"]["negativeNotes"] = [
                "@type" => "ItemList",
                "itemListElement" => $this->format_list_items($cons),
            ];
        }

        // Add price offer if available
        if ($price) {
            $schema["offers"] = [
                "@type" => "Offer",
                "price" => $this->normalize_price($price),
                "priceCurrency" => $fields['currency'],
                "itemCondition" => "https://schema.org/NewCondition",
                "availability" => "https://schema.org/InStock",
                "url" => $product_link,
                "priceValidUntil" => $this->get_price_valid_until($fields['price_valid_duration']),
            ];
        }

        return apply_filters('prs_product_review_schema', $schema, $post);
    }

    private function get_repeater_items($field_name, $post_id) {
        $items = [];
        if (have_rows($field_name, $post_id)) {
            while (have_rows($field_name, $post_id)) {
                the_row();
                // Update to use the correct sub-field names
                $pros_text = get_sub_field('product_pros_text');
                $cons_text = get_sub_field('product_cons_text');
                
                // Check which type of item we're dealing with
                if (strpos($field_name, 'pros') !== false && $pros_text) {
                    $items[] = $pros_text;
                } elseif (strpos($field_name, 'cons') !== false && $cons_text) {
                    $items[] = $cons_text;
                }
            }
        }
        return $items;
    }

    private function format_list_items($items) {
        $formatted = [];
        foreach ($items as $position => $item) {
            $formatted[] = [
                "@type" => "ListItem",
                "position" => $position + 1,
                "name" => trim($item),
            ];
        }
        return $formatted;
    }

    private function get_review_body($verdict) {
        if (!empty($verdict)) {
            // Strip HTML and normalize whitespace
            return wp_strip_all_tags($verdict, true);
        }
        return get_the_excerpt();
    }

    private function normalize_price($price) {
        // Remove any currency symbols and normalize to decimal
        $price = preg_replace('/[^0-9.]/', '', $price);
        return floatval($price);
    }

    private function get_price_valid_until($duration) {
        switch ($duration) {
            case '1_month':
                $interval = '+1 month';
                break;
            case '6_months':
                $interval = '+6 months';
                break;
            case '3_years':
                $interval = '+3 years';
                break;
            case '1_year':
            default:
                $interval = '+1 year';
                break;
        }
        return date('Y-m-d', strtotime($interval));
    }

    private function get_author_name($author_id) {
        $author_name = get_the_author_meta('display_name', $author_id);
        return $author_name ? $author_name : 'Admin';
    }
}

// Initialize the plugin
function product_review_schema_init() {
    Product_Review_Schema::get_instance();
}
add_action('init', 'product_review_schema_init');
