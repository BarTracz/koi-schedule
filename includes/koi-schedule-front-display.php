<?php

if ( ! defined('ABSPATH') ) {
    exit;
}

date_default_timezone_set('Europe/Warsaw');

function display_schedule(): false|string {
	global $wpdb;
	$schedule_table  = $wpdb->prefix . 'koi_schedule';
	$streamers_table = $wpdb->prefix . 'koi_streamers';

	$week_offset = isset( $_GET['week_offset'] ) ? intval( $_GET['week_offset'] ) : 0;

	$start_of_week = date( 'Y-m-d', strtotime("monday this week $week_offset week"));
	$end_of_week   = date( 'Y-m-d', strtotime("sunday this week $week_offset week"));

	$query = $wpdb->prepare(
		"
    SELECT
        DATE_FORMAT(s.time, '%%H:%%i') AS time_formatted,
        DATE_FORMAT(s.time, '%%Y-%%m-%%d') AS date_formatted,
        st.name AS streamer_name,
        st.link AS streamer_link,
        st.avatar_url AS streamer_avatar_url
    FROM {$schedule_table} s
    INNER JOIN {$streamers_table} st ON s.streamer_id = st.id
    WHERE DATE(s.time) BETWEEN %s AND %s
    ORDER BY s.time ASC
    ",
		$start_of_week,
		$end_of_week
	);

	$results = $wpdb->get_results($query);

	$grouped_schedule = [];
	$days_of_week     = [
		'Monday'    => 'Poniedziałek',
		'Tuesday'   => 'Wtorek',
		'Wednesday' => 'Środa',
		'Thursday'  => 'Czwartek',
		'Friday'    => 'Piątek',
		'Saturday'  => 'Sobota',
		'Sunday'    => 'Niedziela',
	];

	foreach ( $results as $row ) {
		$datetime = new DateTime($row->date_formatted);
		$day_en   = $datetime->format('l');
		$day_pl   = $days_of_week[$day_en];
		$hour     = $row->time_formatted;

		$date_for_day_display = $datetime->format('d.m');

		if (!isset($grouped_schedule[$day_pl])) {
			$grouped_schedule[$day_pl] = [
				'display_date' => $date_for_day_display,
				'hours'        => [],
			];
		}
		if (!isset( $grouped_schedule[$day_pl]['hours'][$hour])) {
			$grouped_schedule[ $day_pl ]['hours'][ $hour ] = [];
		}

		$grouped_schedule[$day_pl]['hours'][$hour][] = [
			'name'       => esc_html( $row->streamer_name ),
			'link'       => esc_url( $row->streamer_link ),
			'avatar_url' => esc_url( $row->streamer_avatar_url ),
		];
	}


	ob_start();
	echo '<div class="koi-schedule-container">';
	$current_url = esc_url(add_query_arg(null, null));
	echo '<div class="koi-schedule-date">';
	echo '<a class="koi-schedule-date-arrow" href="' . esc_url(add_query_arg('week_offset', $week_offset - 1, $current_url)) . '" style="text-decoration: none;">';
	echo '<span class="dashicons dashicons-arrow-left-alt2"></span>';
	echo '</a>';
	echo '<p class="koi-schedule-date-range">' . $start_of_week . ' - ' . $end_of_week . '</p>';
	echo '<a class="koi-schedule-date-arrow" href="' . esc_url(add_query_arg('week_offset', $week_offset + 1, $current_url)) . '" style="text-decoration: none;">';
	echo '<span class="dashicons dashicons-arrow-right-alt2"></span>';
	echo '</a>';
	echo '</div>';


	if (!empty($grouped_schedule)) {
		echo '<div class="koi-schedule-week">';
		foreach ($grouped_schedule as $day_name_pl => $day_data) {
			$day_display_date = $day_data['display_date'];
			$hours_of_day = $day_data['hours'];

			echo '<div class="koi-schedule-day">';
			echo '<h3 class="koi-schedule-day-title">' . esc_html(mb_strtoupper($day_name_pl, 'UTF-8')) . ' - ' . esc_html($day_display_date) . '</h3>';

			ksort($hours_of_day);

			foreach ($hours_of_day as $hour_key => $streamers_in_slot) {
				echo '<div class="koi-schedule-time-slot">';
				echo '<h4 class="koi-schedule-hour">' . esc_html($hour_key) . '</h4>';
				echo '<div class="koi-streamer-list">';

				$streamer_counter = 0;
				$total_streamers_this_slot = count($streamers_in_slot);

				foreach ($streamers_in_slot as $streamer) {
					if ($streamer_counter % 2 === 0) {
						if ($streamer_counter > 0) {
							echo '</div>';
						}
						echo '<div class="koi-streamer-row">';
					}

					$style = ($total_streamers_this_slot === 1) ? 'style="width: calc(50% - 5px);"' : '';

					echo '<div class="koi-schedule-streamer" ' . $style . '>';
					echo '<a href="' . esc_url($streamer['link']) . '" target="_blank">';
					echo '<span class="koi-streamer-avatar" style="background-image: url(\'' . esc_url($streamer['avatar_url']) . '\');"></span>';
					echo '</a>';
					echo '</div>';

					$streamer_counter++;
				}

				if ($streamer_counter > 0) {
					echo '</div>';
				}

				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	} else {
		echo '<div class="koi-schedule-no-streamers">';
		echo '<p>Brak zaplanowanych streamów w tym tygodniu.</p>';
		echo '</div>';
	}

	return ob_get_clean();
}

add_shortcode( 'koi_schedule_display', 'display_schedule' );

function enqueue_koi_schedule_styles(): void
{
    wp_enqueue_style(
        'koi-schedule-style',
        plugins_url('css/koi-schedule.css', __FILE__)
    );
}
add_action('wp_enqueue_scripts', 'enqueue_koi_schedule_styles');