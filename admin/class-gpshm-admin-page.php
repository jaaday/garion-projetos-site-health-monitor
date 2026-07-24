<?php
/**
 * Admin screen: health dashboard, per-area checks, problems, history, logs and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Admin_Page {

	const MENU_SLUG = 'gpshm-monitor';

	/**
	 * Set once per render() so every render_*() method shares the exact same
	 * instant instead of each calling current_time()/get_report() again.
	 */
	private $checked_at_label;
	private $previous_active_issues;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Garion Projetos Site Health Monitor', 'garion-projetos-site-health-monitor' ),
			__( 'Site Health Monitor', 'garion-projetos-site-health-monitor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-heart',
			82
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'gpshm-admin', GPSHM_URL . 'assets/css/admin.css', array( 'common' ), GPSHM_VERSION );
		wp_enqueue_script( 'gpshm-admin', GPSHM_URL . 'assets/js/admin.js', array( 'wp-api-fetch' ), GPSHM_VERSION, true );

		wp_localize_script(
			'gpshm-admin',
			'gpshmData',
			array(
				'restNamespace'    => GP_SHM_REST_Controller::NAMESPACE_,
				'restNonce'        => wp_create_nonce( 'wp_rest' ),
				'settingsDefaults' => GP_SHM_Settings::defaults(),
				'i18n'             => array(
					'running'            => __( 'Running checks...', 'garion-projetos-site-health-monitor' ),
					'done'               => __( 'Check completed.', 'garion-projetos-site-health-monitor' ),
					'failed'             => __( 'The check could not be completed.', 'garion-projetos-site-health-monitor' ),
					'ignoreTitle'        => __( 'Ignore this check', 'garion-projetos-site-health-monitor' ),
					'ignoreHelp'         => __( 'This check will stop counting toward the health score and the Problems list until you reopen it. Optionally add a reason below.', 'garion-projetos-site-health-monitor' ),
					'confirm'            => __( 'Confirm', 'garion-projetos-site-health-monitor' ),
					'cancel'             => __( 'Cancel', 'garion-projetos-site-health-monitor' ),
					'ignored'            => __( 'Check ignored.', 'garion-projetos-site-health-monitor' ),
					'reopened'           => __( 'Check reopened.', 'garion-projetos-site-health-monitor' ),
					'undo'               => __( 'Undo', 'garion-projetos-site-health-monitor' ),
					'copySuccess'        => __( 'Technical data copied to clipboard.', 'garion-projetos-site-health-monitor' ),
					'copyFailed'         => __( 'Could not copy to clipboard.', 'garion-projetos-site-health-monitor' ),
					'testing'            => __( 'Testing...', 'garion-projetos-site-health-monitor' ),
					'endpointOk'         => __( 'Reachable — responded with a valid status.', 'garion-projetos-site-health-monitor' ),
					'endpointFailed'     => __( 'Could not reach the endpoint.', 'garion-projetos-site-health-monitor' ),
					'noResults'          => __( 'No problems match the current filters.', 'garion-projetos-site-health-monitor' ),
					'bulkConfirmIgnore'  => __( 'Ignore all selected checks?', 'garion-projetos-site-health-monitor' ),
					'bulkConfirmReopen'  => __( 'Reopen all selected checks?', 'garion-projetos-site-health-monitor' ),
					/* translators: %d is replaced client-side in admin.js with the number of selected rows. */
					'selectedCount'      => __( '%d selected', 'garion-projetos-site-health-monitor' ),
					'savingSettings'     => __( 'Saving...', 'garion-projetos-site-health-monitor' ),
					'unsavedChanges'     => __( 'You have unsaved changes.', 'garion-projetos-site-health-monitor' ),
					/* translators: %1$d and %2$d are replaced client-side in admin.js with the current and total page numbers. */
					'pageOf'             => __( 'Page %1$d of %2$d', 'garion-projetos-site-health-monitor' ),
				),
			)
		);
	}

	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.

		return in_array( $tab, array_keys( $this->tabs() ), true ) ? $tab : 'overview';
	}

	private function tabs() {
		return array(
			'overview'    => __( 'Overview', 'garion-projetos-site-health-monitor' ),
			'issues'      => __( 'Problems', 'garion-projetos-site-health-monitor' ),
			'history'     => __( 'History', 'garion-projetos-site-health-monitor' ),
			'wordpress'   => __( 'WordPress', 'garion-projetos-site-health-monitor' ),
			'security'    => __( 'Security', 'garion-projetos-site-health-monitor' ),
			'performance' => __( 'Performance', 'garion-projetos-site-health-monitor' ),
			'server'      => __( 'Server', 'garion-projetos-site-health-monitor' ),
			'logs'        => __( 'Logs', 'garion-projetos-site-health-monitor' ),
			'settings'    => __( 'Settings', 'garion-projetos-site-health-monitor' ),
		);
	}

	/**
	 * The 3 labeled clusters the navigation is grouped into.
	 */
	private function nav_structure() {
		$labels = $this->tabs();

		return array(
			__( 'Monitoring', 'garion-projetos-site-health-monitor' )  => array(
				'overview' => $labels['overview'],
				'issues'   => $labels['issues'],
				'history'  => $labels['history'],
			),
			__( 'Diagnostics', 'garion-projetos-site-health-monitor' ) => array(
				'wordpress'   => $labels['wordpress'],
				'security'    => $labels['security'],
				'performance' => $labels['performance'],
				'server'      => $labels['server'],
			),
			__( 'System', 'garion-projetos-site-health-monitor' )      => array(
				'logs'     => $labels['logs'],
				'settings' => $labels['settings'],
			),
		);
	}

	private function tab_icons() {
		return array(
			'overview'    => 'dashicons-dashboard',
			'issues'      => 'dashicons-flag',
			'history'     => 'dashicons-backup',
			'wordpress'   => 'dashicons-wordpress',
			'security'    => 'dashicons-shield',
			'performance' => 'dashicons-performance',
			'server'      => 'dashicons-database',
			'logs'        => 'dashicons-media-text',
			'settings'    => 'dashicons-admin-generic',
		);
	}

	/**
	 * Open-problem count per navigation item, feeding the nav badges. Uses
	 * filter_active() so an ignored check never inflates a badge — same
	 * philosophy as the health score.
	 */
	private function nav_badges( array $report ) {
		$checks = new GP_SHM_Checks();
		$active = $checks->filter_active( $report );
		$badges = array();

		foreach ( $this->nav_groups() as $group_key => $label ) {
			$badges[ $group_key ] = count(
				array_filter(
					$active,
					function ( $row ) use ( $group_key ) {
						return $this->check_nav_group( $row['key'] ) === $group_key && 'ok' !== $row['status'];
					}
				)
			);
		}

		$badges['issues'] = count( array_filter( $active, static fn( $row ) => 'ok' !== $row['status'] ) );

		return $badges;
	}

	/**
	 * Which of the 4 grouped diagnostic tabs a check key belongs to. Every key
	 * returned by GP_SHM_Checks::known_keys() must be mapped here or it
	 * silently won't show up on its group tab (it still appears on
	 * Overview/Problems regardless).
	 */
	private function check_nav_group( $key ) {
		$map = array(
			'wordpress_version'          => 'wordpress',
			'php_version'                => 'wordpress',
			'outdated_plugins'           => 'wordpress',
			'outdated_themes'            => 'wordpress',
			'ssl_https'                  => 'security',
			'ssl_certificate_expiry'     => 'security',
			'file_permissions_wpconfig'  => 'security',
			'file_permissions_wpcontent' => 'security',
			'cron'                       => 'performance',
			'object_cache'               => 'performance',
			'disk_space'                 => 'server',
			'database'                   => 'server',
			'outbound_connectivity'      => 'server',
			'debug_log'                  => 'logs',
		);

		return $map[ $key ] ?? 'server';
	}

	private function nav_groups() {
		return array(
			'wordpress'   => __( 'WordPress', 'garion-projetos-site-health-monitor' ),
			'security'    => __( 'Security', 'garion-projetos-site-health-monitor' ),
			'performance' => __( 'Performance', 'garion-projetos-site-health-monitor' ),
			'server'      => __( 'Server', 'garion-projetos-site-health-monitor' ),
		);
	}

	private function group_rows( array $report, $group ) {
		return array_values(
			array_filter(
				$report,
				function ( $row ) use ( $group ) {
					return $this->check_nav_group( $row['key'] ) === $group;
				}
			)
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab    = $this->current_tab();
		$checks = new GP_SHM_Checks();
		$report = $checks->get_report();

		$this->checked_at_label       = get_date_from_gmt( current_time( 'mysql', true ), 'Y-m-d H:i' );
		$this->previous_active_issues = GP_SHM_History::previous_active_issues();
		?>
		<div class="wrap gpshm-wrap">
			<?php $this->render_header( $report ); ?>

			<?php
			GP_SHM_Admin_UI::tabs(
				$this->nav_structure(),
				$tab,
				add_query_arg( array( 'page' => self::MENU_SLUG ), admin_url( 'admin.php' ) ),
				$this->tab_icons(),
				$this->nav_badges( $report )
			);
			?>

			<div class="gpshm-panel">
				<?php
				switch ( $tab ) {
					case 'wordpress':
					case 'security':
					case 'performance':
					case 'server':
						$this->render_group_tab( $tab, $report );
						break;
					case 'issues':
						$this->render_issues( $report );
						break;
					case 'history':
						$this->render_history();
						break;
					case 'logs':
						$this->render_logs( $report );
						break;
					case 'settings':
						$this->render_settings();
						break;
					default:
						$this->render_overview( $report );
				}
				?>
			</div>
		</div>
		<?php
	}

	private function build_run_button_html() {
		ob_start();
		?>
		<button type="button" class="button button-primary gpshm-run-check" id="gpshm-run-check">
			<?php echo GP_SHM_Admin_UI::icon( 'dashicons-update' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts. ?>
			<span class="gpshm-run-check__label"><?php esc_html_e( 'Run check now', 'garion-projetos-site-health-monitor' ); ?></span>
		</button>
		<span id="gpshm-run-status" class="gpshm-header__run-status" aria-live="polite"></span>
		<?php
		return ob_get_clean();
	}

	private function render_header( array $report ) {
		$checks  = new GP_SHM_Checks();
		$overall = $checks->get_overall_status( $report );

		GP_SHM_Admin_UI::header(
			__( 'Garion Projetos Site Health Monitor', 'garion-projetos-site-health-monitor' ),
			GPSHM_VERSION,
			__( 'WordPress diagnostics dashboard: PHP, SSL, outdated plugins/themes, cron, disk space, database and connectivity status.', 'garion-projetos-site-health-monitor' ),
			$overall,
			$this->checked_at_label,
			$this->build_run_button_html()
		);
	}

	private function score_tier_label( $score ) {
		if ( 0 === $score ) {
			return __( 'Unavailable', 'garion-projetos-site-health-monitor' );
		}
		if ( $score >= 90 ) {
			return __( 'Excellent', 'garion-projetos-site-health-monitor' );
		}
		if ( $score >= 75 ) {
			return __( 'Healthy', 'garion-projetos-site-health-monitor' );
		}
		if ( $score >= 50 ) {
			return __( 'Needs attention', 'garion-projetos-site-health-monitor' );
		}

		return __( 'Critical', 'garion-projetos-site-health-monitor' );
	}

	private function render_overview( array $report ) {
		?>
		<div id="gpshm-overview-content">
			<?php echo $this->render_overview_content( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_overview_content() escapes its own parts. ?>
		</div>
		<?php
	}

	/**
	 * Renders everything inside #gpshm-overview-content. Public and returning
	 * (not echoing) a string so the REST /report route can reuse the exact
	 * same markup for its AJAX-driven refresh, instead of duplicating this
	 * logic in JavaScript.
	 */
	public function render_overview_content( array $report ) {
		ob_start();

		$checks   = new GP_SHM_Checks();
		$overall  = $checks->get_overall_status( $report );
		$score    = $checks->get_health_score( $report );
		$weights  = $checks->get_score_weights();
		$active   = $checks->filter_active( $report );
		$critical = array_values( array_filter( $active, static fn( $row ) => 'critical' === $row['status'] ) );
		$warning  = array_values( array_filter( $active, static fn( $row ) => 'warning' === $row['status'] ) );
		$ok_count = count( array_filter( $active, static fn( $row ) => 'ok' === $row['status'] ) );
		$ignored  = count( $report ) - count( $active );

		$checked_at_label = $this->checked_at_label ? $this->checked_at_label : get_date_from_gmt( current_time( 'mysql', true ), 'Y-m-d H:i' );

		$history_entries = GP_SHM_History::all();
		$previous_score   = $history_entries[0]['score'] ?? null;
		$delta            = null !== $previous_score ? $score - $previous_score : null;

		$top_problem = '';
		if ( $critical ) {
			$top_problem = $critical[0]['label'];
		} elseif ( $warning ) {
			$top_problem = $warning[0]['label'];
		}

		$issues_url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'issues' ), admin_url( 'admin.php' ) );
		?>
		<div class="gpshm-hero-row">
			<div class="gpshm-hero-row__score">
				<?php GP_SHM_Admin_UI::score_hero( $score, $this->score_tier_label( $score ), $overall ); ?>
			</div>
			<div class="gpshm-hero-row__context">
				<?php
				GP_SHM_Admin_UI::score_context(
					array(
						'checked_at'   => $checked_at_label,
						'delta'        => $delta,
						'total_checks' => count( $active ),
						'top_problem'  => $top_problem,
						'critical_url' => $critical ? add_query_arg( array( 'severity' => 'critical' ), $issues_url ) : '',
					)
				);
				?>
			</div>
		</div>

		<?php
		$contributors = array();
		foreach ( array_merge( $critical, $warning ) as $row ) {
			$contributors[] = array(
				'label'  => $row['label'],
				'status' => $row['status'],
			);
		}

		GP_SHM_Admin_UI::score_explanation( count( $active ), (int) $weights['warning'], (int) $weights['critical'], $contributors, $ignored, $checked_at_label );
		?>

		<div class="gpshm-metrics">
			<?php
			GP_SHM_Admin_UI::metric_card(
				count( $critical ),
				__( 'Critical problems', 'garion-projetos-site-health-monitor' ),
				'critical',
				'dashicons-dismiss',
				$critical
					? sprintf( /* translators: 1: number of critical problems, 2: total checks. */ __( '%1$d of %2$d checks need immediate attention', 'garion-projetos-site-health-monitor' ), count( $critical ), count( $active ) )
					: __( 'No critical problems found', 'garion-projetos-site-health-monitor' ),
				$critical ? add_query_arg( array( 'severity' => 'critical' ), $issues_url ) : ''
			);
			GP_SHM_Admin_UI::metric_card(
				count( $warning ),
				__( 'Warnings', 'garion-projetos-site-health-monitor' ),
				'warning',
				'dashicons-warning',
				$warning
					/* translators: %d: number of checks reporting a warning. */
					? sprintf( __( '%d checks reporting a warning', 'garion-projetos-site-health-monitor' ), count( $warning ) )
					: __( 'No warnings found', 'garion-projetos-site-health-monitor' ),
				$warning ? add_query_arg( array( 'severity' => 'warning' ), $issues_url ) : ''
			);
			GP_SHM_Admin_UI::metric_card(
				$ok_count,
				__( 'Healthy checks', 'garion-projetos-site-health-monitor' ),
				'success',
				'dashicons-yes-alt',
				sprintf( /* translators: 1: number of healthy checks, 2: total checks. */ __( '%1$d of %2$d checks passing', 'garion-projetos-site-health-monitor' ), $ok_count, count( $active ) )
			);
			GP_SHM_Admin_UI::metric_card(
				$ignored,
				__( 'Ignored', 'garion-projetos-site-health-monitor' ),
				'neutral',
				'dashicons-hidden',
				$ignored
					? __( 'Excluded from the score until reopened', 'garion-projetos-site-health-monitor' )
					: __( 'Nothing is currently ignored', 'garion-projetos-site-health-monitor' )
			);
			?>
		</div>

		<div class="gpshm-group-grid">
			<?php foreach ( $this->nav_groups() as $group_key => $group_label ) : ?>
				<?php
				$rows          = $this->group_rows( $report, $group_key );
				$group_active  = array_values( array_filter( $rows, static fn( $row ) => ! GP_SHM_Issue_State::is_ignored( $row['key'] ) ) );
				$open          = array_filter( $group_active, static fn( $row ) => 'ok' !== $row['status'] );
				$worst         = 'ok';
				$top           = '';
				foreach ( $group_active as $row ) {
					if ( 'critical' === $row['status'] ) {
						$worst = 'critical';
						$top   = $top ? $top : $row['label'];
						break;
					}
					if ( 'warning' === $row['status'] ) {
						$worst = 'warning';
						$top   = $top ? $top : $row['label'];
					}
				}
				$group_url = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $group_key ), admin_url( 'admin.php' ) );
				?>
				<?php GP_SHM_Admin_UI::category_card( $group_label, $worst, count( $group_active ), count( $open ), $top, $group_url ); ?>
			<?php endforeach; ?>
		</div>

		<?php GP_SHM_Admin_UI::endpoint_card( rest_url( GP_SHM_REST_Controller::NAMESPACE_ . '/status' ) ); ?>
		<?php
		return ob_get_clean();
	}

	private function render_group_tab( $group, array $report ) {
		$labels = $this->tabs();
		$rows   = $this->group_rows( $report, $group );

		GP_SHM_Admin_UI::card_start( $labels[ $group ] );
		$this->render_checks_table( $rows );
		GP_SHM_Admin_UI::card_end();
	}

	/**
	 * Earliest recorded, unbroken run of $key appearing as non-ok, counting
	 * backward from the most recent recorded history entry. Null if the most
	 * recent recorded entry doesn't contain it (no honest streak to report).
	 */
	private function check_detected_since( $key ) {
		$earliest = null;

		foreach ( GP_SHM_History::all() as $entry ) {
			if ( in_array( $key, $entry['active_issues'] ?? array(), true ) ) {
				$earliest = $entry['timestamp'];
			} else {
				break;
			}
		}

		return $earliest;
	}

	/**
	 * How many of the last few recorded runs included $key as non-ok, for the
	 * detail row's "recent history" line. Empty string if no entries have the
	 * active_issues field yet (older, pre-upgrade entries).
	 */
	private function check_recent_history_label( $key ) {
		$entries    = array_slice( GP_SHM_History::all(), 0, 10 );
		$with_field = 0;
		$count      = 0;

		foreach ( $entries as $entry ) {
			if ( ! isset( $entry['active_issues'] ) ) {
				continue;
			}
			++$with_field;
			if ( in_array( $key, $entry['active_issues'], true ) ) {
				++$count;
			}
		}

		if ( 0 === $with_field ) {
			return '';
		}

		return sprintf(
			/* translators: 1: number of recorded runs where this check was active, 2: total recorded runs considered. */
			_n( 'Active in %1$d of the last %2$d recorded run.', 'Active in %1$d of the last %2$d recorded runs.', $with_field, 'garion-projetos-site-health-monitor' ),
			$count,
			$with_field
		);
	}

	/**
	 * Shared table renderer used by the 4 grouped tabs and the Problems tab.
	 * $resolution_center enables the extras only the Problems "resolution
	 * center" needs: bulk-select checkboxes, new/recurring badges and the
	 * detected-since/group data attributes the filters read.
	 */
	private function render_checks_table( array $rows, $resolution_center = false ) {
		if ( ! $rows ) {
			GP_SHM_Admin_UI::empty_state( __( 'Nothing to show here.', 'garion-projetos-site-health-monitor' ) );
			return;
		}

		$headers = array();
		if ( $resolution_center ) {
			$headers[] = __( 'Select', 'garion-projetos-site-health-monitor' );
		}
		$headers[] = __( 'Check', 'garion-projetos-site-health-monitor' );
		$headers[] = __( 'Current result', 'garion-projetos-site-health-monitor' );
		$headers[] = __( 'Severity', 'garion-projetos-site-health-monitor' );
		$headers[] = __( 'Last checked', 'garion-projetos-site-health-monitor' );

		GP_SHM_Admin_UI::table_start( $headers, 'gpshm-checks-table' );
		$colspan = count( $headers );

		foreach ( $rows as $row ) {
			$state    = GP_SHM_Issue_State::get( $row['key'] );
			$ignored  = ! empty( $state['ignored'] );
			$reopened = 'ok' !== $row['status'] && GP_SHM_Issue_State::is_recently_reopened( $row['key'] );
			$status   = $ignored ? 'ignored' : $row['status'];
			$doc      = GP_SHM_Check_Docs::get( $row['key'] );

			$novelty     = '';
			$detected_at = '';
			if ( $resolution_center && 'ok' !== $row['status'] ) {
				$detected_at = $this->check_detected_since( $row['key'] );
				if ( null !== $this->previous_active_issues ) {
					$novelty = in_array( $row['key'], $this->previous_active_issues, true ) ? 'recurring' : 'new';
				}
				if ( ! $detected_at ) {
					$detected_at = current_time( 'mysql', true );
				}
			}
			?>
			<tr
				data-gpshm-check-key="<?php echo esc_attr( $row['key'] ); ?>"
				data-gpshm-status="<?php echo esc_attr( $row['status'] ); ?>"
				data-gpshm-group="<?php echo esc_attr( $this->check_nav_group( $row['key'] ) ); ?>"
				data-gpshm-ignored="<?php echo esc_attr( $ignored ? '1' : '0' ); ?>"
				<?php if ( $detected_at ) : ?>data-gpshm-detected-at="<?php echo esc_attr( $detected_at ); ?>"<?php endif; ?>
			>
				<?php if ( $resolution_center ) : ?>
					<td data-label="<?php esc_attr_e( 'Select', 'garion-projetos-site-health-monitor' ); ?>">
						<input type="checkbox" class="gpshm-bulk-checkbox" value="<?php echo esc_attr( $row['key'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: check name. */ __( 'Select %s', 'garion-projetos-site-health-monitor' ), $row['label'] ) ); ?>" />
					</td>
				<?php endif; ?>
				<td data-label="<?php esc_attr_e( 'Check', 'garion-projetos-site-health-monitor' ); ?>">
					<button type="button" class="gpshm-row-toggle" aria-expanded="false" aria-controls="gpshm-detail-<?php echo esc_attr( $row['key'] ); ?>">
						<?php echo GP_SHM_Admin_UI::icon( 'dashicons-arrow-right-alt2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<strong><?php echo esc_html( $row['label'] ); ?></strong>
					</button>
				</td>
				<td data-label="<?php esc_attr_e( 'Current result', 'garion-projetos-site-health-monitor' ); ?>"><?php echo esc_html( $row['value'] ); ?></td>
				<td data-label="<?php esc_attr_e( 'Severity', 'garion-projetos-site-health-monitor' ); ?>">
					<?php echo GP_SHM_Admin_UI::status_badge( $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $reopened ) : ?>
						<?php echo GP_SHM_Admin_UI::badge( __( 'Reopened', 'garion-projetos-site-health-monitor' ), 'info', 'dashicons-image-rotate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<?php if ( 'new' === $novelty ) : ?>
						<?php echo GP_SHM_Admin_UI::badge( __( 'New', 'garion-projetos-site-health-monitor' ), 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php elseif ( 'recurring' === $novelty ) : ?>
						<?php echo GP_SHM_Admin_UI::badge( __( 'Recurring', 'garion-projetos-site-health-monitor' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<?php if ( $ignored && $state['reason'] ) : ?>
						<?php echo GP_SHM_Admin_UI::tooltip( $state['reason'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</td>
				<td data-label="<?php esc_attr_e( 'Last checked', 'garion-projetos-site-health-monitor' ); ?>"><?php echo esc_html( $this->checked_at_label ); ?></td>
			</tr>
			<?php
			GP_SHM_Admin_UI::detail_row(
				$colspan,
				$row,
				$doc,
				array(
					'last_checked'   => $this->checked_at_label,
					'state_note'     => $this->row_state_note( $ignored, $reopened, $state ),
					'recent_history' => $this->check_recent_history_label( $row['key'] ),
					'actions'        => $this->build_row_actions( $row, $ignored ),
				)
			);
		}

		GP_SHM_Admin_UI::table_end();
	}

	private function row_state_note( $ignored, $reopened, array $state ) {
		if ( $ignored ) {
			return $state['reason']
				? sprintf( /* translators: 1: date ignored, 2: reason given. */ __( 'Ignored on %1$s — reason: %2$s', 'garion-projetos-site-health-monitor' ), get_date_from_gmt( $state['ignored_at'], 'Y-m-d H:i' ), $state['reason'] )
				: sprintf( /* translators: %s: date ignored. */ __( 'Ignored on %s, with no reason given.', 'garion-projetos-site-health-monitor' ), get_date_from_gmt( $state['ignored_at'], 'Y-m-d H:i' ) );
		}

		if ( $reopened && ! empty( $state['reopened_at'] ) ) {
			return sprintf( /* translators: %s: date reopened. */ __( 'Reopened on %s.', 'garion-projetos-site-health-monitor' ), get_date_from_gmt( $state['reopened_at'], 'Y-m-d H:i' ) );
		}

		return '';
	}

	/**
	 * Action items shown at the end of the detail panel once it's expanded —
	 * there is no action menu on the collapsed row. "View details"/"View
	 * recommendation" are intentionally absent here: by the time these
	 * actions are reachable, the panel is already open and showing both.
	 */
	private function build_row_actions( array $row, $ignored ) {
		$items   = array();
		$items[] = array(
			'label' => __( 'Run this check again', 'garion-projetos-site-health-monitor' ),
			'icon'  => 'dashicons-update',
			'attrs' => 'data-gpshm-menu-action="rerun"',
		);

		$items[] = array(
			'label' => __( 'Copy technical data', 'garion-projetos-site-health-monitor' ),
			'icon'  => 'dashicons-clipboard',
			'attrs' => 'data-gpshm-menu-action="copy" data-gpshm-payload="' . esc_attr( wp_json_encode( $row ) ) . '"',
		);

		if ( 'ok' !== $row['status'] ) {
			if ( $ignored ) {
				$items[] = array(
					'label' => __( 'Reopen', 'garion-projetos-site-health-monitor' ),
					'icon'  => 'dashicons-image-rotate',
					'attrs' => 'data-gpshm-menu-action="reopen" data-gpshm-check-key="' . esc_attr( $row['key'] ) . '"',
				);
			} else {
				$items[] = array(
					'label' => __( 'Ignore temporarily', 'garion-projetos-site-health-monitor' ),
					'icon'  => 'dashicons-hidden',
					'attrs' => 'data-gpshm-menu-action="ignore" data-gpshm-check-key="' . esc_attr( $row['key'] ) . '"',
				);
			}
		}

		return $items;
	}

	private function render_issues( array $report ) {
		$problems = array_values( array_filter( $report, static fn( $row ) => 'ok' !== $row['status'] ) );

		$critical_count = count( array_filter( $problems, static fn( $row ) => 'critical' === $row['status'] ) );
		$warning_count  = count( array_filter( $problems, static fn( $row ) => 'warning' === $row['status'] ) );

		$preset_severity = isset( $_GET['severity'] ) ? sanitize_key( wp_unslash( $_GET['severity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter preset, no state change.

		GP_SHM_Admin_UI::card_start(
			__( 'Problems', 'garion-projetos-site-health-monitor' ),
			__( 'Every check currently reporting a warning or critical status, across all areas — search, filter, sort and act on them here.', 'garion-projetos-site-health-monitor' )
		);
		?>
		<div class="gpshm-issues-counters">
			<?php echo GP_SHM_Admin_UI::badge( sprintf( /* translators: %d: number of critical problems. */ _n( '%d critical', '%d critical', $critical_count, 'garion-projetos-site-health-monitor' ), $critical_count ), 'critical', 'dashicons-dismiss' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo GP_SHM_Admin_UI::badge( sprintf( /* translators: %d: number of warnings. */ _n( '%d warning', '%d warnings', $warning_count, 'garion-projetos-site-health-monitor' ), $warning_count ), 'warning', 'dashicons-warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="gpshm-filters">
			<label class="screen-reader-text" for="gpshm-issues-search"><?php esc_html_e( 'Search problems', 'garion-projetos-site-health-monitor' ); ?></label>
			<input type="search" id="gpshm-issues-search" placeholder="<?php esc_attr_e( 'Search problems...', 'garion-projetos-site-health-monitor' ); ?>" />

			<label class="screen-reader-text" for="gpshm-issues-severity"><?php esc_html_e( 'Filter by severity', 'garion-projetos-site-health-monitor' ); ?></label>
			<select id="gpshm-issues-severity">
				<option value=""><?php esc_html_e( 'All severities', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="critical" <?php selected( $preset_severity, 'critical' ); ?>><?php esc_html_e( 'Critical', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="warning" <?php selected( $preset_severity, 'warning' ); ?>><?php esc_html_e( 'Warning', 'garion-projetos-site-health-monitor' ); ?></option>
			</select>

			<label class="screen-reader-text" for="gpshm-issues-category"><?php esc_html_e( 'Filter by category', 'garion-projetos-site-health-monitor' ); ?></label>
			<select id="gpshm-issues-category">
				<option value=""><?php esc_html_e( 'All categories', 'garion-projetos-site-health-monitor' ); ?></option>
				<?php foreach ( $this->nav_groups() as $group_key => $group_label ) : ?>
					<option value="<?php echo esc_attr( $group_key ); ?>"><?php echo esc_html( $group_label ); ?></option>
				<?php endforeach; ?>
				<option value="logs"><?php esc_html_e( 'Logs', 'garion-projetos-site-health-monitor' ); ?></option>
			</select>

			<label class="screen-reader-text" for="gpshm-issues-status"><?php esc_html_e( 'Filter by status', 'garion-projetos-site-health-monitor' ); ?></label>
			<select id="gpshm-issues-status">
				<option value=""><?php esc_html_e( 'Active and ignored', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="active"><?php esc_html_e( 'Active only', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="ignored"><?php esc_html_e( 'Ignored only', 'garion-projetos-site-health-monitor' ); ?></option>
			</select>

			<label class="screen-reader-text" for="gpshm-issues-period"><?php esc_html_e( 'Filter by period', 'garion-projetos-site-health-monitor' ); ?></label>
			<select id="gpshm-issues-period">
				<option value=""><?php esc_html_e( 'Any time', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="1"><?php esc_html_e( 'Last 24 hours', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="7"><?php esc_html_e( 'Last 7 days', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="30"><?php esc_html_e( 'Last 30 days', 'garion-projetos-site-health-monitor' ); ?></option>
			</select>

			<label class="screen-reader-text" for="gpshm-issues-sort"><?php esc_html_e( 'Sort by', 'garion-projetos-site-health-monitor' ); ?></label>
			<select id="gpshm-issues-sort">
				<option value="priority"><?php esc_html_e( 'Sort by priority', 'garion-projetos-site-health-monitor' ); ?></option>
				<option value="date"><?php esc_html_e( 'Sort by detection date', 'garion-projetos-site-health-monitor' ); ?></option>
			</select>

			<button type="button" class="button" id="gpshm-issues-clear"><?php esc_html_e( 'Clear filters', 'garion-projetos-site-health-monitor' ); ?></button>
		</div>

		<div class="gpshm-bulk-bar" id="gpshm-bulk-bar" hidden>
			<span class="gpshm-bulk-bar__count"></span>
			<button type="button" class="button gpshm-bulk-ignore"><?php esc_html_e( 'Ignore selected', 'garion-projetos-site-health-monitor' ); ?></button>
			<button type="button" class="button gpshm-bulk-reopen"><?php esc_html_e( 'Reopen selected', 'garion-projetos-site-health-monitor' ); ?></button>
		</div>

		<div id="gpshm-issues-no-results" class="gpshm-empty-state" hidden>
			<?php echo GP_SHM_Admin_UI::icon( 'dashicons-search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<p class="gpshm-empty-state__title"><?php esc_html_e( 'No problems match the current filters.', 'garion-projetos-site-health-monitor' ); ?></p>
		</div>

		<?php
		if ( ! $problems ) {
			GP_SHM_Admin_UI::empty_state( __( 'No problems found.', 'garion-projetos-site-health-monitor' ), __( 'Every check is currently reporting a healthy status.', 'garion-projetos-site-health-monitor' ), 'dashicons-yes-alt' );
		} else {
			$this->render_checks_table( $problems, true );
			GP_SHM_Admin_UI::pagination( 10 );
		}
		GP_SHM_Admin_UI::card_end();
	}

	private function render_history() {
		$entries = GP_SHM_History::all();

		GP_SHM_Admin_UI::card_start( __( 'History', 'garion-projetos-site-health-monitor' ), __( 'A run is recorded here whenever the overall status changes, or at most once every 15 minutes.', 'garion-projetos-site-health-monitor' ) );

		if ( ! $entries ) {
			GP_SHM_Admin_UI::empty_state(
				__( 'No history yet.', 'garion-projetos-site-health-monitor' ),
				__( 'History starts recording from the next check — either a normal page load, "Run check now", or an automatic background check if enabled in Settings.', 'garion-projetos-site-health-monitor' )
			);
		} else {
			GP_SHM_Admin_UI::table_start(
				array(
					__( 'Date', 'garion-projetos-site-health-monitor' ),
					__( 'Status', 'garion-projetos-site-health-monitor' ),
					__( 'Score', 'garion-projetos-site-health-monitor' ),
					__( 'OK', 'garion-projetos-site-health-monitor' ),
					__( 'Warnings', 'garion-projetos-site-health-monitor' ),
					__( 'Critical', 'garion-projetos-site-health-monitor' ),
				)
			);
			foreach ( $entries as $entry ) {
				?>
				<tr>
					<td data-label="<?php esc_attr_e( 'Date', 'garion-projetos-site-health-monitor' ); ?>"><?php echo esc_html( get_date_from_gmt( $entry['timestamp'], 'Y-m-d H:i' ) ); ?></td>
					<td data-label="<?php esc_attr_e( 'Status', 'garion-projetos-site-health-monitor' ); ?>"><?php echo GP_SHM_Admin_UI::status_badge( $entry['overall'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td data-label="<?php esc_attr_e( 'Score', 'garion-projetos-site-health-monitor' ); ?>"><?php echo esc_html( $entry['score'] ); ?>/100</td>
					<td data-label="<?php esc_attr_e( 'OK', 'garion-projetos-site-health-monitor' ); ?>"><?php echo esc_html( $entry['counts']['ok'] ?? 0 ); ?></td>
					<td data-label="<?php esc_attr_e( 'Warnings', 'garion-projetos-site-health-monitor' ); ?>"><?php echo esc_html( $entry['counts']['warning'] ?? 0 ); ?></td>
					<td data-label="<?php esc_attr_e( 'Critical', 'garion-projetos-site-health-monitor' ); ?>"><?php echo esc_html( $entry['counts']['critical'] ?? 0 ); ?></td>
				</tr>
				<?php
			}
			GP_SHM_Admin_UI::table_end();
		}

		GP_SHM_Admin_UI::card_end();
	}

	private function render_logs( array $report ) {
		$checks_engine = new GP_SHM_Checks();
		$log_row       = null;

		foreach ( $report as $row ) {
			if ( 'debug_log' === $row['key'] ) {
				$log_row = $row;
				break;
			}
		}

		GP_SHM_Admin_UI::card_start( __( 'Logs', 'garion-projetos-site-health-monitor' ) );

		if ( $log_row ) {
			GP_SHM_Admin_UI::metric_card( $log_row['value'], __( 'Recent PHP errors', 'garion-projetos-site-health-monitor' ), GP_SHM_Admin_UI::status_tone( $log_row['status'] ), 'dashicons-warning' );
		}

		$lines = $checks_engine->get_recent_log_lines( 20 );
		?>
		<h3><?php esc_html_e( 'Recent debug.log entries', 'garion-projetos-site-health-monitor' ); ?></h3>
		<?php if ( empty( $lines ) ) : ?>
			<?php GP_SHM_Admin_UI::empty_state( __( 'No log entries to show.', 'garion-projetos-site-health-monitor' ), __( 'Either debug.log does not exist, or WP_DEBUG_LOG is not enabled.', 'garion-projetos-site-health-monitor' ) ); ?>
		<?php else : ?>
			<pre class="gpshm-log"><?php echo esc_html( implode( "\n", $lines ) ); ?></pre>
		<?php endif; ?>
		<?php
		GP_SHM_Admin_UI::card_end();
	}

	private function render_settings() {
		$settings = GP_SHM_Settings::get_all();
		?>
		<?php
		/*
		 * Round 1 never called this, so a settings save redirected back with
		 * no visible confirmation at all — settings_errors() is what prints
		 * WordPress's own "Settings saved." notice for options.php-based
		 * forms; this is the standard, native way to surface that feedback.
		 */
		settings_errors( 'gpshm_settings_group' );
		?>
		<form method="post" action="options.php" id="gpshm-settings-form">
			<?php settings_fields( 'gpshm_settings_group' ); ?>

			<?php GP_SHM_Admin_UI::card_start( __( 'Checks', 'garion-projetos-site-health-monitor' ), __( 'Thresholds used by the automated checks.', 'garion-projetos-site-health-monitor' ) ); ?>
			<div class="gpshm-settings-grid">
				<?php
				GP_SHM_Admin_UI::settings_field(
					array(
						'id'          => 'ssl_expiry_warning_days',
						'label'       => __( 'Warn when SSL certificate expires within', 'garion-projetos-site-health-monitor' ),
						'name'        => GP_SHM_Settings::OPTION_KEY . '[ssl_expiry_warning_days]',
						'value'       => $settings['ssl_expiry_warning_days'],
						'unit'        => __( 'days', 'garion-projetos-site-health-monitor' ),
						'min'         => 1,
						/* translators: %d: default value. */
						'description' => sprintf( __( 'Default: %d days.', 'garion-projetos-site-health-monitor' ), GP_SHM_Settings::defaults()['ssl_expiry_warning_days'] ),
					)
				);
				GP_SHM_Admin_UI::settings_field(
					array(
						'id'          => 'disk_space_warning_mb',
						'label'       => __( 'Warn when free disk space is below', 'garion-projetos-site-health-monitor' ),
						'name'        => GP_SHM_Settings::OPTION_KEY . '[disk_space_warning_mb]',
						'value'       => $settings['disk_space_warning_mb'],
						'unit'        => __( 'MB', 'garion-projetos-site-health-monitor' ),
						'min'         => 1,
						/* translators: %d: default value. */
						'description' => sprintf( __( 'Default: %d MB.', 'garion-projetos-site-health-monitor' ), GP_SHM_Settings::defaults()['disk_space_warning_mb'] ),
					)
				);
				GP_SHM_Admin_UI::settings_field(
					array(
						'id'          => 'cron_overdue_minutes',
						'label'       => __( 'Warn when a scheduled cron event is overdue by', 'garion-projetos-site-health-monitor' ),
						'name'        => GP_SHM_Settings::OPTION_KEY . '[cron_overdue_minutes]',
						'value'       => $settings['cron_overdue_minutes'],
						'unit'        => __( 'minutes', 'garion-projetos-site-health-monitor' ),
						'min'         => 1,
						/* translators: %d: default value. */
						'description' => sprintf( __( 'Default: %d minutes.', 'garion-projetos-site-health-monitor' ), GP_SHM_Settings::defaults()['cron_overdue_minutes'] ),
					)
				);
				GP_SHM_Admin_UI::settings_field(
					array(
						'id'          => 'history_retention',
						'label'       => __( 'History entries to keep', 'garion-projetos-site-health-monitor' ),
						'name'        => GP_SHM_Settings::OPTION_KEY . '[history_retention]',
						'value'       => $settings['history_retention'],
						'unit'        => __( 'entries', 'garion-projetos-site-health-monitor' ),
						'min'         => 5,
						'max'         => 50,
						'description' => __( 'Between 5 and 50 entries.', 'garion-projetos-site-health-monitor' ),
					)
				);
				?>
			</div>
			<?php GP_SHM_Admin_UI::card_end(); ?>

			<?php GP_SHM_Admin_UI::card_start( __( 'Monitoring', 'garion-projetos-site-health-monitor' ), __( 'Optional background checks via WP-Cron, independent of anyone loading this page.', 'garion-projetos-site-health-monitor' ) ); ?>
			<div class="gpshm-settings-grid">
				<?php
				GP_SHM_Admin_UI::settings_field(
					array(
						'id'          => 'monitoring_enabled',
						'label'       => __( 'Automatic monitoring', 'garion-projetos-site-health-monitor' ),
						'name'        => GP_SHM_Settings::OPTION_KEY . '[monitoring_enabled]',
						'type'        => 'checkbox',
						'value'       => $settings['monitoring_enabled'],
						'description' => __( 'When enabled, checks also run in the background on the schedule below and are recorded to History.', 'garion-projetos-site-health-monitor' ),
					)
				);
				GP_SHM_Admin_UI::settings_field(
					array(
						'id'          => 'monitoring_frequency',
						'label'       => __( 'Check frequency', 'garion-projetos-site-health-monitor' ),
						'name'        => GP_SHM_Settings::OPTION_KEY . '[monitoring_frequency]',
						'type'        => 'select',
						'value'       => $settings['monitoring_frequency'],
						'options'     => GP_SHM_Settings::monitoring_frequencies(),
						'description' => __( 'How often the background check runs when automatic monitoring is enabled above.', 'garion-projetos-site-health-monitor' ),
					)
				);
				?>
			</div>
			<?php GP_SHM_Admin_UI::card_end(); ?>

			<div class="gpshm-settings-actionbar">
				<span class="gpshm-settings-actionbar__status" id="gpshm-settings-status" aria-live="polite"></span>
				<button type="button" class="button" id="gpshm-settings-restore"><?php esc_html_e( 'Restore defaults', 'garion-projetos-site-health-monitor' ); ?></button>
				<?php submit_button( __( 'Save changes', 'garion-projetos-site-health-monitor' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
		<?php
	}
}
