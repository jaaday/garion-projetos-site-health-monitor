<?php
/**
 * Optional WP-Cron based scheduled checks, gated by the "monitoring_enabled"
 * setting. Reuses the exact same read path as the manual "Run check now"
 * button and the REST /report route (get_report() + GP_SHM_History::record()) —
 * never touches get_overall_status()/get_health_score()/the public /status
 * endpoint, which keep computing fresh on every request regardless of cron.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Cron {

	const HOOK = 'gpshm_scheduled_check';

	const INTERVALS = array( 'hourly', 'twicedaily', 'daily', 'gpshm_weekly' );

	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'register_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- interval is only added when missing, see register_schedules().
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'update_option_' . GP_SHM_Settings::OPTION_KEY, array( $this, 'on_settings_updated' ) );
	}

	/**
	 * WP core has no built-in weekly schedule; every other option
	 * (hourly/twicedaily/daily) already exists.
	 */
	public function register_schedules( $schedules ) {
		if ( ! isset( $schedules['gpshm_weekly'] ) ) {
			$schedules['gpshm_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => __( 'Once Weekly', 'garion-projetos-site-health-monitor' ),
			);
		}

		return $schedules;
	}

	public function run() {
		$checks = new GP_SHM_Checks();
		$report = $checks->get_report();

		GP_SHM_History::record( $report );
	}

	public function on_settings_updated( $new_value ) {
		self::reschedule( $new_value );
	}

	/**
	 * Reconciles the scheduled event with the current settings. Always clears
	 * first so a frequency change never leaves two events scheduled at once;
	 * safe to call repeatedly (activation, every settings save).
	 */
	public static function reschedule( $settings = null ) {
		$settings = $settings ?? GP_SHM_Settings::get_all();

		wp_clear_scheduled_hook( self::HOOK );

		if ( empty( $settings['monitoring_enabled'] ) ) {
			return;
		}

		$frequency = in_array( $settings['monitoring_frequency'] ?? '', self::INTERVALS, true )
			? $settings['monitoring_frequency']
			: 'daily';

		wp_schedule_event( time(), $frequency, self::HOOK );
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
