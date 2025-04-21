<?php

if (!defined('ABSPATH')) {
    exit;
}

function create_koi_streamers_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'koi_streamers';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            link tinytext NOT NULL,
            avatar_url VARCHAR(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if (!empty($wpdb->last_error)) {
        error_log('Database Error: ' . $wpdb->last_error);
    }
}

function streamers_entry_form() {
    ob_start();
    ?>
    <form method="post" action="" id="koi-streamers-form">
        <table>
            <tr>
                <th>Name</th>
            </tr>
            <tr>
                <td><input type="text" id="streamer_name" name="streamer_name" required></td>
            </tr>
            <tr>
                <th>Link</th>
            </tr>
            <tr>
                <td><input type="url" id="streamer_link" name="streamer_link" required></td>
            </tr>
            <tr>
                <th>Avatar URL</th>
            </tr>
            <tr>
                <td><input type="url" id="streamer_avatar_url" name="streamer_avatar_url"></td>
            </tr>
        </table>
        <p>
            <input type="submit" name="submit_streamer" value="Add streamer" class="button button-primary">
        </p>
        <input type="hidden" name="streamer_action" value="add_streamer">
        <?php wp_nonce_field('streamer_nonce_action', 'streamer_nonce_field'); ?>
    </form>
    <?php
    return ob_get_clean();
}

function streamers_entry_form_handler() {
    if (isset($_POST['streamer_action']) && $_POST['streamer_action'] === 'add_streamer') {
        if (!isset($_POST['streamer_nonce_field']) || !wp_verify_nonce($_POST['streamer_nonce_field'], 'streamer_nonce_action')) {
            return;
        }

        $name = sanitize_text_field($_POST['streamer_name']);
        $link = esc_url_raw($_POST['streamer_link']);
        $avatar_url = esc_url_raw($_POST['streamer_avatar_url']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'koi_streamers';

        $wpdb->insert($table_name, array(
            'name' => $name,
            'link' => $link,
            'avatar_url' => $avatar_url
        ),
            array(
                '%s',
                '%s',
                '%s'
            ));

        if ($wpdb->insert_id) {
            echo '<div class="updated"><p>Streamer added successfully!</p></div>';
        } else {
            error_log('Database Insert Error: ' . $wpdb->last_error);
            echo '<div class="error"><p>Failed to add streamer. Please try again.</p></div>';
        }
    }
}

function streamers_edit_entry_form() {
    global $wpdb;
    $streamers_table = $wpdb->prefix . 'koi_streamers';

    $items_per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $streamers_table");

    $streamers = $wpdb->get_results($wpdb->prepare("
        SELECT id, name, link, avatar_url, created_at
        FROM $streamers_table
        LIMIT %d OFFSET %d
    ", $items_per_page, $offset));

    echo '<div class="wrap">';
    echo '<h1>Edit Streamers</h1>';

    if ($streamers) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Name</th><th>Link</th><th>Avatar</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        foreach ($streamers as $streamer) {
            echo '<tr>';
            echo '<form method="post" action="">';
            echo '<td><input type="text" name="name" value="' . esc_attr($streamer->name) . '" required></td>';
            echo '<td><input type="url" name="link" value="' . esc_attr($streamer->link) . '" required></td>';
            echo '<td><input type="url" name="avatar_url" value="' . esc_attr($streamer->avatar_url) . '"></td>';
            echo '<td>';
            echo '<input type="hidden" name="streamer_id" value="' . esc_attr($streamer->id) . '">';
            echo '<button type="submit" name="streamer_action" value="edit_streamer" class="button button-primary">Update</button> ';
            echo '<button type="submit" name="streamer_action" value="delete_streamer" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this streamer?\');">Delete</button>';
            wp_nonce_field('koi_streamers_nonce_action', 'koi_streamers_nonce_field');
            echo '</td>';
            echo '</form>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        $total_pages = ceil($total_items / $items_per_page);
        echo '<div class="tablenav"><div class="tablenav-pages">';
        if ($current_page > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page - 1], admin_url('admin.php?page=koi-streamers-edit'))) . '">Previous</a>';
        }
        if ($current_page < $total_pages) {
            echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page + 1], admin_url('admin.php?page=koi-streamers-edit'))) . '">Next</a>';
        }
        echo '</div></div>';
    } else {
        echo '<p>No streamers found.</p>';
    }

    echo '</div>';
}

function streamers_edit_entry_form_handler() {
    global $wpdb;
    $streamers_table = $wpdb->prefix . 'koi_streamers';

    if (isset($_POST['streamer_action']) && $_POST['streamer_action'] === 'edit_streamer') {
        if (!isset($_POST['koi_streamers_nonce_field']) || !wp_verify_nonce($_POST['koi_streamers_nonce_field'], 'koi_streamers_nonce_action')) {
            return;
        }

        $streamer_id = intval($_POST['streamer_id']);
        $name = sanitize_text_field($_POST['name']);
        $link = esc_url_raw($_POST['link']);
        $avatar_url = esc_url_raw($_POST['avatar_url']);

        $wpdb->update($streamers_table,
            ['name' => $name, 'link' => $link, 'avatar_url' => $avatar_url],
            ['id' => $streamer_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        echo '<div class="updated"><p>Streamer updated successfully!</p></div>';
    } else if (isset($_POST['streamer_action']) && $_POST['streamer_action'] === 'delete_streamer') {
        if (!isset($_POST['koi_streamers_nonce_field']) || !wp_verify_nonce($_POST['koi_streamers_nonce_field'], 'koi_streamers_nonce_action')) {
            return;
        }

        $streamer_id = intval($_POST['streamer_id']);

        $wpdb->delete($streamers_table, ['id' => $streamer_id], ['%d']);

        echo '<div class="updated"><p>Streamer deleted successfully!</p></div>';
    }
}

function streamers_add_menu_page() {
    add_menu_page(
        'Koi Streamers',
        'Koi Streamers',
        'manage_options',
        'koi_streamers',
        'streamers_entry_page',
        'dashicons-admin-users',
        6
    );

    add_submenu_page(
        'koi_streamers',
        'Edit Streamers',
        'Edit Streamers',
        'manage_options',
        'koi-streamers-edit',
        'streamers_edit_entry_page'
    );
}

function streamers_entry_page() {
    echo '<div class="wrap">';
    echo '<h1>Koi streamers form</h1>';
    echo streamers_entry_form();
    echo '</div>';
}

function streamers_edit_entry_page() {
    echo '<div class="wrap">';
    streamers_edit_entry_form();
    echo '</div>';
}

add_action('init', 'streamers_entry_form_handler');
add_action('init', 'streamers_edit_entry_form_handler');
add_action('admin_menu', 'streamers_add_menu_page');