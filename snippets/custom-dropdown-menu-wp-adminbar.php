<?php
/*
Plugin Name: Custom Admin Bar Dropdown
Description: Adds a custom dropdown menu to the admin bar with configurable menu items.
Version: 1.1
Author: Ruhani Rabin
Date: Sunday, June 09, 2024
*/

// Hook to add menu item to admin bar
add_action('admin_bar_menu', 'custom_admin_bar_dropdown', 100);

function custom_admin_bar_dropdown($wp_admin_bar) {
    // Get the menu title from the options table, default to 'Custom Menu'
    $menu_title = get_option('custom_admin_bar_title', 'Custom Menu');
    // Get the menu items from the options table, default to an empty array
    $menu_items = get_option('custom_admin_bar_items', array());

    // Add the main menu node to the admin bar
    $wp_admin_bar->add_node(array(
        'id'    => 'custom_admin_bar_dropdown',
        'title' => $menu_title,
        'href'  => '#',
    ));

    // Loop through each menu item and add it as a child node
    foreach ($menu_items as $item) {
        $wp_admin_bar->add_node(array(
            'id'     => sanitize_title($item['title']),
            'title'  => $item['title'],
            'href'   => $item['url'],
            'parent' => 'custom_admin_bar_dropdown',
        ));
    }

    // Add manage link as the last item
    $wp_admin_bar->add_node(array(
        'id'     => 'custom_admin_bar_dropdown_manage',
        'title'  => 'Manage Menu',
        'href'   => admin_url('options-general.php?page=custom-admin-bar-dropdown'),
        'parent' => 'custom_admin_bar_dropdown',
    ));
}

// Hook to add settings page
add_action('admin_menu', 'custom_admin_bar_settings_page');

function custom_admin_bar_settings_page() {
    // Add a settings page under the "Settings" menu
    add_options_page(
        'Custom Admin Bar Dropdown',
        'Admin Bar Dropdown',
        'manage_options',
        'custom-admin-bar-dropdown',
        'custom_admin_bar_settings_page_html'
    );
}

// HTML for settings page
function custom_admin_bar_settings_page_html() {
    // Check if the user has the required capability
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save the settings if the form is submitted
    if (isset($_POST['custom_admin_bar_title'])) {
        update_option('custom_admin_bar_title', sanitize_text_field($_POST['custom_admin_bar_title']));
    }

    if (isset($_POST['custom_admin_bar_items'])) {
        $items = array_map(function($item) {
            return array(
                'title' => sanitize_text_field($item['title']),
                'url'   => esc_url_raw($item['url']),
            );
        }, $_POST['custom_admin_bar_items']);
        update_option('custom_admin_bar_items', $items);
    }

    // Get the current settings
    $menu_title = get_option('custom_admin_bar_title', 'Custom Menu');
    $menu_items = get_option('custom_admin_bar_items', array());

    ?>
    <div class="wrap">
        <h1>Custom Admin Bar Dropdown Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Menu Title</th>
                    <td><input type="text" name="custom_admin_bar_title" value="<?php echo esc_attr($menu_title); ?>" class="regular-text"></td>
                </tr>
            </table>

            <h2>Menu Items</h2>
            <div id="menu-items">
                <?php foreach ($menu_items as $index => $item): ?>
                    <div class="menu-item">
                        <label>Title: <input type="text" name="custom_admin_bar_items[<?php echo $index; ?>][title]" value="<?php echo esc_attr($item['title']); ?>"></label>
                        <label>URL: <input type="text" name="custom_admin_bar_items[<?php echo $index; ?>][url]" value="<?php echo esc_url($item['url']); ?>"></label>
                        <button type="button" class="remove-item">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-item">Add Item</button>
            <br><br>
            <?php submit_button(); ?>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var addItemButton = document.getElementById('add-item');
            var menuItemsContainer = document.getElementById('menu-items');

            // Add new menu item input fields when "Add Item" button is clicked
            addItemButton.addEventListener('click', function() {
                var index = menuItemsContainer.children.length;
                var newItem = document.createElement('div');
                newItem.className = 'menu-item';
                newItem.innerHTML = `
                    <label>Title: <input type="text" name="custom_admin_bar_items[${index}][title]"></label>
                    <label>URL: <input type="text" name="custom_admin_bar_items[${index}][url]"></label>
                    <button type="button" class="remove-item">Remove</button>
                `;
                menuItemsContainer.appendChild(newItem);
            });

            // Remove menu item input fields when "Remove" button is clicked
            menuItemsContainer.addEventListener('click', function(event) {
                if (event.target.classList.contains('remove-item')) {
                    event.target.parentElement.remove();
                }
            });
        });
    </script>
    <?php
}
?>
