<?php
/**
 * REST routes for the Site Health Monitor.
 *
 * `/status` is a public, lightweight status endpoint suitable for external
 * uptime monitoring services. It only exposes an overall status, never the
 * detailed report (which could leak server info), and its shape/permission
 * never changes.
 *
 * Every other route requires `manage_options` (checked via the standard
 * WordPress REST cookie+nonce authentication) and is only meant to be called
 * from this plugin's own admin screen.
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

		register_rest_route(
			self::NAMESPACE_,
			'/report',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_report' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/history',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_history' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'limit' => array( 'default' => 20 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/issues/(?P<key>[a-z0-9_]+)/ignore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'ignore_issue' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_,
			'/issues/(?P<key>[a-z0-9_]+)/reopen',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reopen_issue' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function check_permission() {
		return current_user_can( 'manage_options' );
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

	/**
	 * Re-runs every check once, records the summary to history, and returns
	 * both the structured data and a ready-to-inject HTML fragment so the
	 * admin JS never has to re-implement the PHP rendering logic.
	 */
	public function run_report( WP_REST_Request $request ) {
		$checks = new GP_SHM_Checks();
		$report = $checks->get_report();

		GP_SHM_History::record( $report );

		$admin_page = new GP_SHM_Admin_Page();

		return rest_ensure_response(
			array(
				'report'     => $report,
				'overall'    => $checks->get_overall_status( $report ),
				'score'      => $checks->get_health_score( $report ),
				'checked_at' => current_time( 'mysql', true ),
				'html'       => $admin_page->render_overview_content( $report ),
			)
		);
	}

	public function get_history( WP_REST_Request $request ) {
		$limit = max( 1, min( 50, (int) $request['limit'] ) );

		return rest_ensure_response(
			array(
				'items' => array_slice( GP_SHM_History::all(), 0, $limit ),
				'total' => count( GP_SHM_History::all() ),
			)
		);
	}

	private function validated_key( WP_REST_Request $request ) {
		$key = sanitize_key( $request['key'] );

		return in_array( $key, GP_SHM_Checks::known_keys(), true ) ? $key : null;
	}

	public function ignore_issue( WP_REST_Request $request ) {
		$key = $this->validated_key( $request );

		if ( ! $key ) {
			return new WP_Error( 'gpshm_check_not_found', __( 'Unknown check.', 'garion-projetos-site-health-monitor' ), array( 'status' => 404 ) );
		}

		GP_SHM_Issue_State::ignore( $key, (string) $request['reason'] );

		return rest_ensure_response( array_merge( array( 'key' => $key ), GP_SHM_Issue_State::get( $key ) ) );
	}

	public function reopen_issue( WP_REST_Request $request ) {
		$key = $this->validated_key( $request );

		if ( ! $key ) {
			return new WP_Error( 'gpshm_check_not_found', __( 'Unknown check.', 'garion-projetos-site-health-monitor' ), array( 'status' => 404 ) );
		}

		GP_SHM_Issue_State::reopen( $key );

		return rest_ensure_response( array_merge( array( 'key' => $key ), GP_SHM_Issue_State::get( $key ) ) );
	}
}
