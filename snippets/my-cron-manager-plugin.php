<?php 
/*
Plugin Name: My Cron Manager
Description: Allows you to see your current wordpress cron events, delete them selectively and able to clean up orphaned cron events.
Version: 1.5
Author: Ruhani Rabin
Date: Thursday, October 24, 2024
Compatible with: WordPress 6.4.x

*/

if (!defined('ABSPATH')) {
    exit;
}

// Only run in admin
if (!is_admin()) {
    return;
}

class WP_Cron_Manager_Snippet {
    private static $instance = null;
    private $message = '';
    private $deleted_count = 0;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_actions'));
    }

    public function add_admin_menu() {
        add_management_page(
            'My Cron Manager',
            'My Cron Manager',
            'manage_options',
            'wp-cron-manager-snippet',
            array($this, 'admin_page')
        );
    }

    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'], 'cron_manager_action')) {
            switch ($_POST['action']) {
                case 'delete_cron':
                    if (isset($_POST['cron_hook'])) {
                        $hook = sanitize_text_field($_POST['cron_hook']);
                        wp_clear_scheduled_hook($hook);
                        $this->message = sprintf('Cron job "%s" deleted successfully!', $hook);
                        add_action('admin_notices', array($this, 'show_success_notice'));
                    }
                    break;
                case 'delete_all_crons':
                    $count = $this->remove_all_cron_jobs();
                    $this->message = sprintf('%d cron jobs deleted successfully!', $count);
                    add_action('admin_notices', array($this, 'show_success_notice'));
                    break;
                case 'clean_orphaned':
                    $count = $this->clean_orphaned_cron_jobs();
                    $this->message = sprintf('%d orphaned cron jobs cleaned successfully!', $count);
                    add_action('admin_notices', array($this, 'show_success_notice'));
                    break;
            }
        }
    }

    public function show_success_notice() {
        if (empty($this->message)) {
            $this->message = 'Action completed successfully!';
        }
        ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($this->message); ?></p>
        </div>
        <?php
    }

    private function remove_all_cron_jobs() {
        $count = 0;
        $cron_jobs = _get_cron_array();
        if (!empty($cron_jobs)) {
            foreach ($cron_jobs as $timestamp => $cron) {
                foreach ($cron as $hook => $events) {
                    wp_unschedule_event($timestamp, $hook);
                    $count++;
                }
            }
        }
        return $count;
    }

    private function clean_orphaned_cron_jobs() {
        $count = 0;
        $active_plugins = get_option('active_plugins');
        $cron_jobs = _get_cron_array();

        if (!empty($cron_jobs)) {
            foreach ($cron_jobs as $timestamp => $cron) {
                foreach ($cron as $hook => $events) {
                    $is_orphaned = true;

                    if (strpos($hook, 'wp_') === 0) {
                        $is_orphaned = false;
                        continue;
                    }

                    foreach ($active_plugins as $plugin) {
                        if (strpos($hook, basename(dirname($plugin))) !== false) {
                            $is_orphaned = false;
                            break;
                        }
                    }

                    if ($is_orphaned) {
                        wp_unschedule_event($timestamp, $hook);
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    public function admin_page() {
        $cron_jobs = _get_cron_array();
        ?>
        <style>
            .wp-list-table .column-actions {
                width: 100px;
            }
            .cron-info {
                background: #fff;
                padding: 15px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .tablenav.top {
                margin: 15px 0;
            }
            .tablenav.top form {
                margin-right: 10px;
            }
            .success-toast {
                position: fixed;
                top: 50px;
                right: 20px;
                background: #fff;
                padding: 15px 25px;
                border-left: 4px solid #46b450;
                box-shadow: 0 1px 4px rgba(0,0,0,0.15);
                z-index: 999999;
            }
        </style>
        <div class="wrap">
            <h1>My Cron Manager</h1>
            <h4>by <a href="https://www.ruhanirabin.com" target="_blank">RuhaniRabin.com</a></h4>
            <div class="cron-info" style="margin-top: 20px;">
                <h3>System Information</h3>
                <p>
                    <strong>WordPress Cron Enabled: </strong>
                    <?php 
                    $is_cron_enabled = !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
                    $status_style = $is_cron_enabled 
                        ? 'background-color: #d4edda; color: #155724; padding: 2px 6px; border-radius: 3px;' 
                        : 'background-color: #f8d7da; color: #721c24; padding: 2px 6px; border-radius: 3px;';
                    ?>
                    <span style="<?php echo $status_style; ?>">
                        <?php echo $is_cron_enabled ? 'Yes' : 'No'; ?>
                    </span>
                </p>
                <p>
                    <strong>Server Time: </strong>
                    <?php echo date('Y-m-d H:i:s'); ?>
                </p>
            </div>
            <div class="tablenav top">
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('cron_manager_action'); ?>
                    <input type="hidden" name="action" value="clean_orphaned">
                    <input type="submit" class="button" value="Clean Orphaned Crons">
                </form>

                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('cron_manager_action'); ?>
                    <input type="hidden" name="action" value="delete_all_crons">
                    <input type="submit" class="button" value="Delete All Crons" 
                           onclick="return confirm('Are you sure you want to delete all cron jobs?');">
                </form>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Hook</th>
                        <th>Next Run (Server Time)</th>
                        <th>Schedule</th>
                        <th>Arguments</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($cron_jobs)) {
                        foreach ($cron_jobs as $timestamp => $cron) {
                            foreach ($cron as $hook => $events) {
                                foreach ($events as $event) {
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($hook); ?></td>
                                        <td><?php echo esc_html(date('Y-m-d H:i:s', $timestamp)); ?></td>
                                        <td><?php echo esc_html($event['schedule'] ?? 'One-time'); ?></td>
                                        <td><?php echo !empty($event['args']) ? esc_html(json_encode($event['args'])) : 'None'; ?></td>
                                        <td>
                                            <form method="post" style="display: inline;">
                                                <?php wp_nonce_field('cron_manager_action'); ?>
                                                <input type="hidden" name="action" value="delete_cron">
                                                <input type="hidden" name="cron_hook" value="<?php echo esc_attr($hook); ?>">
                                                <input type="submit" class="button button-small" value="Delete" 
                                                       onclick="return confirm('Are you sure you want to delete this cron job?');">
                                            </form>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5">No cron jobs found.</td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Initialize using singleton pattern
WP_Cron_Manager_Snippet::get_instance();