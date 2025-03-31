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


/* +++++++++++++++++++++++++++++++++++++++++++++++++++ Set Up +++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
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
        module_video_iframe TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Include the required file for dbDelta
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql); // This function creates/updates the table
}
register_activation_hook(__FILE__, 'create_elearning_modules_table');



/* ++++++++++++++++++++++++++++++++++++++++++++++++++ Main Page +++++++++++++++++++++++++++++++++++++++++++++++++++++ */
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

$textarea_default_content = '<h3>Titel</h3>
<p>Paragraph</p>'
;

// Admin Page Callback with success or error message
function elearning_admin_page() {
    global $textarea_default_content;
    ?>
    <div class="wrap">
        <h1>E-Learning Modules</h1>

        <?php if (isset($_GET['success'])): ?>
            <div class="notice notice-success"><p>Module saved successfully!</p></div>
        <?php elseif (isset($_GET['deleted']) && $_GET['deleted'] == 'true'): ?>
            <div class="notice notice-success"><p>Module deleted successfully!</p></div>
        <?php elseif (isset($_GET['deleted']) && $_GET['deleted'] == 'false'): ?>
            <div class="notice notice-error"><p>Failed to delete module.</p></div>
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

            <label for="module_video_iframe">Module Elements:</label>
            <div id="video_iframe_fields" style="display: flex; flex-direction: column;">
                <div class="iframe-field" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <textarea name="module_video_iframe[]" style="width: 100%" required><?php echo $textarea_default_content; ?></textarea>
                </div>
            </div>
            <button type="button" id="add_video" style="width: 150px; margin-bottom: 20px;">Add Element</button>

            <input type="submit" name="submit_module" value="Save Module" class="button button-primary" style="width: 250px; margin-bottom: 40px;">
        </form>

        <?php
        // Display the modules table
        display_elearning_modules();
        ?>
    </div>
    <script>
        document.getElementById('add_video').addEventListener('click', function() {
            var container = document.createElement('div');
            container.className = 'iframe-field';
            container.style.display = 'flex';
            container.style.alignItems = 'center';
            container.style.gap = '10px';
            container.style.marginBottom = '10px';

            var newTextarea = document.createElement('textarea');
            newTextarea.name = 'module_video_iframe[]';
            newTextarea.textContent = <?php echo json_encode($textarea_default_content); ?>;
            newTextarea.style.width = '100%';
            newTextarea.required = true;

            var deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.innerText = 'Delete';

            deleteButton.addEventListener('click', function() {
                container.remove();
            });

            container.appendChild(newTextarea);
            container.appendChild(deleteButton);
            document.getElementById('video_iframe_fields').appendChild(container);
        });

        document.querySelectorAll('.remove_video').forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.remove();
            });
        });
    </script>
    <?php
}


// Display existing modules from the custom table
function display_elearning_modules() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'e_learning_modules';
    $modules = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

    if ($modules) {
        echo '<table class="widefat fixed">';
        echo '<thead><tr><th>Module Number</th><th>Title</th><th>Intro Text</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($modules as $module) {
            $edit_url = admin_url('admin.php?page=edit-elearning-module&module_id=' . $module->id);
            $delete_url = wp_nonce_url(admin_url('admin.php?page=elearning-modules&delete_module=' . $module->id), 'delete_elearning_module');

            echo '<tr>';
            echo '<td>' . esc_html($module->module_number) . '</td>';
            echo '<td>' . esc_html($module->module_title) . '</td>';
            echo '<td>' . esc_html($module->module_introtext) . '</td>';
            echo '<td><a href="' . esc_url($edit_url) . '">Edit</a> | <a href="' . esc_url($delete_url) . '">Delete</a></td>';
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
        $allowed_html = array_merge(
            wp_kses_allowed_html('post'),
            array(
                'iframe' => array(
                    'id'                  => array(),
                    'width'               => array(),
                    'height'              => array(),
                    'src'                 => array(),
                    'class'               => array(),
                    'allowfullscreen'     => array(),
                    'webkitallowfullscreen' => array(),
                    'mozallowfullscreen'  => array(),
                    'allow'               => array(),
                    'referrerpolicy'      => array(),
                    'sandbox'             => array(),
                    'frameborder'         => array(),
                    'title'               => array(),
                ),
            )
        );

        $module_video_iframes = array_map(function($iframe) use ($allowed_html) {
            return wp_kses($iframe, $allowed_html);
        }, $_POST['module_video_iframe']);


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
                'module_video_iframe'     => serialize($module_video_iframes), // Save array as serialized string
                'module_thumbnail' => $thumbnail_id, // Store the thumbnail attachment ID
                'created_at'       => current_time('mysql'), // Store the current date/time
            ),
            array(
                '%s', // module_number
                '%s', // module_title
                '%s', // module_introtext
                '%s', // module_video_iframe (serialized)
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
            $deleted = $wpdb->delete($table_name, array('id' => $module_id), array('%d'));

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

/* ++++++++++++++++++++++++++++++++++++++++++++++++++ Edit Page +++++++++++++++++++++++++++++++++++++++++++++++++++++ */
// Register the admin menu page for editing
function elearning_admin_edit_menu() {
    add_submenu_page(
        null, // Hidden menu
        'Edit E-Learning Module',
        'Edit Module',
        'manage_options',
        'edit-elearning-module',
        'elearning_edit_module_page'
    );
}
add_action('admin_menu', 'elearning_admin_edit_menu');

function elearning_edit_module_page() {
    if (!isset($_GET['module_id']) || !is_numeric($_GET['module_id'])) {
        echo '<div class="notice notice-error"><p>Invalid module ID.</p></div>';
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'e_learning_modules';
    $module_id = intval($_GET['module_id']);
    $module = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $module_id));

    if (!$module) {
        echo '<div class="notice notice-error"><p>Module not found.</p></div>';
        return;
    }

    // Get the current thumbnail URL
    $current_thumbnail = !empty($module->module_thumbnail) ? wp_get_attachment_url($module->module_thumbnail) : '';
    $module_video_iframes = unserialize($module->module_video_iframe);  // Unserialize the video iframes

    global $textarea_default_content;
    ?>
    <div class="wrap">
        <h1>Edit E-Learning Module</h1>

        <?php if (isset($_GET['updated']) && $_GET['updated'] == 'true'): ?>
            <div class="notice notice-success"><p>Module updated successfully!</p></div>
        <?php elseif (isset($_GET['updated']) && $_GET['updated'] == 'false'): ?>
            <div class="notice notice-error"><p>Failed to update module.</p></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" style="display: flex; flex-direction: column;">
            <?php wp_nonce_field('update_elearning_module', 'elearning_nonce'); ?>

            <input type="hidden" name="module_id" value="<?php echo esc_attr($module->id); ?>">

            <label for="module_number">Module Number:</label>
            <input type="text" id="module_number" name="module_number" value="<?php echo esc_attr($module->module_number); ?>" required>

            <label for="module_title">Module Title:</label>
            <input type="text" id="module_title" name="module_title" value="<?php echo esc_attr($module->module_title); ?>" required>

            <label for="module_introtext">Module Introtext:</label>
            <textarea id="module_introtext" name="module_introtext" required><?php echo esc_textarea($module->module_introtext); ?></textarea>

            <label for="module_video_iframe">Module Video iFrame:</label>
            <div id="video_iframe_fields" >
                <?php foreach ($module_video_iframes as $iframe): ?>
                    <div class="iframe-field" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                        <textarea type="text" name="module_video_iframe[]" value="<?php echo esc_attr($iframe); ?>" style="width: 100%" required><?php echo $textarea_default_content; ?></textarea>
                        <button type="button" class="remove-iframe">Delete</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add_video">Add Video</button>

            <label for="module_thumbnail">Module Thumbnail:</label>
            <?php if ($current_thumbnail): ?>
                <div>
                    <img src="<?php echo esc_url($current_thumbnail); ?>" alt="Current Thumbnail" style="max-width: 200px; height: auto; margin-bottom: 10px;">
                </div>
            <?php endif; ?>
            <input type="file" id="module_thumbnail" name="module_thumbnail">

            <input type="submit" name="update_module" value="Save Changes" class="button button-primary" style="width: 250px; margin-bottom: 40px;">
        </form>
    </div>
    <script>
        // Add a new video input field
        document.getElementById('add_video').addEventListener('click', function() {
            var textareaDefaultContent = <?php echo json_encode($textarea_default_content); ?>;
            var newDiv = document.createElement('div');
            newDiv.classList.add('iframe-field');
            newDiv.style.display = 'flex';
            newDiv.style.alignItems = 'center';
            newDiv.style.gap = '10px';
            newDiv.style.marginBottom = '10px';
            newDiv.innerHTML = '<textarea type="text" name="module_video_iframe[]" style="width: 100%" required>'
                + textareaDefaultContent +
                '</textarea> <button type="button" class="remove-iframe">Delete</button>';
            document.getElementById('video_iframe_fields').appendChild(newDiv);
        });

        // Remove a video input field when the "Delete" button is clicked
        document.getElementById('video_iframe_fields').addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('remove-iframe')) {
                var iframeField = e.target.closest('.iframe-field');
                iframeField.remove();
            }
        });
    </script>
    <?php
}




function update_elearning_module() {
    if (isset($_POST['update_module']) && check_admin_referer('update_elearning_module', 'elearning_nonce')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'e_learning_modules';

        $module_id = intval($_POST['module_id']);
        $module_number = sanitize_text_field($_POST['module_number']);
        $module_title = sanitize_text_field($_POST['module_title']);
        $module_introtext = sanitize_textarea_field($_POST['module_introtext']);

        $allowed_html = array_merge(
            wp_kses_allowed_html('post'),
            array(
                'iframe' => array(
                    'id'                  => array(),
                    'width'               => array(),
                    'height'              => array(),
                    'src'                 => array(),
                    'class'               => array(),
                    'allowfullscreen'     => array(),
                    'webkitallowfullscreen' => array(),
                    'mozallowfullscreen'  => array(),
                    'allow'               => array(),
                    'referrerpolicy'      => array(),
                    'sandbox'             => array(),
                    'frameborder'         => array(),
                    'title'               => array(),
                ),
            )
        );

        $module_video_iframes = array_map(function($iframe) use ($allowed_html) {
            return wp_kses($iframe, $allowed_html);
        }, $_POST['module_video_iframe']);

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

        // Update the module in the custom table
        $result = $wpdb->update(
            $table_name,
            array(
                'module_number'   => $module_number,
                'module_title'    => $module_title,
                'module_introtext' => $module_introtext,
                'module_video_iframe'     => serialize($module_video_iframes), // Save array as serialized string
                'module_thumbnail' => $thumbnail_id, // Store the thumbnail attachment ID
            ),
            array('id' => $module_id),
            array('%s', '%s', '%s', '%s', '%d'),
            array('%d')  // Where clause
        );

        // Check if the update was successful
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=edit-elearning-module&module_id=' . $module_id . '&updated=true'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=edit-elearning-module&module_id=' . $module_id . '&updated=false'));
            exit;
        }
    }
}
add_action('admin_init', 'update_elearning_module');
