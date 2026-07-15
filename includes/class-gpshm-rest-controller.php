<?php
/**
 * Public, lightweight status endpoint suitable for external uptime monitoring services.
 * Only exposes an overall status, never the detailed report (which could leak server info).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_REST_Controller {

	const NAMESPACE_ = 'garion-projetos-site-health-monitor/v1';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function get_status() {
		$checks = new GP_SHM_Checks();

		return rest_ensure_response(
			array(
				'status'     => $checks->get_overall_status(),
				'checked_at' => current_time( 'mysql', true ),
			)
		);
	}
}
