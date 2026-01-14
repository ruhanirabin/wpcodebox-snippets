<?php
// MOST OF THE TIME YOU WILL NOT NEED THE OPENING <?PHP TAG
//
// Enqueue Highlight.js scripts and styles
// v3
// Date: Saturday, September 06, 2025, 12:24 AM
// Author: Ruhani Rabin
// Upgraded with code folding and better buttons
//

// Enqueue Highlight.js scripts and styles
function enqueue_highlight_js() {
    wp_enqueue_script('highlight-js', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js', array(), '11.8.0', true);
    wp_enqueue_style('highlight-js-style', 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github-dark.min.css', array(), '11.8.0');

    // Inline script for highlight.js, copy button, and folding
    wp_add_inline_script('highlight-js', '
        document.addEventListener("DOMContentLoaded", function() {
            hljs.configure({ ignoreUnescapedHTML: true });
            hljs.highlightAll();

            document.querySelectorAll("pre code").forEach(function(codeBlock, index) {
                const pre = codeBlock.parentElement;
                const wrapper = document.createElement("div");
                wrapper.className = "code-block-wrapper";

                // Copy Button
                const button = document.createElement("button");
                button.className = "copy-code-btn";
                button.innerHTML = "Copy Code";
                button.setAttribute("aria-label", "Copy code to clipboard");

                pre.parentNode.insertBefore(wrapper, pre);
                wrapper.appendChild(pre);
                wrapper.appendChild(button);

                // Fold logic
                const wordCount = codeBlock.textContent.trim().split(/\\s+/).length;
                if (wordCount > 300) {
                    const foldButton = document.createElement("button");
                    foldButton.className = "fold-toggle-btn";
                    foldButton.innerText = "Show More";
                    wrapper.appendChild(foldButton);
                    pre.classList.add("folded");

                    foldButton.addEventListener("click", function() {
                        pre.classList.toggle("unfolded");
                        foldButton.innerText = pre.classList.contains("unfolded") ? "Show Less" : "Show More";
                    });
                }

                // Copy logic
                button.addEventListener("click", async function() {
                    try {
                        await navigator.clipboard.writeText(codeBlock.textContent);
                        button.innerHTML = "Copied!";
                        setTimeout(() => { button.innerHTML = "Copy Code"; }, 2000);
                    } catch (err) {
                        console.error("Failed to copy:", err);
                        button.innerHTML = "Failed to copy";
                    }
                });
            });
        });
    ');
}
add_action('wp_enqueue_scripts', 'enqueue_highlight_js');

// Wrap inline code tags
function filter_code_tags($content) {
    $pattern = '/<p>\s*<code>(.*?)<\/code>\s*<\/p>/is';
    $replacement = '<div class="code-container"><pre><code>$1</code></pre></div>';
    return preg_replace($pattern, $replacement, $content);
}
add_filter('the_content', 'filter_code_tags', 20);

// Add custom CSS styles using vars
function add_custom_code_style() {
    ?>
    <style>
        :root {
            --code-bg: #1e1e1e;
            --code-color: #ddd;
            --btn-bg: var(--primary);
            --btn-border: var(--neutral-ultra-dark);
            --btn-color: var(--white);
            --btn-bg-hover: var(--primary-semi-dark);
        }

        .code-block-wrapper {
            position: relative;
            margin: 1.5em 0;
        }

        pre {
            margin: 0;
            padding: 1.5em;
            border-radius: 8px;
            background-color: var(--code-bg);
            color: var(--code-color);
            overflow-x: auto;
            transition: max-height 0.3s ease;
            max-height: 500px;
        }

        pre.folded {
            max-height: 250px;
            overflow: hidden;
        }

        pre.unfolded {
            max-height: none;
        }

        pre code {
            font-family: 'Consolas', 'Monaco', 'Andale Mono', monospace;
            font-size: 14px;
            line-height: 1.5;
            padding: 0;
        }

        .copy-code-btn,
        .fold-toggle-btn {
            position: absolute;
            top: 1.8em;
            right: 1.8em;
            padding: 0.5em 1em;
            font-size: 0.85em;
            color: var(--btn-color);
            background-color: var(--btn-bg);
            border: 1px solid var(--btn-border);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
            z-index: 10;
        }

        .fold-toggle-btn {
            top: auto;
            bottom: 1.8em;
        }

        .copy-code-btn:hover,
        .fold-toggle-btn:hover {
            background-color: var(--btn-bg-hover);
        }

        @media (max-width: 600px) {
            pre code {
                font-size: 12px;
            }

            .copy-code-btn,
            .fold-toggle-btn {
                font-size: 0.75em;
                padding: 0.4em 0.8em;
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'add_custom_code_style');
?>
