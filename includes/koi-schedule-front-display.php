<?php

if ( ! defined('ABSPATH') ) {
    exit;
}

date_default_timezone_set('Europe/Warsaw');

function display_schedule() {
    global $wpdb;
    $schedule_table = $wpdb->prefix . 'koi_schedule';
    $streamers_table = $wpdb->prefix . 'koi_streamers';
    $start_of_week = date('Y-m-d', strtotime('monday this week'));
    $end_of_week = date('Y-m-d', strtotime('sunday this week'));

    $results = $wpdb->get_results("
    SELECT
        DATE_FORMAT(s.time, '%H:%i') AS time_formatted,
        DATE_FORMAT(s.time, '%Y-%m-%d') AS date_formatted,
        st.name AS streamer_name,
        st.link AS streamer_link,
        st.avatar_url AS streamer_avatar_url
    FROM $schedule_table s
    INNER JOIN $streamers_table st ON s.streamer_id = st.id
    WHERE DATE(s.time) BETWEEN '$start_of_week' AND '$end_of_week'
    ORDER BY s.time ASC
");

    $grouped_schedule = [];
    $days_of_week = [
        'Monday'    => 'Poniedziałek',
        'Tuesday'   => 'Wtorek',
        'Wednesday' => 'Środa',
        'Thursday'  => 'Czwartek',
        'Friday'    => 'Piątek',
        'Saturday'  => 'Sobota',
        'Sunday'    => 'Niedziela',
    ];

    foreach ($results as $row) {
        $datetime = new DateTime($row->date_formatted);
        $day_en = $datetime->format('l');
        $day_pl = $days_of_week[$day_en];
        $hour = $row->time_formatted;

        if (!isset($grouped_schedule[$day_pl])) {
            $grouped_schedule[$day_pl] = [];
        }
        if (!isset($grouped_schedule[$day_pl][$hour])) {
            $grouped_schedule[$day_pl][$hour] = [];
        }

        $grouped_schedule[$day_pl][$hour][] = [
            'name' => esc_html($row->streamer_name),
            'link' => esc_url($row->streamer_link),
            'avatar_url' => esc_url($row->streamer_avatar_url),
        ];
    }

    ob_start();
    echo '<div class="koi-schedule-container">';
    echo '<p class="koi-schedule-date-range"><span class="dashicons dashicons-calendar-alt"></span> ' . $start_of_week . ' - ' . $end_of_week . '</p>';

    if (!empty($grouped_schedule)) {
        foreach ($grouped_schedule as $day => $hours) {
            echo '<div class="koi-schedule-day">';
            echo '<h3 class="koi-schedule-day-title"><span class="dashicons dashicons-calendar"></span> ' . esc_html(mb_strtoupper($day, 'UTF-8')) . ' - ' . date('d.m', strtotime(array_search($day, $days_of_week) . ' this week')) . '</h3>';
            foreach ($hours as $hour => $streamers) {
                echo '<div class="koi-schedule-time-slot">';
                echo '<span class="koi-schedule-time"><span class="dashicons dashicons-clock"></span> ' . esc_html($hour) . '</span>';
                echo '<div class="koi-streamer-list">';
                foreach ($streamers as $streamer) {
                    echo '<div class="koi-schedule-streamer">';
                    echo '<span class="koi-streamer-avatar" style="background-image: url(\'' . $streamer['avatar_url'] . '\');"></span> ';
                    echo '<span class="koi-streamer-name">' . $streamer['name'] . '</span>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
        }
    } else {
        echo '<p>No schedules found.</p>';
    }
    echo '</div>';

    return ob_get_clean();
}

add_shortcode( 'koi_schedule_display', 'display_schedule' );

function enqueue_koi_schedule_styles() {
    wp_enqueue_style(
        'koi-schedule-style',
        plugins_url('css/koi-schedule.css', __FILE__)
    );
}
add_action('wp_enqueue_scripts', 'enqueue_koi_schedule_styles');