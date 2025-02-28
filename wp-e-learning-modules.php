<?php
/**
 * Plugin Name: WP E-Learning Modules
 * Plugin URI: https://github.com/AlonCohen96/wp-e-learning-modules-plugin
 * Description: A WordPress plugin for managing e-learning modules.
 * Version: 1.0
 * Author: AlonCohen
 * Author URI: https://github.com/AlonCohen96
 * License: GPL2
 * Text Domain: wp-e-learning-modules
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Plugin activation hook
function wp_e_learning_activate() {
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'wp_e_learning_activate');

// Plugin deactivation hook
function wp_e_learning_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'wp_e_learning_deactivate');


// Create the custom table when the plugin is activated
function create_elearning_modules_table() {
    global $wpdb;

    // Set the table name
    $table_name = $wpdb->prefix . 'e_learning_modules';

    // SQL to create the table
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id INT(11) NOT NULL AUTO_INCREMENT,
        module_number VARCHAR(255) NOT NULL,
        module_title VARCHAR(255) NOT NULL,
        module_introtext TEXT NOT NULL,
        module_thumbnail VARCHAR(255) DEFAULT NULL,
        module_video VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Include the required file for dbDelta
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql); // This function creates/updates the table
}
register_activation_hook(__FILE__, 'create_elearning_modules_table');


// Add a custom admin menu for the E-Learning Module
function elearning_admin_menu() {
    add_menu_page(
        'Add E-Learning Module',  // Page title
        'E-Learning Modules',     // Menu title
        'manage_options',         // Capability
        'elearning-modules',      // Slug
        'elearning_admin_page',   // Callback function
        'dashicons-welcome-learn-more', // Icon
        20  // Position
    );
}
add_action('admin_menu', 'elearning_admin_menu');


// Admin Page Callback
function elearning_admin_page() {
    ?>
    <div class="wrap">
        <h1>Add New E-Learning Module</h1>
        <?php if (isset($_GET['success'])): ?>
            <div class="notice notice-success"><p>Module saved successfully!</p></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" style="display: flex; flex-direction: column;">
            <?php wp_nonce_field('save_elearning_module', 'elearning_nonce'); ?>

            <label for="module_number">Module Number:</label>
            <input type="text" id="module_number" name="module_number" required>

            <label for="module_title">Module Title:</label>
            <input type="text" id="module_title" name="module_title" required>

            <label for="module_introtext">Module Introtext:</label>
            <textarea id="module_introtext" name="module_introtext" required></textarea>

            <label for="module_thumbnail">Module Thumbnail:</label>
            <input type="file" id="module_thumbnail" name="module_thumbnail">

            <label for="module_video">Module Video Link:</label>
            <input type="text" id="module_video" name="module_video" required>

            <input type="submit" name="submit_module" value="Save Module" class="button button-primary" style="width: 250px; margin-bottom: 40px;">
        </form>

        <?php
            // Display the modules table
            display_elearning_modules();
        ?>
    </div>
    <?php
}

// Display existing modules from the custom table
function display_elearning_modules() {
    global $wpdb;

    // Query the custom table for the saved modules
    $table_name = $wpdb->prefix . 'e_learning_modules';
    $modules = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    if ($modules) {
        echo '<table class="widefat fixe">';
        echo '<thead><tr><th>Module Number</th><th>Title</th><th>Intro Text</th><th>Video Link</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($modules as $module) {
            echo '<tr>';
            echo '<td>' . esc_html($module->module_number) . '</td>';
            echo '<td>' . esc_html($module->module_title) . '</td>';
            echo '<td>' . esc_html($module->module_introtext) . '</td>';
            echo '<td><a href="' . esc_url($module->module_video) . '" target="_blank">Video</a></td>';
            echo '<td><a href="#">Edit</a> | <a href="#">Delete</a></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No modules found.</p>';
    }
}



function save_elearning_module() {
    if (isset($_POST['submit_module']) && check_admin_referer('save_elearning_module', 'elearning_nonce')) {
        // Sanitize input data
        $module_number = sanitize_text_field($_POST['module_number']);
        $module_title = sanitize_text_field($_POST['module_title']);
        $module_introtext = sanitize_textarea_field($_POST['module_introtext']);
        $module_video = esc_url($_POST['module_video']);

        // Handle the thumbnail upload (if applicable)
        $thumbnail_id = null;
        if (!empty($_FILES['module_thumbnail']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $attachment_id = media_handle_upload('module_thumbnail', 0); // No need to associate it with a post
            if (!is_wp_error($attachment_id)) {
                $thumbnail_id = $attachment_id; // Save attachment ID for the thumbnail
            }
        }

        // Get global $wpdb object for interacting with the custom table
        global $wpdb;

        // Insert the new module into the custom table
        $table_name = $wpdb->prefix . 'e_learning_modules';  // Your custom table
        $result = $wpdb->insert(
            $table_name,
            array(
                'module_number'   => $module_number,
                'module_title'    => $module_title,
                'module_introtext' => $module_introtext,
                'module_video'     => $module_video,
                'module_thumbnail' => $thumbnail_id, // Store the thumbnail attachment ID
                'created_at'       => current_time('mysql'), // Store the current date/time
            ),
            array(
                '%s', // module_number
                '%s', // module_title
                '%s', // module_introtext
                '%s', // module_video
                '%d', // module_thumbnail (ID)
                '%s'  // created_at (datetime)
            )
        );

        // Check if the insertion was successful
        if ($result) {
            // Redirect to avoid form resubmission
            wp_redirect(admin_url('admin.php?page=elearning-modules&success=true'));
            exit;
        } else {
            // Handle error if needed
            wp_redirect(admin_url('admin.php?page=elearning-modules&error=true'));
            exit;
        }
    }
}
add_action('admin_init', 'save_elearning_module');


function delete_elearning_module() {
    // Check if 'delete_module' is set and the nonce is valid
    if (isset($_GET['delete_module']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_elearning_module')) {
        $module_id = intval($_GET['delete_module']);

        if ($module_id > 0) {
            global $wpdb;

            // Table name
            $table_name = $wpdb->prefix . 'e_learning_modules';

            // Delete the module from the custom table
            $deleted = $wpdb->delete($table_name, array('ID' => $module_id), array('%d'));

            if ($deleted) {
                // Redirect to the module list with a success message
                wp_redirect(admin_url('admin.php?page=elearning-modules&deleted=true'));
                exit;
            } else {
                // Redirect to the module list with an error message
                wp_redirect(admin_url('admin.php?page=elearning-modules&deleted=false'));
                exit;
            }
        }
    }
}
add_action('admin_init', 'delete_elearning_module');
