<?php
/**
 * Per-check "ignored" state, stored in a single option (no custom table).
 *
 * This plugin computes every check live and has no persisted "problem" record
 * to mark as resolved — when the underlying condition changes, the check
 * simply reports 'ok' again on its own next run. The only state worth keeping
 * is which checks an admin has chosen to ignore (and why), so they stop
 * counting toward the health score and the Problems list until reopened.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Issue_State {

	const OPTION_KEY = 'gpshm_issue_state';

	/**
	 * A "reopened" badge is shown while reopened_at is within this many days
	 * AND the check is non-ok again — display-only, no decay job needed.
	 */
	const REOPENED_WINDOW_DAYS = 7;

	private static function defaults() {
		return array(
			'ignored'     => false,
			'reason'      => '',
			'ignored_at'  => null,
			'reopened_at' => null,
		);
	}

	/**
	 * All stored per-check states, keyed by check key.
	 */
	public static function all() {
		$state = get_option( self::OPTION_KEY, array() );

		return is_array( $state ) ? $state : array();
	}

	public static function get( $key ) {
		$state = self::all();

		return isset( $state[ $key ] ) ? wp_parse_args( $state[ $key ], self::defaults() ) : self::defaults();
	}

	public static function is_ignored( $key ) {
		return (bool) self::get( $key )['ignored'];
	}

	public static function ignore( $key, $reason = '' ) {
		$state          = self::prune( self::all() );
		$state[ $key ]  = array(
			'ignored'    => true,
			'reason'     => sanitize_textarea_field( $reason ),
			'ignored_at' => current_time( 'mysql', true ),
		);

		update_option( self::OPTION_KEY, $state, false );
	}

	/**
	 * Reopening keeps the row (instead of deleting it) so a "reopened" badge
	 * can be shown for a bounded window — see REOPENED_WINDOW_DAYS. This is
	 * the one behavior change in this pass to a persisted data model, called
	 * out explicitly since is_ignored() still only checks the boolean and is
	 * unaffected.
	 */
	public static function reopen( $key ) {
		$state         = self::prune( self::all() );
		$state[ $key ] = array(
			'ignored'     => false,
			'reason'      => '',
			'ignored_at'  => null,
			'reopened_at' => current_time( 'mysql', true ),
		);

		update_option( self::OPTION_KEY, $state, false );
	}

	/**
	 * Whether $key was reopened recently enough to still show a "reopened"
	 * badge. Purely a display condition; storage never grows beyond one row
	 * per known check regardless of how long ago reopened_at was.
	 */
	public static function is_recently_reopened( $key ) {
		$reopened_at = self::get( $key )['reopened_at'] ?? null;

		if ( ! $reopened_at ) {
			return false;
		}

		$timestamp = strtotime( $reopened_at . ' UTC' );

		return $timestamp && ( time() - $timestamp ) < self::REOPENED_WINDOW_DAYS * DAY_IN_SECONDS;
	}

	/**
	 * Drop any stored key that no longer corresponds to a real check, so
	 * renamed/removed checks don't leave orphaned entries behind forever.
	 */
	private static function prune( array $state ) {
		$known = class_exists( 'GP_SHM_Checks' ) ? GP_SHM_Checks::known_keys() : array_keys( $state );

		return array_intersect_key( $state, array_flip( $known ) );
	}
}
