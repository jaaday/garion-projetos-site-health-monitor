<?php
/**
 * Gathers all site health checks. Read-only: this plugin monitors, it does not fix.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Checks {

	const MIN_PHP_VERSION = '8.0';

	public function get_report() {
		$checks = array_merge(
			array( $this->check_wordpress_version() ),
			array( $this->check_php_version() ),
			array( $this->check_ssl() ),
			$this->check_ssl_certificate(),
			array( $this->check_outdated_plugins() ),
			array( $this->check_outdated_themes() ),
			array( $this->check_disk_space() ),
			array( $this->check_cron() ),
			array( $this->check_debug_log() ),
			$this->check_file_permissions(),
			array( $this->check_database() ),
			array( $this->check_object_cache() ),
			array( $this->check_outbound_connectivity() )
		);

		return $checks;
	}

	public function get_overall_status( $report = null ) {
		$report = $report ?? $this->get_report();

		$has_critical = false;
		$has_warning  = false;

		foreach ( $report as $check ) {
			if ( 'critical' === $check['status'] ) {
				$has_critical = true;
			} elseif ( 'warning' === $check['status'] ) {
				$has_warning = true;
			}
		}

		if ( $has_critical ) {
			return 'critical';
		}

		return $has_warning ? 'warning' : 'ok';
	}

	private function row( $label, $value, $status, $category ) {
		return array(
			'label'    => $label,
			'value'    => $value,
			'status'   => $status,
			'category' => $category,
		);
	}

	private function check_wordpress_version() {
		$updates = get_core_updates();
		$current = get_bloginfo( 'version' );
		$has_update = ! empty( $updates ) && isset( $updates[0]->response ) && 'upgrade' === $updates[0]->response;

		return $this->row(
			__( 'WordPress version', 'garion-projetos-site-health-monitor' ),
			$has_update
				? sprintf( /* translators: 1: current version, 2: available version. */ __( '%1$s (update to %2$s available)', 'garion-projetos-site-health-monitor' ), $current, $updates[0]->version )
				: $current,
			$has_update ? 'warning' : 'ok',
			'core'
		);
	}

	private function check_php_version() {
		$ok = version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '>=' );

		return $this->row(
			__( 'PHP version', 'garion-projetos-site-health-monitor' ),
			PHP_VERSION,
			$ok ? 'ok' : 'warning',
			'core'
		);
	}

	private function check_ssl() {
		return $this->row(
			__( 'HTTPS (current request)', 'garion-projetos-site-health-monitor' ),
			is_ssl() ? __( 'Active', 'garion-projetos-site-health-monitor' ) : __( 'Not active', 'garion-projetos-site-health-monitor' ),
			is_ssl() ? 'ok' : 'critical',
			'security'
		);
	}

	/**
	 * @return array List with zero or one row (skipped when the site is not served over HTTPS).
	 */
	private function check_ssl_certificate() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host || ! function_exists( 'openssl_x509_parse' ) ) {
			return array();
		}

		$context = stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false ) ) );
		$client  = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- connection failures are expected on hosts blocking outbound sockets; we degrade gracefully below.

		if ( ! $client ) {
			return array( $this->row( __( 'SSL certificate', 'garion-projetos-site-health-monitor' ), __( 'Could not connect to verify.', 'garion-projetos-site-health-monitor' ), 'warning', 'security' ) );
		}

		$params = stream_context_get_params( $client );
		fclose( $client ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- plain network socket, not a WordPress filesystem file.

		if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
			return array( $this->row( __( 'SSL certificate', 'garion-projetos-site-health-monitor' ), __( 'Unable to read certificate.', 'garion-projetos-site-health-monitor' ), 'warning', 'security' ) );
		}

		$cert  = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
		$valid_to = isset( $cert['validTo_time_t'] ) ? (int) $cert['validTo_time_t'] : 0;
		$days_left = $valid_to ? (int) floor( ( $valid_to - time() ) / DAY_IN_SECONDS ) : 0;
		$warning_days = (int) GP_SHM_Settings::get( 'ssl_expiry_warning_days' );

		$status = 'ok';
		if ( $days_left <= 0 ) {
			$status = 'critical';
		} elseif ( $days_left <= $warning_days ) {
			$status = 'warning';
		}

		return array(
			$this->row(
				__( 'SSL certificate expiry', 'garion-projetos-site-health-monitor' ),
				$days_left > 0
					? sprintf( /* translators: %d: days until expiry. */ _n( '%d day left', '%d days left', $days_left, 'garion-projetos-site-health-monitor' ), $days_left )
					: __( 'Expired', 'garion-projetos-site-health-monitor' ),
				$status,
				'security'
			),
		);
	}

	private function check_outdated_plugins() {
		if ( ! function_exists( 'get_plugin_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$count = count( get_plugin_updates() );

		return $this->row(
			__( 'Outdated plugins', 'garion-projetos-site-health-monitor' ),
			$count,
			$count > 0 ? 'warning' : 'ok',
			'updates'
		);
	}

	private function check_outdated_themes() {
		if ( ! function_exists( 'get_theme_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$count = count( get_theme_updates() );

		return $this->row(
			__( 'Outdated themes', 'garion-projetos-site-health-monitor' ),
			$count,
			$count > 0 ? 'warning' : 'ok',
			'updates'
		);
	}

	private function check_disk_space() {
		$free = @disk_free_space( ABSPATH ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- disabled on some hosts; we show "unknown" below instead of a noisy warning.
		$warning_mb = (int) GP_SHM_Settings::get( 'disk_space_warning_mb' );

		if ( false === $free ) {
			return $this->row( __( 'Free disk space', 'garion-projetos-site-health-monitor' ), __( 'Unknown (disabled by host)', 'garion-projetos-site-health-monitor' ), 'warning', 'server' );
		}

		return $this->row(
			__( 'Free disk space', 'garion-projetos-site-health-monitor' ),
			size_format( $free ),
			$free < $warning_mb * MB_IN_BYTES ? 'warning' : 'ok',
			'server'
		);
	}

	private function check_cron() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			return $this->row( __( 'WP-Cron', 'garion-projetos-site-health-monitor' ), __( 'Disabled via DISABLE_WP_CRON (make sure a system cron calls wp-cron.php instead).', 'garion-projetos-site-health-monitor' ), 'warning', 'server' );
		}

		$cron_array     = _get_cron_array();
		$overdue        = 0;
		$overdue_cutoff = time() - ( (int) GP_SHM_Settings::get( 'cron_overdue_minutes' ) * MINUTE_IN_SECONDS );

		foreach ( (array) $cron_array as $timestamp => $hooks ) {
			if ( $timestamp < $overdue_cutoff ) {
				$overdue += count( $hooks );
			}
		}

		return $this->row(
			__( 'WP-Cron scheduled events', 'garion-projetos-site-health-monitor' ),
			$overdue > 0
				/* translators: %d: number of overdue cron events. */
				? sprintf( __( '%d overdue event(s)', 'garion-projetos-site-health-monitor' ), $overdue )
				: __( 'Running on schedule', 'garion-projetos-site-health-monitor' ),
			$overdue > 0 ? 'warning' : 'ok',
			'server'
		);
	}

	private function check_debug_log() {
		$log_path = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_path ) ) {
			return $this->row( __( 'Recent PHP errors', 'garion-projetos-site-health-monitor' ), __( 'No debug.log file found.', 'garion-projetos-site-health-monitor' ), 'ok', 'errors' );
		}

		$modified_recently = ( time() - filemtime( $log_path ) ) < DAY_IN_SECONDS;
		$size              = size_format( filesize( $log_path ) );

		return $this->row(
			__( 'Recent PHP errors', 'garion-projetos-site-health-monitor' ),
			sprintf(
				/* translators: %s: debug.log file size. */
				__( 'debug.log present (%s)', 'garion-projetos-site-health-monitor' ),
				$size
			),
			$modified_recently ? 'warning' : 'ok',
			'errors'
		);
	}

	public function get_recent_log_lines( $lines = 20 ) {
		$log_path = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_path ) || ! is_readable( $log_path ) ) {
			return array();
		}

		$max_bytes = 200 * 1024;
		$size      = filesize( $log_path );

		$handle = fopen( $log_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- reading our own log tail; WP_Filesystem is unnecessary overhead for a local read-only diagnostic.

		if ( ! $handle ) {
			return array();
		}

		if ( $size > $max_bytes ) {
			fseek( $handle, -$max_bytes, SEEK_END );
		}

		$contents = stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		$all_lines = explode( "\n", trim( (string) $contents ) );

		return array_slice( $all_lines, -1 * $lines );
	}

	private function check_file_permissions() {
		$rows = array();

		$wp_config_path = ABSPATH . 'wp-config.php';
		if ( file_exists( $wp_config_path ) ) {
			$perms          = substr( sprintf( '%o', fileperms( $wp_config_path ) ), -3 );
			$world_writable = (int) substr( $perms, -1 ) >= 2;

			$rows[] = $this->row(
				__( 'wp-config.php permissions', 'garion-projetos-site-health-monitor' ),
				$perms,
				$world_writable ? 'critical' : 'ok',
				'security'
			);
		}

		$rows[] = $this->row(
			__( 'wp-content writable', 'garion-projetos-site-health-monitor' ),
			is_writable( WP_CONTENT_DIR ) ? __( 'Yes', 'garion-projetos-site-health-monitor' ) : __( 'No', 'garion-projetos-site-health-monitor' ),
			is_writable( WP_CONTENT_DIR ) ? 'ok' : 'warning',
			'security'
		);

		return $rows;
	}

	private function check_database() {
		global $wpdb;

		$tables_found = (int) $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->posts ) ) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off connectivity/schema check.
		) ? 1 : 0;

		return $this->row(
			__( 'Database', 'garion-projetos-site-health-monitor' ),
			$tables_found
				? sprintf( /* translators: %s: database server version. */ __( 'Connected (server %s)', 'garion-projetos-site-health-monitor' ), $wpdb->db_version() )
				: __( 'Core tables not found', 'garion-projetos-site-health-monitor' ),
			$tables_found ? 'ok' : 'critical',
			'server'
		);
	}

	private function check_object_cache() {
		return $this->row(
			__( 'Persistent object cache', 'garion-projetos-site-health-monitor' ),
			wp_using_ext_object_cache() ? __( 'Active', 'garion-projetos-site-health-monitor' ) : __( 'Not active (default DB cache)', 'garion-projetos-site-health-monitor' ),
			'ok',
			'server'
		);
	}

	private function check_outbound_connectivity() {
		$response = wp_remote_get(
			'https://api.wordpress.org/core/version-check/1.7/',
			array( 'timeout' => 10 )
		);

		$ok = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;

		return $this->row(
			__( 'Outbound connectivity (api.wordpress.org)', 'garion-projetos-site-health-monitor' ),
			$ok ? __( 'Reachable', 'garion-projetos-site-health-monitor' ) : __( 'Unreachable - outgoing HTTP requests may be blocked on this server', 'garion-projetos-site-health-monitor' ),
			$ok ? 'ok' : 'critical',
			'connectivity'
		);
	}
}
