<?php
// MOST OF THE TIME YOU WILL NOT NEED THE OPENING <?PHP TAG
//
// Enqueue Highlight.js scripts and styles
// v2.5
// Date: Sunday, June 02, 2024
// Author: Ruhani Rabin
//
//
function enqueue_highlight_js() {
    wp_enqueue_script('highlight-js', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.5.0/highlight.min.js', array(), null, true);
    wp_enqueue_style('highlight-js-style', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.4.0/styles/github-dark.min.css');

    // Add a script to initialize Highlight.js and the copy code functionality
    wp_add_inline_script('highlight-js', 'document.addEventListener("DOMContentLoaded", function() {
        hljs.highlightAll();
        
        // Copy code functionality
        document.querySelectorAll("pre code").forEach(function(codeBlock, index) {
            let pre = codeBlock.parentElement;
            let button = document.createElement("a");
            button.href = "javascript:void(0);";
            button.innerText = "Copy Code";
            button.style.position = "absolute";
            button.style.top = "10px";
            button.style.right = "10px";
            button.style.textDecoration = "none";
            button.classList.add("copy-code-btn");
            button.dataset.index = index;

            pre.style.position = "relative";
            pre.style.paddingTop = "40px";
            pre.appendChild(button);

            button.addEventListener("click", function() {
                navigator.clipboard.writeText(codeBlock.textContent).then(function() {
                    alert("Code copied to clipboard!");
                }).catch(function(error) {
                    alert("Failed to copy code: " + error);
                });
            });
        });
    });');
}
add_action('wp_enqueue_scripts', 'enqueue_highlight_js');

// Filter standalone <code> tags within <p> and wrap them with <pre>
function filter_code_tags($content) {
    $pattern = '/<p>\s*<code>(.*?)<\/code>\s*<\/p>/i';
    $replacement = '<div class="code-container"><pre><code>$1</code></pre></div>';
    $content = preg_replace($pattern, $replacement, $content);
    return $content;
}
add_filter('the_content', 'filter_code_tags');

// Custom styles for <code> elements
function add_custom_code_style() {
    echo '<style>
        pre, code {
            font-family: consolas, monospace;
            font-size: 16px;
            border-radius: 5px;
            overflow-x: auto;
        }
        pre {
            position: relative;
            padding: 20px;
            padding-top: 40px;
        }
        .copy-code-btn {
            text-decoration: none;
            position: absolute;
            top: 10px;
            right: 10px;
            background: #333;
            color: #fff;
            padding: 5px 10px;
            border-radius: 3px;
        }
    /* Media query for devices with a maximum width of 600px */
    @media (max-width: 600px) {
        pre, code {
            font-size: 12px; 
            border-radius: 0px;
            overflow-x: auto;
            position: relative;
            padding: 3px;
            padding-top: 10px;        
        /* Smaller font size for mobile devices */
    }
}        
    </style>';
}
add_action('wp_head', 'add_custom_code_style');
?>