<?php
/**
 * Plugin Name: WP Partner
 * Version: 1.0
 * Description: A simple plugin to display a partner table
 */

if (!defined('ABSPATH')) exit;

function wp_create_partner_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'partner';
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

register_activation_hook(__FILE__, 'wp_create_partner_table');

add_action('rest_api_init', 'register_partner_routes');

function register_partner_routes() {
    register_rest_route('wp/v2', '/partners/', array(
        'methods' => 'GET',
        'callback' => 'get_partner'
    ));
}

function get_partner(WP_REST_Request $request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'partner';
    $partners = $wpdb->get_results("SELECT * FROM $table_name");
    if ($partners) {
        return $partners;
    } else {
        return 'No Partners found.';
    }
}

add_action('admin_menu', 'register_admin_page');

function register_admin_page() {
    add_menu_page(
        __('Partners', 'wp-partner-table'),
        __('Partners', 'wp-partner-table'),
        'manage_options',
        'partner-table-page',
        'render_admin_page',
        'dashicons-money',
        30
    );
}

function render_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div id="partner-form">
            <h2>Add Partner</h2>
            <form id="add-partner-form" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="partner-name">Name:</label>
                    <input type="text" id="partner-name" name="partner-name" placeholder="Partner Name" required>
                </div>
                <div class="form-group">
                    <label for="partner-url">URL:</label>
                    <input type="url" id="partner-url" name="partner-url" placeholder="https://example.com" required>
                </div>
                <div class="form-group">
                    <label for="partner-logo">Logo:</label>
                    <input type="file" id="partner-logo" name="partner-logo" accept="image/*" required>
                </div>
                <button type="submit" class="button button-primary" name="add-partner">Add Partner</button>
            </form>
        </div>
        <div id="partner-list">
            <h2>Partners</h2>
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
                    $table_name = $wpdb->prefix . 'partner';
                    $partners = $wpdb->get_results("SELECT * FROM $table_name");

                    if ($partners) {
                        foreach ($partners as $partner) {
                            echo "<tr>";
                            echo "<td>{$partner->name}</td>";
                            echo "<td><img src='{$partner->logo}' style='max-width: 100px; height: auto;' alt='{$partner->name}' /></td>";
                            echo "<td><a href='{$partner->url}' target='_blank'>{$partner->url}</a></td>";
                            echo "<td><form method='post'><input type='hidden' name='partner-id' value='{$partner->id}'><button class='button button-danger' name='delete-partner'>Delete</button></form></td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4'>No partners found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    wp_enqueue_style('partner-admin-css', plugin_dir_url(__FILE__) . 'style.css');

    if (isset($_POST['add-partner'])) {
        save_partner();
    }

    if (isset($_POST['delete-partner'])) {
        delete_partner($_POST['partner-id']);
    }
}

function save_partner() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'partner';
    $partner_name = sanitize_text_field($_POST['partner-name']);
    $partner_url = esc_url_raw($_POST['partner-url']);

    if (!empty($_FILES['partner-logo']['tmp_name'])) {
        $attachment_id = media_handle_upload('partner-logo', 0);

        if (is_wp_error($attachment_id)) {
            echo 'Error uploading image: ' . $attachment_id->get_error_message();
            return;
        }

        $partner_logo = wp_get_attachment_url($attachment_id);
    } else {
        echo 'Please upload a logo.';
        return;
    }

    $insert_result = $wpdb->insert(
        $table_name,
        array(
            'name' => $partner_name,
            'logo' => $partner_logo,
            'url' => $partner_url
        )
    );

    if ($insert_result !== false) {
        echo 'Partner added successfully.';
        echo '<script>location.reload();</script>';
    } else {
        echo 'Failed to add partner: ' . $wpdb->last_error;
    }
}

function delete_partner($partner_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'partner';
    $delete_result = $wpdb->delete($table_name, array('id' => $partner_id));

    if ($delete_result !== false) {
        echo 'Partner deleted successfully.';
        echo '<script>location.reload();</script>';
    } else {
        echo 'Failed to delete partner: ' . $wpdb->last_error;
    }
}
