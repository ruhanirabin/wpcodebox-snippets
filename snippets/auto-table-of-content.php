<?php 

// v2.0
// Date: Sunday, June 02, 2024
// Author: Ruhani Rabin
//
//

add_filter( 'the_content', function ( $content ) {
    // This snippet requires the DOMDocument class to be available.
    if ( ! class_exists( 'DOMDocument' ) ) {
        return $content;
    }

    if ( ! is_single() || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }

    $dom = new DOMDocument();
    // Prevent warnings caused by HTML5 tags
    libxml_use_internal_errors(true);
    $load_content = mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );
    $dom->loadHTML( $load_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();

    $xpath = new DOMXPath( $dom );
    $h2_headings = $xpath->query('//h2');

    if ( $h2_headings->length > 2 ) {
        $headings = $xpath->query('//h2 | //h3');

        if ( $headings->length > 0 ) {
            // Find the first <h2> element
            $first_h2 = $h2_headings->item(0);

            // Create the table of contents with title
            $headings_list = '<div class="table-of-contents-container" id="toc"><p class="toc-title">Table of Contents</p>';
            $headings_list .= '<ul class="table-of-contents" style="list-style: none;">';
            foreach ( $headings as $heading ) {
                $heading_id = $heading->getAttribute('id');
                if ( empty( $heading_id ) ) {
                    // Generate a heading id and add it to the heading.
                    $heading_id  = sanitize_title( $heading->nodeValue );
                    $heading->setAttribute('id', $heading_id);
                }
                $heading_text = $heading->nodeValue;
                $padding_class = $heading->tagName === 'h3' ? ' toc-h3-padding' : '';
                $headings_list .= '<li class="' . $padding_class . '"><a href="#' . $heading_id . '">' . $heading_text . '</a></li>';
            }
            $headings_list .= '</ul></div>';

            // Create a new DOM element for the table of contents
            $dom_toc = $dom->createDocumentFragment();
            $dom_toc->appendXML($headings_list);

            // Insert the table of contents before the first <h2>
            $first_h2->parentNode->insertBefore($dom_toc, $first_h2);

            // Save the modified content
            $content = $dom->saveHTML();
        }
    }

    return $content;
});

// Add some CSS for padding and title styling
add_action('wp_head', function() {
    echo '<style>
        .table-of-contents-container {
            display: block;
        }
        .table-of-contents ul {
            list-style: none !important; 
            padding: 0;
        }
        .table-of-contents .toc-h3-padding {
            padding-left: 20px;
        }
        .toc-title {
            font-weight: bold;
            font-size: 21px;
            margin-bottom: 10px;
        }
    </style>';
});