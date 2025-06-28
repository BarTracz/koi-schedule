<?php

// Blokada bezpośredniego dostępu do pliku
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Tworzy tabelę eventów w bazie danych.
 */
function create_koi_events_table() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'koi_events';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            icon_url VARCHAR(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	if (!empty($wpdb->last_error)) {
		error_log('Database Error: ' . $wpdb->last_error);
	}

	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM $table_name WHERE name = %s", 'Normal'
	));
	if (!$exists) {
		$wpdb->insert($table_name, [
			'name' => 'Normal',
			'icon_url' => '',
		], [
			'%s',
			'%s'
		]);
	}
}

/**
 * Formularz dodawania nowego eventu.
 *
 * @return false|string
 */
function events_entry_form() {
	ob_start();
	?>
    <form method="post" action="" id="koi-events-form">
        <table>
            <tr>
                <th>Name</th>
            </tr>
            <tr>
                <td><input type="text" id="event_name" name="event_name" required></td>
            </tr>
            <tr>
                <th>Icon URL</th>
            </tr>
            <tr>
                <td><input type="url" id="icon_url" name="icon_url"></td>
            </tr>
        </table>
        <p>
            <input type="submit" name="submit_event" value="Add event" class="button button-primary">
        </p>
        <input type="hidden" name="event_action" value="add_event">
		<?php wp_nonce_field('event_nonce_action', 'event_nonce_field'); ?>
    </form>
	<?php
	return ob_get_clean();
}

/**
 * Obsługa formularza dodawania eventa.
 */
function events_entry_form_handler() {
	if (isset($_POST['event_action']) && $_POST['event_action'] === 'add_event') {
		if (!isset($_POST['event_nonce_field']) || !wp_verify_nonce($_POST['event_nonce_field'], 'event_nonce_action')) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-events'));
		}

		$name = sanitize_text_field($_POST['event_name']);
		$icon_url = esc_url_raw($_POST['icon_url']);

		// Walidacja URL
		if ($icon_url && !filter_var($icon_url, FILTER_VALIDATE_URL)) {
			echo '<div class="error"><p>' . esc_html__('Invalid icon URL.', 'koi-events') . '</p></div>';
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'koi_events';

		$wpdb->insert($table_name, [
			'name' => $name,
			'icon_url' => $icon_url
		], [
			'%s',
			'%s'
		]);

		if ($wpdb->insert_id) {
			echo '<div class="updated"><p>' . esc_html__('Event added successfully', 'koi-events') . '</p></div>';
		} else {
			error_log('Database Insert Error: ' . $wpdb->last_error);
			echo '<div class="error"><p>' . esc_html__('Failed to add event. Please try again.', 'koi-events') . '</p></div>';
		}
	}
}

/**
 * Formularz edycji i usuwania eventów (z paginacją).
 */
function events_edit_entry_form() {
	global $wpdb;
	$events_table = $wpdb->prefix . 'koi_events';

	$items_per_page = 10;
	$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$offset = ($current_page - 1) * $items_per_page;

	$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $events_table");

	$events = $wpdb->get_results($wpdb->prepare("
        SELECT id, name, icon_url, created_at
        FROM $events_table
        LIMIT %d OFFSET %d
    ", $items_per_page, $offset));

	echo '<div class="wrap">';
	echo '<h1>Edit events</h1>';

	if ($events) {
		echo '<table class="wp-list-table widefat striped">';
		echo '<thead><tr><th>Name</th><th>Icon URL</th><th>Actions</th></tr></thead>';
		echo '<tbody>';
		foreach ($events as $event) {
			echo '<tr>';
			echo '<form method="post" action="">';
			echo '<td><input type="text" name="name" value="' . esc_attr($event->name) . '" required></td>';
			echo '<td><input type="url" name="icon_url" value="' . esc_attr($event->icon_url) . '"></td>';
			echo '<td>';
			echo '<input type="hidden" name="event_id" value="' . esc_attr($event->id) . '">';
			echo '<button type="submit" name="event_action" value="edit_event" class="button button-primary">Update</button> ';
			echo '<button type="submit" name="event_action" value="delete_event" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this event?\');">Delete</button>';
			wp_nonce_field('koi_events_nonce_action', 'koi_events_nonce_field');
			echo '</td>';
			echo '</form>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		// Paginacja
		$total_pages = ceil($total_items / $items_per_page);
		echo '<div class="tablenav"><div class="tablenav-pages">';
		if ($current_page > 1) {
			echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page - 1], admin_url('admin.php?page=koi-events-edit'))) . '">Previous</a>';
		}
		if ($current_page < $total_pages) {
			echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page + 1], admin_url('admin.php?page=koi-events-edit'))) . '">Next</a>';
		}
		echo '</div></div>';
	} else {
		echo '<p>No events found.</p>';
	}

	echo '</div>';
}

/**
 * Obsługa formularza edycji i usuwania eventów.
 */
function events_edit_entry_form_handler() {
	global $wpdb;
	$events_table = $wpdb->prefix . 'koi_events';

	// Edycja eventa
	if (isset($_POST['event_action']) && $_POST['event_action'] === 'edit_event') {
		if (!isset($_POST['koi_events_nonce_field']) || !wp_verify_nonce($_POST['koi_events_nonce_field'], 'koi_events_nonce_action')) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-events'));
		}

		$event_id = intval($_POST['event_id']);
		$name = sanitize_text_field($_POST['name']);
		$icon_url = esc_url_raw($_POST['icon_url']);

		if ($icon_url && !filter_var($icon_url, FILTER_VALIDATE_URL)) {
			echo '<div class="error"><p>' . esc_html__('Invalid icon URL.', 'koi-events') . '</p></div>';
			return;
		}

		$result = $wpdb->update($events_table,
			['name' => $name, 'icon_url' => $icon_url],
			['id' => $event_id],
			['%s', '%s'],
			['%d']
		);

		if ($result !== false) {
			echo '<div class="updated"><p>' . esc_html__('Event updated successfully', 'koi-events') . '</p></div>';
		} else {
			echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-events') . esc_html($wpdb->last_error) . '</p></div>';
		}
	}
	// Usuwanie eventu
	else if (isset($_POST['event_action']) && $_POST['event_action'] === 'delete_event') {
		if (!isset($_POST['koi_events_nonce_field']) || !wp_verify_nonce($_POST['koi_events_nonce_field'], 'koi_events_nonce_action')) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-events'));
		}

		$event_id = intval($_POST['event_id']);

		$result = $wpdb->delete($events_table, ['id' => $event_id], ['%d']);

		if ($result !== false) {
			echo '<div class="updated"><p>' . esc_html__('Event deleted successfully', 'koi-events') . '</p></div>';
		} else {
			echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-events') . esc_html($wpdb->last_error) . '</p></div>';
		}
	}
}

/**
 * Dodaje pozycje menu do panelu administratora WordPressa.
 */
function events_add_menu_page() {
	add_menu_page(
		'Koi Events',
		'Koi Events',
		'manage_options',
		'koi_events',
		'events_entry_page',
		'dashicons-image-filter',
		2
	);

	add_submenu_page(
		'koi_events',
		'Edit Events',
		'Edit Events',
		'manage_options',
		'koi-events-edit',
		'events_edit_entry_page'
	);
}

/**
 * Strona formularza dodawania eventów.
 */
function events_entry_page() {
	echo '<div class="wrap">';
	echo '<h1>Koi events form</h1>';
	echo events_entry_form();
	echo '</div>';
}

/**
 * Strona edycji eventów.
 */
function events_edit_entry_page() {
	echo '<div class="wrap">';
	events_edit_entry_form();
	echo '</div>';
}

// Rejestracja akcji WordPressa
add_action('init', 'events_entry_form_handler');
add_action('init', 'events_edit_entry_form_handler');
add_action('admin_menu', 'events_add_menu_page');