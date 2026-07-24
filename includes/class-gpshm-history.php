<?php
/**
 * Rolling log of past check runs, stored in a single option (no custom table).
 * Only records real, computed summaries — never backfills or fabricates entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_History {

	const OPTION_KEY = 'gpshm_history';

	/**
	 * Safety ceiling kept regardless of the configured retention, so a
	 * misconfigured setting can never grow the option unbounded.
	 */
	const HARD_CAP = 50;

	/**
	 * Minimum minutes between two recorded entries when the overall status
	 * hasn't changed, so refreshing the dashboard repeatedly doesn't flood
	 * the log with near-duplicate rows.
	 */
	const MIN_INTERVAL_MINUTES = 15;

	/**
	 * All stored entries, newest first.
	 */
	public static function all() {
		$entries = get_option( self::OPTION_KEY, array() );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Summarize a report (as returned by GP_SHM_Checks::get_report()) into one entry.
	 */
	public static function summarize( array $report ) {
		$counts = array( 'ok' => 0, 'warning' => 0, 'critical' => 0 );

		foreach ( $report as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				++$counts[ $row['status'] ];
			}
		}

		$checks = new GP_SHM_Checks();

		return array(
			'timestamp'     => current_time( 'mysql', true ),
			'overall'       => $checks->get_overall_status( $report ),
			'counts'        => $counts,
			'score'         => $checks->get_health_score( $report ),
			/*
			 * Keys of every non-ok row at record time (ignored or not — same
			 * set render_issues() lists). Lets the UI compute "new since last
			 * recorded run" vs "recurring" for real, without fabricating a
			 * per-check timeline. Older entries predate this field and are
			 * read back with `?? array()` everywhere.
			 */
			'active_issues' => array_values(
				array_map(
					static fn( $row ) => $row['key'],
					array_filter( $report, static fn( $row ) => 'ok' !== $row['status'] )
				)
			),
		);
	}

	/**
	 * The `active_issues` list from the most recently recorded entry, or null
	 * if there is no history yet (no honest baseline to compare against).
	 */
	public static function previous_active_issues() {
		$entries = self::all();

		return isset( $entries[0] ) ? ( $entries[0]['active_issues'] ?? array() ) : null;
	}

	/**
	 * Record a report's summary, throttled so identical-status reloads don't
	 * spam the log. Always safe to call on every check run.
	 */
	public static function record( array $report ) {
		$entries = self::all();
		$entry   = self::summarize( $report );
		$latest  = $entries[0] ?? null;

		if ( $latest ) {
			$same_status  = $latest['overall'] === $entry['overall'];
			$latest_time  = strtotime( $latest['timestamp'] . ' UTC' );
			$too_soon     = $latest_time && ( time() - $latest_time ) < self::MIN_INTERVAL_MINUTES * MINUTE_IN_SECONDS;

			if ( $same_status && $too_soon ) {
				return;
			}
		}

		array_unshift( $entries, $entry );

		$retention = (int) GP_SHM_Settings::get( 'history_retention' );
		$limit     = max( 1, min( self::HARD_CAP, $retention ?: self::HARD_CAP ) );
		$entries   = array_slice( $entries, 0, $limit );

		update_option( self::OPTION_KEY, $entries, false );
	}
}
