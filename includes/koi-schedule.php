<?php

// Blokada bezpośredniego dostępu do pliku
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Tworzy tabelę harmonogramu w bazie danych.
 */
function create_koi_schedule_table(): void
{
	global $wpdb;

	$table_name = $wpdb->prefix . 'koi_schedule';
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        time DATETIME NOT NULL,
        streamer_id mediumint(9) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (streamer_id) REFERENCES {$wpdb->prefix}koi_streamers(id) ON DELETE CASCADE
    ) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	if (!empty($wpdb->last_error)) {
		error_log('Database Error: ' . $wpdb->last_error);
	}
}

function update_koi_schedule_table(): void
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'koi_schedule';
	$events_table = $wpdb->prefix . 'koi_events';

	// Sprawdzenie, czy kolumna 'event_id' istnieje
	if ($wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'event_id'") === null) {
		$wpdb->query("ALTER TABLE $table_name ADD COLUMN event_id mediumint(9) NOT NULL DEFAULT 1");
		$wpdb->query("ALTER TABLE $table_name ADD FOREIGN KEY (event_id) REFERENCES $events_table(id) ON DELETE RESTRICT");
	}

	if (!empty($wpdb->last_error)) {
		error_log('Database Error: ' . $wpdb->last_error);
	}
}

/**
 * Formularz dodawania wpisów do harmonogramu.
 *
 * @return false|string
 */
function schedule_entry_form(): false|string
{
	global $wpdb;
	$streamers_table = $wpdb->prefix . 'koi_streamers';
    $events_table = $wpdb->prefix . 'koi_events';
	$streamers = $wpdb->get_results("SELECT * FROM $streamers_table");
    $events = $wpdb->get_results("SELECT id, name FROM $events_table");

	ob_start();
	?>
    <form method="post" action="" id="koi-schedule-form">
        <table>
            <tr>
                <th>Streamer</th>
            </tr>
            <tr>
                <td>
                    <select id="streamer_id" name="streamer_id" required>
                        <option value="">Select a streamer</option>
						<?php foreach ($streamers as $streamer): ?>
                            <option value="<?php echo esc_attr($streamer->id); ?>">
								<?php echo esc_html($streamer->name); ?>
                            </option>
						<?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <div id="schedule-entries">
            <table class="schedule-entry">
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Event</th>
                    <th>Action</th>
                </tr>
                <tr>
                    <td><input type="date" id="date_0" name="schedule_entries[0][date]" required></td>
                    <td><input type="time" id="time_0" name="schedule_entries[0][time]" required></td>
                    <td>
                        <select name="schedule_entries[0][event_id]">
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo esc_attr($event->id); ?>">
                                    <?php echo esc_html($event->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><button type="button" class="remove-entry button button-secondary">Remove</button></td>
                </tr>
            </table>
        </div>
        <p>
            <button type="button" id="add-entry" class="button button-secondary">Add another entry</button>
        </p>
        <p>
            <input type="submit" name="submit" value="Submit" class="button button-primary">
        </p>
        <input type="hidden" name="schedule_action" value="add_schedule">
		<?php wp_nonce_field('koi_schedule_nonce_action', 'koi_schedule_nonce_field'); ?>
    </form>
    <script>
        // Dodawanie kolejnych wpisów do harmonogramu (JS)
        document.getElementById('add-entry').addEventListener('click', function() {
            const container = document.getElementById('schedule-entries');
            const index = container.querySelectorAll('table.schedule-entry').length;
            const entry = document.createElement('table');
            entry.classList.add('schedule-entry');
            entry.innerHTML = `
            <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Event</th>
                <th>Action</th>
            </tr>
            <tr>
                <td><input type="date" id="date_${index}" name="schedule_entries[${index}][date]" required></td>
                <td><input type="time" id="time_${index}" name="schedule_entries[${index}][time]" required></td>
                <td>
                    <select name="schedule_entries[${index}][event_id]">
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo esc_attr($event->id); ?>">
                                <?php echo esc_html($event->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <td><button type="button" class="remove-entry button button-secondary">Remove</button></td>
            </tr>
        `;
            container.appendChild(entry);

            entry.querySelector('.remove-entry').addEventListener('click', function() {
                container.removeChild(entry);
            });
        });

        // Usuwanie wpisu z harmonogramu (JS)
        document.querySelectorAll('.remove-entry').forEach(button => {
            button.addEventListener('click', function() {
                const container = document.getElementById('schedule-entries');
                const entry = button.closest('table.schedule-entry');
                container.removeChild(entry);
            });
        });
    </script>
	<?php
	return ob_get_clean();
}

/**
 * Obsługa formularza dodawania wpisów do harmonogramu.
 */
function schedule_entry_form_handler(): void
{
	if (
		isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'add_schedule'
	) {
		if (
			!isset($_POST['koi_schedule_nonce_field']) ||
			!wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
		) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-schedule'));
		}

		$streamer_id = intval($_POST['streamer_id']);
		$schedule_entries = $_POST['schedule_entries'];

		global $wpdb;
		$table_name = $wpdb->prefix . 'koi_schedule';
		$success = false;

		foreach ($schedule_entries as $entry) {
			$date = sanitize_text_field($entry['date']);
			$time = sanitize_text_field($entry['time']);
			// Walidacja daty i czasu
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
				echo '<div class="error"><p>' . esc_html__('Invalid date/time format.', 'koi-schedule') . '</p></div>';
				continue;
			}

			$datetime = $date . ' ' . $time;

			$event_id = isset($entry['event_id']) ? intval($entry['event_id']) : null;
			$wpdb->insert($table_name, array(
				'time' => $datetime,
				'streamer_id' => $streamer_id,
				'event_id' => $event_id
			), array(
				'%s',
				'%d',
				'%d'
			));
			if ($wpdb->insert_id) {
				$success = true;
			}
		}
		if ($success) {
			echo '<div class="updated"><p>' . esc_html__('Entry added successfully', 'koi-schedule') . '</p></div>';
		} else {
			error_log('Database Insert Error: ' . $wpdb->last_error);
			echo '<div class="error"><p>' . esc_html__('Failed to add entry. Please try again.', 'koi-schedule') . '</p></div>';
		}
	}
}

/**
 * Formularz edycji i usuwania wpisów harmonogramu (z paginacją i sortowaniem).
 */
function schedule_edit_entry_form(): void
{
	global $wpdb;
	$schedule_table = $wpdb->prefix . 'koi_schedule';
	$streamers_table = $wpdb->prefix . 'koi_streamers';
	$events_table = $wpdb->prefix . 'koi_events';
	$events = $wpdb->get_results("SELECT id, name FROM $events_table");

	$items_per_page = 30;
	$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$offset = ($current_page - 1) * $items_per_page;

	$sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'time';
	$order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

	$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $schedule_table");

	$entries = $wpdb->get_results($wpdb->prepare("
        SELECT s.id, s.time, s.streamer_id, s.event_id, st.name AS streamer_name
        FROM $schedule_table s
        INNER JOIN $streamers_table st ON s.streamer_id = st.id
        ORDER BY $sort_by $order
        LIMIT %d OFFSET %d
    ", $items_per_page, $offset));

	$streamers = $wpdb->get_results("SELECT id, name FROM $streamers_table");

	echo '<div class="wrap">';
	echo '<h1>Edit schedule entries</h1>';

	echo '<form method="post" action="">';
	echo '<p>Sort by: ';
	echo '<a href="' . esc_url(add_query_arg(['sort_by' => 'streamer_name', 'order' => $order === 'ASC' ? 'desc' : 'asc'])) . '">Name</a> | ';
	echo '<a href="' . esc_url(add_query_arg(['sort_by' => 'time', 'order' => $order === 'ASC' ? 'desc' : 'asc'])) . '">Date</a>';
	echo '</p>';

	if ($entries) {
		echo '<table class="wp-list-table widefat striped">';
		echo '<thead><tr><th>Streamer</th><th>Date</th><th>Time</th><th>Event</th><th>Actions</th></tr></thead>';
		echo '<tbody>';
		foreach ($entries as $entry) {
			$date = esc_attr(date('Y-m-d', strtotime($entry->time)));
			$time = esc_attr(date('H:i', strtotime($entry->time)));
			echo '<tr>';
			echo '<form method="post" action="">';
			echo '<td>';
			echo '<select name="streamer_id" required>';
			foreach ($streamers as $streamer) {
				$selected = $streamer->id == $entry->streamer_id ? 'selected' : '';
				echo '<option value="' . esc_attr($streamer->id) . '" ' . $selected . '>' . esc_html($streamer->name) . '</option>';
			}
			echo '</select>';
			echo '</td>';
			echo '<td><input type="date" name="date" value="' . $date . '" required></td>';
			echo '<td><input type="time" name="time" value="' . $time . '" required></td>';
			echo '<td><select name="event_id">';
			foreach ($events as $event) {
				$selected = ($event->id == $entry->event_id) ? 'selected' : '';
				echo '<option value="' . esc_attr($event->id) . '" ' . $selected . '>' . esc_html($event->name) . '</option>';
			}
			echo '</select></td>';
			echo '<td>';
			echo '<input type="hidden" name="entry_id" value="' . esc_attr($entry->id) . '">';
			echo '<button type="submit" name="schedule_action" value="edit_schedule" class="button button-primary">Update</button> ';
			echo '<button type="submit" name="schedule_action" value="delete_schedule" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete this entry?\');">Delete</button>';
			wp_nonce_field('koi_schedule_nonce_action', 'koi_schedule_nonce_field');
			echo '</td>';
			echo '</form>';
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';

		// Usuwanie wpisów starszych niż wybrana data
		echo '<p>';
		echo '<label for="delete-older-date">Delete entries older than:</label> ';
		echo '<input type="date" id="delete-older-date" name="delete_older_date">';
		echo '</p>';
		echo '<p>';
		echo '<button type="submit" name="schedule_action" value="delete_older" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete entries older than the selected date?\');">Delete</button>';
		echo '</p>';

		// Paginacja
		$total_pages = ceil($total_items / $items_per_page);
		echo '<div class="tablenav"><div class="tablenav-pages">';
		if ($current_page > 1) {
			echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page - 1], admin_url('admin.php?page=koi-schedule-edit'))) . '">Previous</a>';
		}
		if ($current_page < $total_pages) {
			echo '<a class="button" href="' . esc_url(add_query_arg(['paged' => $current_page + 1], admin_url('admin.php?page=koi-schedule-edit'))) . '">Next</a>';
		}
		echo '</div></div>';
	} else {
		echo '<p>No entries found.</p>';
	}

	echo '</form>';
	echo '</div>';
}

/**
 * Obsługa formularza edycji i usuwania wpisów harmonogramu.
 */
function schedule_edit_entry_form_handler(): void
{
	global $wpdb;
	$schedule_table = $wpdb->prefix . 'koi_schedule';

	// Usuwanie wpisów starszych niż wybrana data
	if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'delete_older') {
		if (
			!isset($_POST['koi_schedule_nonce_field']) ||
			!wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
		) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-schedule'));
		}

		$delete_older_date = sanitize_text_field($_POST['delete_older_date']);
		if ($delete_older_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $delete_older_date)) {
			$result = $wpdb->query($wpdb->prepare(
				"DELETE FROM $schedule_table WHERE time < %s",
				$delete_older_date . ' 00:00:00'
			));
			if ($result !== false) {
				echo '<div class="updated"><p>' . esc_html__('Entries older than ', 'koi-schedule') . esc_html($delete_older_date) . esc_html__(' deleted successfully', 'koi-schedule') . '</p></div>';
			} else {
				echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-schedule') . esc_html($wpdb->last_error) . '</p></div>';
			}
		} else {
			echo '<div class="error"><p>' . esc_html__('Please select a valid date.', 'koi-schedule') . '</p></div>';
		}
	}

	// Edycja wpisu
	if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'edit_schedule') {
		if (
			!isset($_POST['koi_schedule_nonce_field']) ||
			!wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
		) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-schedule'));
		}

		$entry_id = intval($_POST['entry_id']);
		$streamer_id = intval($_POST['streamer_id']);
		$date = sanitize_text_field($_POST['date']);
		$time = sanitize_text_field($_POST['time']);
		$datetime = $date . ' ' . $time;
		$event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : null;
		$result = $wpdb->update($schedule_table,
			['time' => $datetime, 'streamer_id' => $streamer_id, 'event_id' => $event_id],
			['id' => $entry_id],
			['%s', '%d', '%d'],
            ['%d']
        );


		if ($result !== false) {
			echo '<div class="updated"><p>' . esc_html__('Schedule entry updated successfully', 'koi-schedule') . '</p></div>';
		} else {
			echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-schedule') . esc_html($wpdb->last_error) . '</p></div>';
		}
	}

	// Usuwanie pojedynczego wpisu
	if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'delete_schedule') {
		if (
			!isset($_POST['koi_schedule_nonce_field']) ||
			!wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
		) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-schedule'));
		}

		$entry_id = intval($_POST['entry_id']);

		$result = $wpdb->delete(
			$schedule_table,
			['id' => $entry_id],
			['%d']
		);

		if ($result !== false) {
			echo '<div class="updated"><p>' . esc_html__('Schedule entry deleted successfully', 'koi-schedule') . '</p></div>';
		} else {
			echo '<div class="error"><p>' . esc_html__('Database error: ', 'koi-schedule') . esc_html($wpdb->last_error) . '</p></div>';
		}
	}
}

/**
 * Dodaje pozycje menu do panelu administratora WordPressa.
 */
function schedule_add_menu_page(): void
{
	add_menu_page(
		'Add schedule entries',
		'Koi Schedule',
		'manage_options',
		'koi_schedule',
		'schedule_entry_page',
		'dashicons-calendar',
		2
	);

	add_submenu_page(
		'koi_schedule',
		'Edit schedule entries',
		'Edit schedule entries',
		'manage_options',
		'koi-schedule-edit',
		'schedule_edit_entry_page'
	);
}

/**
 * Strona formularza dodawania wpisów.
 */
function schedule_entry_page(): void
{
	echo '<div class="wrap">';
	echo '<h1>Koi schedule form</h1>';
	echo schedule_entry_form();
	echo '</div>';
}

/**
 * Strona edycji wpisów harmonogramu.
 */
function schedule_edit_entry_page(): void
{
	echo '<div class="wrap">';
	schedule_edit_entry_form();
	echo '</div>';
}

// Rejestracja akcji WordPressa
add_action('init', 'schedule_entry_form_handler');
add_action('init', 'schedule_edit_entry_form_handler');
add_action('admin_menu', 'schedule_add_menu_page');