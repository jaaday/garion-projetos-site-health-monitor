<?php
/**
 * Settings: warning thresholds used by the health checks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Settings {

	const OPTION_KEY = 'gpshm_settings';

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public static function defaults() {
		return array(
			'ssl_expiry_warning_days' => 14,
			'disk_space_warning_mb'   => 500,
			'cron_overdue_minutes'    => 60,
			'history_retention'       => 30,
			'monitoring_enabled'      => false,
			'monitoring_frequency'    => 'daily',
		);
	}

	/**
	 * Valid values for the "monitoring_frequency" field, shared by the
	 * settings form and the sanitizer so they can never drift apart.
	 */
	public static function monitoring_frequencies() {
		return array(
			'hourly'       => __( 'Every hour', 'garion-projetos-site-health-monitor' ),
			'twicedaily'   => __( 'Twice a day', 'garion-projetos-site-health-monitor' ),
			'daily'        => __( 'Once a day', 'garion-projetos-site-health-monitor' ),
			'gpshm_weekly' => __( 'Once a week', 'garion-projetos-site-health-monitor' ),
		);
	}

	public static function get_all() {
		return wp_parse_args( get_option( self::OPTION_KEY, array() ), self::defaults() );
	}

	public static function get( $key ) {
		$settings = self::get_all();

		return $settings[ $key ] ?? self::defaults()[ $key ] ?? 0;
	}

	public function register_settings() {
		register_setting(
			'gpshm_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize( $input ) {
		$current = self::get_all();

		$sanitized = array(
			'ssl_expiry_warning_days' => isset( $input['ssl_expiry_warning_days'] ) ? max( 1, (int) $input['ssl_expiry_warning_days'] ) : $current['ssl_expiry_warning_days'],
			'disk_space_warning_mb'   => isset( $input['disk_space_warning_mb'] ) ? max( 1, (int) $input['disk_space_warning_mb'] ) : $current['disk_space_warning_mb'],
			'cron_overdue_minutes'    => isset( $input['cron_overdue_minutes'] ) ? max( 1, (int) $input['cron_overdue_minutes'] ) : $current['cron_overdue_minutes'],
			'history_retention'      => isset( $input['history_retention'] ) ? max( 5, min( 50, (int) $input['history_retention'] ) ) : $current['history_retention'],
			'monitoring_enabled'      => ! empty( $input['monitoring_enabled'] ),
			'monitoring_frequency'    => isset( $input['monitoring_frequency'] ) && array_key_exists( $input['monitoring_frequency'], self::monitoring_frequencies() )
				? $input['monitoring_frequency']
				: $current['monitoring_frequency'],
		);

		if ( class_exists( 'GP_SHM_Cron' ) ) {
			GP_SHM_Cron::reschedule( $sanitized );
		}

		return $sanitized;
	}
}
