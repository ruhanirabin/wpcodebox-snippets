<?php
/**
 * Plugin Name: Admin Notepad 2
 * Version : 2.2
 * Author: Ruhani Rabin
 * 
 * Description: A persistent notepad for admin users in the WordPress dashboard with multiple notes support.
 */

// Add the widget to the dashboard
add_action('wp_dashboard_setup', 'advanced_admin_notepad_dashboard_widget');

function advanced_admin_notepad_dashboard_widget() {
    if (current_user_can('administrator')) {
        wp_add_dashboard_widget('advanced_admin_notepad_widget', 'Admin Notepad 2', 'advanced_admin_notepad_widget_display');
    }
}

// Display the widget content
function advanced_admin_notepad_widget_display() {
    $notes = get_option('advanced_admin_notepad_content', array());

    // Check if form was submitted and save the note
    if (isset($_POST['admin-notepad-save']) && isset($_POST['admin_notepad_nonce']) && wp_verify_nonce($_POST['admin_notepad_nonce'], 'admin-notepad-save-action')) {
        $new_note = array(
            'content' => sanitize_textarea_field($_POST['admin-notepad-content']),
            'user' => wp_get_current_user()->display_name,
            'date' => current_time('mysql')
        );
        array_unshift($notes, $new_note); // Add new note to the beginning of the array
        update_option('advanced_admin_notepad_content', $notes);
        echo '<div class="updated"><p>Note saved successfully.</p></div>';
    }

    // Delete note if requested
    if (isset($_GET['delete']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete-note-' . $_GET['delete'])) {
        $delete_id = intval($_GET['delete']);
        if (isset($notes[$delete_id])) {
            unset($notes[$delete_id]);
            $notes = array_values($notes); // Reindex array
            update_option('advanced_admin_notepad_content', $notes);
            echo '<div class="updated"><p>Note deleted successfully.</p></div>';
        }
    }

    // Display the form for new note
    echo '<form method="post" action="">';
    wp_nonce_field('admin-notepad-save-action', 'admin_notepad_nonce');
    echo '<textarea id="admin-notepad-content" name="admin-notepad-content" style="width:100%; height:200px;" placeholder="Enter your note here..."></textarea>';
    echo '<input type="submit" name="admin-notepad-save" class="button button-primary" value="Save Note">';
    echo '</form>';

    // Display existing notes
    echo '<h3>Existing Notes:</h3>';
    echo '<ul id="admin-notepad-list" style="list-style-type: none; padding: 0;">';
    foreach ($notes as $index => $note) {
        $first_line = strtok($note['content'], "\n");
        echo '<li data-index="' . $index . '" style="margin-bottom: 5px; cursor: pointer; position: relative;">';
        echo '<span style="background: #f0f0f0; padding: 5px; display: inline-block; width: 100%; box-sizing: border-box;">';
        echo esc_html($first_line) . ' - <small>' . esc_html($note['user']) . ' (' . esc_html($note['date']) . ')</small>';
        echo '</span>';
        $delete_url = wp_nonce_url(add_query_arg('delete', $index), 'delete-note-' . $index);
        echo '<a href="' . esc_url($delete_url) . '" style="position: absolute; right: 5px; top: 5px; text-decoration: none; color: #a00;" onclick="return confirm(\'Are you sure you want to delete this note?\');">[X]</a>';
        echo '</li>';
    }
    echo '</ul>';

    echo '<p>Admin Notepad 2 for WPCodeBox by <a href="https://ruhanirabin.com" target="_blank">RuhaniRabin.com</a></p>';

    // Add JavaScript to handle click events
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#admin-notepad-list li').click(function(e) {
            if (!$(e.target).is('a')) {
                var index = $(this).data('index');
                var notes = <?php echo json_encode($notes); ?>;
                $('#admin-notepad-content').val(notes[index].content);
            }
        });
    });
    </script>
    <?php
}