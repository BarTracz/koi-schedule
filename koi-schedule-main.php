<?php
    /**
     * Plugin Name: KoiSchedule
     * Description: A plugin to manage schedules for KoiCorp
     * Version: 0.2.0
     * Author: Enduriionek
     */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

define( 'KOI_SCHEDULE_PATH', plugin_dir_path( __FILE__ ) );
define( 'KOI_SCHEDULE_URL', plugin_dir_url( __FILE__ ) );
define( 'KOI_SCHEDULE_TABLE_NAME', $wpdb->prefix . 'koi-schedule' );

define( 'KOI_STREAMERS_TABLE_NAME', $wpdb->prefix . 'koi-streamers' );

require_once KOI_SCHEDULE_PATH . 'includes/koi-schedule.php';
require_once KOI_SCHEDULE_PATH . 'includes/koi-schedule-front-display.php';

require_once KOI_SCHEDULE_PATH . 'includes/koi-streamers.php';
require_once KOI_SCHEDULE_PATH . 'includes/koi-events.php';

register_activation_hook(__FILE__, function () {
    ob_start();
    create_koi_streamers_table();
	create_koi_events_table();
    create_koi_schedule_table();
	update_koi_schedule_table();
    ob_end_clean();
});