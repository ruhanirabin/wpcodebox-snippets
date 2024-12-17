<?php
/*
Plugin Name: Advanced Schema Generator
Description: Generates schema markup for posts, pages, reviews, and code snippets
Version: 1.0
Author: Ruhani Rabin
Author URI: https://github.com/ruhanirabin
*/

if (!defined('ABSPATH')) exit;

class Advanced_Schema_Generator {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_head', array($this, 'output_schema'));
    }

    public function output_schema() {
        if (is_admin() || wp_doing_ajax()) return;

        $schema = $this->get_schema_data();
        if (!empty($schema)) {
            printf(
                '<script type="application/ld+json">%s</script>',
                wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
    }

    private function get_schema_data() {
        if (is_singular('post')) {
            return $this->get_blog_posting_schema();
        } elseif (is_singular('page')) {
            return $this->get_website_schema();
        } elseif (is_singular('product-review')) {
            return $this->get_product_review_schema();
        } elseif (is_singular('code-snippet')) {
            return $this->get_code_snippet_schema();
        } elseif (is_archive()) {
            return $this->get_collection_page_schema();
        }
        return array();
    }

    private function get_blog_posting_schema() {
        $post = get_post();
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => get_the_title(),
            'description' => get_the_excerpt(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'url' => get_permalink(),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author(),
                'url' => get_author_posts_url(get_the_author_meta('ID'))
            )
        );

        if (has_post_thumbnail()) {
            $schema['image'] = array(
                '@type' => 'ImageObject',
                'url' => get_the_post_thumbnail_url(null, 'full')
            );
        }

        return apply_filters('asg_blog_posting_schema', $schema, $post);
    }

    private function get_website_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url()
        );

        return apply_filters('asg_website_schema', $schema);
    }

private function get_product_review_schema() {
    $post = get_post();
    $product_name = get_field('rl_product_name', $post->ID);
    $rating = get_field('rl_rating', $post->ID);
    $price = get_field('rl_price', $post->ID);
    $product_link = get_field('rl_product_link', $post->ID);
    $pros = get_field('rl_pros', $post->ID);
    $cons = get_field('rl_cons', $post->ID);
    $short_desc = get_field('rl_short_description', $post->ID);

    // Get the post featured image URL
    $image_url = has_post_thumbnail($post->ID) ? get_the_post_thumbnail_url($post->ID, 'full') : '';

    // Generate a random number for `reviewCount` between 5 and 91
    $rating_count = rand(5, 91);

    $schema = array(
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product_name,
        'image' => $image_url,
        'description' => $short_desc,
        'review' => array(
            '@type' => 'Review',
            'name' => get_the_title() . ' review',
            'reviewRating' => array(
                '@type' => 'Rating',
                'ratingValue' => $rating,
                'bestRating' => 5
            ),
            'author' => array(
                '@type' => 'Person',
                'name' => $this->get_author_name($post->post_author)
            ),
            'reviewBody' => get_the_excerpt()
        ),
        'aggregateRating' => array(
            '@type' => 'AggregateRating',
            'ratingValue' => $rating,
            'ratingCount' => $rating_count
        )
    );

    // Add pros if available
    if ($pros) {
        $schema['review']['positiveNotes'] = array(
            '@type' => 'ItemList',
            'itemListElement' => $this->parse_pros_cons_list($pros)
        );
    }

    // Add cons if available
    if ($cons) {
        $schema['review']['negativeNotes'] = array(
            '@type' => 'ItemList',
            'itemListElement' => $this->parse_pros_cons_list($cons)
        );
    }

    if ($price) {
        $schema['offers'] = array(
            '@type' => 'Offer',
            'price' => $price,
            'priceCurrency' => 'USD',
            'availability' => 'https://schema.org/InStock',
            'url' => $product_link,
            'priceValidUntil' => date('Y-m-d', strtotime('+1 year'))
        );
    }

    return apply_filters('asg_product_review_schema', $schema, $post);
}

private function parse_pros_cons_list($content) {
    if (empty($content)) return array();
    
    // Strip HTML tags except for list items
    $content = strip_tags($content, '<li>');
    
    // Extract text from list items
    preg_match_all('/<li>(.*?)<\/li>/s', $content, $matches);
    
    $items = array();
    
    if (!empty($matches[1])) {
        foreach ($matches[1] as $position => $item) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => trim($item)
            );
        }
    }
    
    return $items;
}

// Helper function to get the author's name
private function get_author_name($author_id) {
    // Fetch the author's display name by their user ID
    $author_name = get_the_author_meta('display_name', $author_id);
    return $author_name ? $author_name : 'Ruhani Rabin';
}

    private function get_code_snippet_schema() {
        $post = get_post();
        //$summary = get_field('snippet_summary', $post->ID);
        $summary = get_the_excerpt();
        $snippet_type = get_field('snippet_type', $post->ID);
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareSourceCode',
            'name' => get_the_title(),
            'description' => $summary,
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'codeSampleType' => $this->get_programming_language($post->ID),
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author()
            ),
            'programmingLanguage' => $this->get_programming_language($post->ID)
        );

        // Add additional snippets if available
        if (have_rows('additional_code_snippets', $post->ID)) {
            $codeRepository = array();
            while (have_rows('additional_code_snippets', $post->ID)) {
                the_row();
                $github_url = get_sub_field('snippet_github');
                if ($github_url) {
                    $codeRepository[] = $github_url;
                }
            }
            if (!empty($codeRepository)) {
                $schema['codeRepository'] = $codeRepository;
            }
        }

        return apply_filters('asg_code_snippet_schema', $schema, $post);
    }

    private function get_collection_page_schema() {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => get_the_archive_title(),
            'description' => get_the_archive_description(),
            'url' => get_permalink()
        );

        return apply_filters('asg_collection_page_schema', $schema);
    }

    private function get_programming_language($post_id) {
        $languages = array();
        if (have_rows('additional_code_snippets', $post_id)) {
            while (have_rows('additional_code_snippets', $post_id)) {
                the_row();
                $type = get_sub_field('snippet_add-on_type');
                if ($type && !in_array($type, $languages)) {
                    $languages[] = $type;
                }
            }
        }
        return implode(', ', $languages);
    }
}

// Initialize the plugin
function advanced_schema_generator_init() {
    Advanced_Schema_Generator::get_instance();
}
add_action('init', 'advanced_schema_generator_init');
?>