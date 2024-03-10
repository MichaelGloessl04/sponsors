<?php
/**
 * Plugin Name: WP Sponsor Table
 * Version: 1.0
 * Description: A simple plugin to display a sponsor table
 */

if (!defined('ABSPATH')) exit;

function wp_create_sponsor_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sponsor';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        logo varchar(100) NOT NULL,
        url varchar(100) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'wp_create_sponsor_table');

add_action('rest_api_init', 'register_sponsor_routes');

function register_sponsor_routes() {
    register_rest_route('wp/v2', '/sponsors/', array(
        'methods' => 'GET',
        'callback' => 'get_sponsor'
    ));
}

function get_sponsor(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sponsor';
    $sponsors = $wpdb->get_results("SELECT * FROM $table_name");
    if ($sponsors) {
        return $sponsors;
    } else {
        return 'No Sponsors found.';
    }
}

add_action('admin_menu', 'register_admin_page');

function register_admin_page() {
    add_menu_page(
        __('Sponsors', 'wp-sponsor-table'),
        __('Sponsors', 'wp-sponsor-table'),
        'manage_options',
        'sponsor-table-page',
        'render_admin_page',
        'dashicons-money',
        30
    );
}

function render_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div id="sponsor-form">
            <h2>Add Sponsor</h2>
            <form id="add-sponsor-form" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="sponsor-name">Name:</label>
                    <input type="text" id="sponsor-name" name="sponsor-name" placeholder="Sponsor Name" required>
                </div>
                <div class="form-group">
                    <label for="sponsor-url">URL:</label>
                    <input type="url" id="sponsor-url" name="sponsor-url" placeholder="https://example.com" required>
                </div>
                <div class="form-group">
                    <label for="sponsor-logo">Logo:</label>
                    <input type="file" id="sponsor-logo" name="sponsor-logo" accept="image/*" required>
                </div>
                <button type="submit" class="button button-primary" name="add-sponsor">Add Sponsor</button>
            </form>
        </div>
        <div id="sponsor-list">
            <h2>Sponsors</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Logo</th>
                        <th>URL</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'sponsor';
                    $sponsors = $wpdb->get_results("SELECT * FROM $table_name");

                    if ($sponsors) {
                        foreach ($sponsors as $sponsor) {
                            echo "<tr>";
                            echo "<td>{$sponsor->name}</td>";
                            echo "<td><img src='{$sponsor->logo}' style='max-width: 100px; height: auto;' alt='{$sponsor->name}' /></td>";
                            echo "<td><a href='{$sponsor->url}' target='_blank'>{$sponsor->url}</a></td>";
                            echo "<td><form method='post'><input type='hidden' name='sponsor-id' value='{$sponsor->id}'><button class='button button-danger' name='delete-sponsor'>Delete</button></form></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No sponsors found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    wp_enqueue_style('sponsor-admin-css', plugin_dir_url(__FILE__) . 'style.css');

    if (isset($_POST['add-sponsor'])) {
        save_sponsor();
    }

    if (isset($_POST['delete-sponsor'])) {
        delete_sponsor($_POST['sponsor-id']);
    }
}

function save_sponsor() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'sponsor';
    $sponsor_name = sanitize_text_field($_POST['sponsor-name']);
    $sponsor_url = esc_url_raw($_POST['sponsor-url']);

    if (!empty($_FILES['sponsor-logo']['tmp_name'])) {
        $attachment_id = media_handle_upload('sponsor-logo', 0);

        if (is_wp_error($attachment_id)) {
            echo 'Error uploading image: ' . $attachment_id->get_error_message();
            return;
        }

        $sponsor_logo = wp_get_attachment_url($attachment_id);
    } else {
        echo 'Please upload a logo.';
        return;
    }

    $insert_result = $wpdb->insert(
        $table_name,
        array(
            'name' => $sponsor_name,
            'logo' => $sponsor_logo,
            'url' => $sponsor_url
        )
    );

    if ($insert_result !== false) {
        echo 'Sponsor added successfully.';
        echo '<script>location.reload();</script>';
    } else {
        echo 'Failed to add sponsor: ' . $wpdb->last_error;
    }
}

function delete_sponsor($sponsor_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'sponsor';
    $delete_result = $wpdb->delete($table_name, array('id' => $sponsor_id));

    if ($delete_result !== false) {
        echo 'Sponsor deleted successfully.';
        echo '<script>location.reload();</script>';
    } else {
        echo 'Failed to delete sponsor: ' . $wpdb->last_error;
    }
}
