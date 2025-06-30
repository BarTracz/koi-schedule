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
                    <td>
                        <div>
                            <label><input type="radio" name="schedule_entries[0][time_radio]" value="12:00" checked>12:00</label>
                            <label><input type="radio" name="schedule_entries[0][time_radio]" value="16:00">16:00</label>
                            <label><input type="radio" name="schedule_entries[0][time_radio]" value="20:00">20:00</label>
                            <label>
                                <input type="radio" name="schedule_entries[0][time_radio]" value="other"> Other:
                                <input type="number" class="koi-number-field" id="hour_0" name="schedule_entries[0][hour]" min="0" max="23" disabled placeholder="hh">
                                :
                                <input type="number" class="koi-number-field" id="minute_0" name="schedule_entries[0][minute]" min="0" max="59" disabled placeholder="mm">
                            </label>
                        </div>
                        <script>
                            document.querySelectorAll('input[name="schedule_entries[0][time_radio]"]').forEach(radio => {
                                radio.addEventListener('change', function() {
                                    const hourInput = document.getElementById('hour_0');
                                    const minuteInput = document.getElementById('minute_0');
                                    if (this.value === 'other') {
                                        hourInput.disabled = false;
                                        hourInput.required = true;
                                        minuteInput.disabled = false;
                                        minuteInput.required = true;
                                    } else {
                                        hourInput.disabled = true;
                                        hourInput.required = false;
                                        hourInput.value = '';
                                        minuteInput.disabled = true;
                                        minuteInput.required = false;
                                        minuteInput.value = '';
                                    }
                                });
                            });
                        </script>
                    </td>
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
        // Funkcja do obsługi pola "Inna godzina" w harmonogramie
        function attachOtherTimeHandler(entry, index) {
            const timeRadios = entry.querySelectorAll(`input[name="schedule_entries[${index}][time_radio]"]`);
            const hourInput = entry.querySelector(`#hour_${index}`);
            const minuteInput = entry.querySelector(`#minute_${index}`);

            timeRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'other') {
                        hourInput.disabled = false;
                        hourInput.required = true;
                        minuteInput.disabled = false;
                        minuteInput.required = true;
                    } else {
                        hourInput.disabled = true;
                        hourInput.required = false;
                        hourInput.value = '';
                        minuteInput.disabled = true;
                        minuteInput.required = false;
                        minuteInput.value = '';
                    }
                });
            });
        }

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
                <td>
                <div>
                    <label><input type="radio" name="schedule_entries[${index}][time_radio]" value="12:00" checked>12:00</label>
                    <label><input type="radio" name="schedule_entries[${index}][time_radio]" value="16:00">16:00</label>
                    <label><input type="radio" name="schedule_entries[${index}][time_radio]" value="20:00">20:00</label>
                    <label>
                        <input type="radio" name="schedule_entries[${index}][time_radio]" value="other"> Other:
                        <input type="number" class="koi-number-field" id="hour_${index}" name="schedule_entries[${index}][hour]" min="0" max="23" disabled placeholder="hh">
                        :
                        <input type="number" class="koi-number-field" id="minute_${index}" name="schedule_entries[${index}][minute]" min="0" max="59" disabled placeholder="mm">
                    </label>
                </div>
                </td>
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

            attachOtherTimeHandler(entry, index);

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

        document.addEventListener('DOMContentLoaded', function() {
            attachOtherTimeHandler(document, 0);
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
			// Pobierz czas z radio lub z pól hour/minute
			if (isset($entry['time_radio'])) {
				if ($entry['time_radio'] === 'other') {
					$hour = isset($entry['hour']) ? intval($entry['hour']) : null;
					$minute = isset($entry['minute']) ? intval($entry['minute']) : null;
					if ($hour === null || $minute === null || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
						echo '<div class="error"><p>' . esc_html__('Invalid hour or minute.', 'koi-schedule') . '</p></div>';
						continue;
					}
					$time = sprintf('%02d:%02d', $hour, $minute);
				} else {
					$time = sanitize_text_field($entry['time_radio']);
				}
			} else {
				$time = '00:00';
			}
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
	$streamers = $wpdb->get_results("SELECT id, name FROM $streamers_table");

	$items_per_page = 30;
	$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
	$offset = ($current_page - 1) * $items_per_page;

	$sort_by = isset($_GET['sort_by']) ? sanitize_text_field($_GET['sort_by']) : 'time';
	$order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

	$filter_streamer = isset($_GET['filter_streamer']) ? intval($_GET['filter_streamer']) : 0;
	$filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
	$filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';

	$where = [];
	$params = [];
	if ($filter_streamer) {
		$where[] = 's.streamer_id = %d';
		$params[] = $filter_streamer;
	}
	if ($filter_date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_from)) {
		$where[] = 's.time >= %s';
		$params[] = $filter_date_from . ' 00:00:00';
	}
	if ($filter_date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date_to)) {
		$where[] = 's.time <= %s';
		$params[] = $filter_date_to . ' 23:59:59';
	}
	$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

	$total_items = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(*) FROM $schedule_table s INNER JOIN $streamers_table st ON s.streamer_id = st.id $where_sql",
		...$params
	));

	$entries = $wpdb->get_results($wpdb->prepare(
		"SELECT s.id, s.time, s.streamer_id, s.event_id, st.name AS streamer_name
        FROM $schedule_table s
        INNER JOIN $streamers_table st ON s.streamer_id = st.id
        $where_sql
        ORDER BY $sort_by $order
        LIMIT %d OFFSET %d",
		...array_merge($params, [$items_per_page, $offset])
	));

	$streamers = $wpdb->get_results("SELECT id, name FROM $streamers_table");

	echo '<div class="wrap">';
	echo '<h1>Edit schedule entries</h1>';

	echo '<form method="get" action="">';
	echo '<input type="hidden" name="page" value="koi-schedule-edit">';
    echo '<div class="koi-admin-filters">';
	echo '<label for="filter_streamer">Streamer: </label>';
	echo '<select name="filter_streamer" id="filter_streamer">';
	echo '<option value="0">-- All --</option>';
	foreach ($streamers as $streamer) {
		$selected = $filter_streamer == $streamer->id ? 'selected' : '';
		echo '<option value="' . esc_attr($streamer->id) . '" ' . $selected . '>' . esc_html($streamer->name) . '</option>';
	}

	echo '</select> ';
	echo '<label for="filter_date_from">From: </label>';
	echo '<input type="date" name="filter_date_from" id="filter_date_from" value="' . esc_attr($filter_date_from) . '"> ';
	echo '<label for="filter_date_to">To: </label>';
	echo '<input type="date" name="filter_date_to" id="filter_date_to" value="' . esc_attr($filter_date_to) . '"> ';
	echo '<input type="submit" class="button" value="Filter">';
	echo '</form>';
    echo '</div>';

	echo '<form method="post" action="">';
	echo '<p>Sort by: ';
	echo '<a href="' . esc_url(add_query_arg(['sort_by' => 'streamer_name', 'order' => $order === 'ASC' ? 'desc' : 'asc'])) . '">Name</a> | ';
	echo '<a href="' . esc_url(add_query_arg(['sort_by' => 'time', 'order' => $order === 'ASC' ? 'desc' : 'asc'])) . '">Date</a>';
	echo '</p>';

	if ($entries) {
		echo '<form method="post" action="">';
		echo '<table class="wp-list-table widefat striped">';
		echo '<thead><tr><th><input type="checkbox" id="select-all"></th><th>Streamer</th><th>Date</th><th>Time</th><th>Event</th><th>Actions</th></tr></thead>';
		echo '<tbody>';
		foreach ($entries as $entry) {
			$date = esc_attr(date('Y-m-d', strtotime($entry->time)));
			$time = esc_attr(date('H:i', strtotime($entry->time)));
			echo '<tr>';
			echo '<td><input type="checkbox" name="bulk_ids[]" value="' . esc_attr($entry->id) . '"></td>';
			echo '<td>';
			echo '<select name="streamer_id" required>';
			foreach ($streamers as $streamer) {
				$selected = $streamer->id == $entry->streamer_id ? 'selected' : '';
				echo '<option value="' . esc_attr($streamer->id) . '" ' . $selected . '>' . esc_html($streamer->name) . '</option>';
			}
			echo '</select>';
			echo '</td>';
			echo '<td><input type="date" name="date" value="' . $date . '" required></td>';
			echo '<td>
                <input type="number" name="hour" min="0" max="23" value="' . esc_attr(date('H', strtotime($entry->time))) . '" required placeholder="hh">
                :
                <input type="number" name="minute" min="0" max="59" value="' . esc_attr(date('i', strtotime($entry->time))) . '" required placeholder="mm">
            </td>';
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

		echo '<div class="koi-admin-filters">';
		echo '<label>Edit selected:</label> ';
		echo '<select name="bulk_streamer_id"><option value="">--no changes--</option>';
		foreach ($streamers as $streamer) {
			echo '<option value="' . esc_attr($streamer->id) . '">' . esc_html($streamer->name) . '</option>';
		}
		echo '</select> ';
		echo '<input type="date" name="bulk_date"> ';
		echo '<input type="number" class="koi-number-field" name="bulk_hour" min="0" max="23" placeholder="hh">:';
		echo '<input type="number" class="koi-number-field" name="bulk_minute" min="0" max="59" placeholder="mm"> ';
		echo '<select name="bulk_event_id"><option value="">--no changes--</option>';
		foreach ($events as $event) {
			echo '<option value="' . esc_attr($event->id) . '">' . esc_html($event->name) . '</option>';
		}
		echo '</select> ';
		echo '<button type="submit" name="schedule_action" value="bulk_edit" class="button button-primary">Update selected</button>';
		wp_nonce_field('koi_schedule_nonce_action', 'koi_schedule_nonce_field');
		echo '</div>';
		echo '</form>';

		echo "<script>
            document.getElementById('select-all').addEventListener('change', function() {
            document.querySelectorAll('input[name=\"bulk_ids[]\"]').forEach(cb => cb.checked = this.checked);
            });
        </script>";

		// Usuwanie wpisów starszych niż wybrana data
		echo '<div class="koi-admin-filters">';
		echo '<label for="delete-date">Delete entries: </label>';
		echo '<select name="delete_direction" id="delete-direction">';
		echo '<option value="older">older than</option>';
		echo '<option value="newer">newer than</option>';
		echo '</select> ';
		echo '<input type="date" id="delete-date" name="delete_older_date">';
		echo '<p>';
		echo '<button type="submit" name="schedule_action" value="delete_older" class="button button-secondary" onclick="return confirm(\'Are you sure you want to delete entries?\');">Delete</button>';
		echo '</p>';
        echo '</div>';

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
		$delete_direction = isset($_POST['delete_direction']) ? $_POST['delete_direction'] : 'older';
		$operator = $delete_direction === 'newer' ? '>' : '<';

		if ($delete_older_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $delete_older_date)) {
			$result = $wpdb->query($wpdb->prepare(
				"DELETE FROM $schedule_table WHERE time $operator %s",
				$delete_older_date . ' 00:00:00'
			));
			if ($result !== false) {
				$msg = $delete_direction === 'newer'
					? esc_html__('Entries newer than ', 'koi-schedule')
					: esc_html__('Entries older than ', 'koi-schedule');
				echo '<div class="updated"><p>' . $msg . esc_html($delete_older_date) . esc_html__(' deleted successfully', 'koi-schedule') . '</p></div>';
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
		$hour = isset($_POST['hour']) ? intval($_POST['hour']) : null;
		$minute = isset($_POST['minute']) ? intval($_POST['minute']) : null;
		if ($hour === null || $minute === null || $hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
			echo '<div class="error"><p>' . esc_html__('Invalid hour or minute.', 'koi-schedule') . '</p></div>';
			return;
		}
		$time = sprintf('%02d:%02d', $hour, $minute);
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

	if (isset($_POST['schedule_action']) && $_POST['schedule_action'] === 'bulk_edit') {
		if (
			!isset($_POST['koi_schedule_nonce_field']) ||
			!wp_verify_nonce($_POST['koi_schedule_nonce_field'], 'koi_schedule_nonce_action')
		) {
			return;
		}
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('No permissions.', 'koi-schedule'));
		}
		$ids = isset($_POST['bulk_ids']) ? array_map('intval', $_POST['bulk_ids']) : [];
		if (!$ids) {
			echo '<div class="error"><p>No entries selected.</p></div>';
			return;
		}
		$fields = [];
		if (!empty($_POST['bulk_streamer_id'])) $fields['streamer_id'] = intval($_POST['bulk_streamer_id']);
		if (!empty($_POST['bulk_event_id'])) $fields['event_id'] = intval($_POST['bulk_event_id']);

		$bulk_date = !empty($_POST['bulk_date']) ? sanitize_text_field($_POST['bulk_date']) : '';
		$bulk_hour = isset($_POST['bulk_hour']) && $_POST['bulk_hour'] !== '' ? intval($_POST['bulk_hour']) : null;
		$bulk_minute = isset($_POST['bulk_minute']) && $_POST['bulk_minute'] !== '' ? intval($_POST['bulk_minute']) : null;

		if ($bulk_date !== '' || $bulk_hour !== null || $bulk_minute !== null) {
			global $wpdb;
			foreach ($ids as $id) {
				$current_time = $wpdb->get_var($wpdb->prepare(
					"SELECT time FROM {$wpdb->prefix}koi_schedule WHERE id = %d", $id
				));
				if (!$current_time) continue;
				$dt = new DateTime($current_time);

				// Zmień datę, jeśli podano
				if ($bulk_date !== '') {
					$dt->setDate(
						(int)substr($bulk_date, 0, 4),
						(int)substr($bulk_date, 5, 2),
						(int)substr($bulk_date, 8, 2)
					);
				}
				// Zmień godzinę, jeśli podano
				if ($bulk_hour !== null && $bulk_minute !== null) {
					$dt->setTime($bulk_hour, $bulk_minute, 0);
				}
				$fields['time'] = $dt->format('Y-m-d H:i:s');
				$wpdb->update($wpdb->prefix . 'koi_schedule', $fields, ['id' => $id]);
			}
			echo '<div class="updated"><p>Group edit finished.</p></div>';
			return;
		}

		if ($fields) {
			global $wpdb;
			foreach ($ids as $id) {
				$wpdb->update($wpdb->prefix . 'koi_schedule', $fields, ['id' => $id]);
			}
			echo '<div class="updated"><p>Group edit finished.</p></div>';
		} else {
			echo '<div class="error"><p>No changes chosen.</p></div>';
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

/**
 * Rejestruje skrypty i style dla strony administracyjnej harmonogramu.
 */
function koi_schedule_enqueue_admin_styles(): void {
	wp_enqueue_style(
		'koi-schedule-style',
		plugins_url('css/koi-schedule.css', __FILE__)
	);
	wp_enqueue_style('dashicons');
}

// Rejestracja akcji WordPressa
add_action('admin_enqueue_scripts', 'koi_schedule_enqueue_admin_styles');
add_action('init', 'schedule_entry_form_handler');
add_action('init', 'schedule_edit_entry_form_handler');
add_action('admin_menu', 'schedule_add_menu_page');