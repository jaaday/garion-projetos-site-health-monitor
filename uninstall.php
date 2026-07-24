<?php
/**
 * Uninstall routine: removes every option created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gpshm_settings' );
delete_option( 'gpshm_history' );
delete_option( 'gpshm_issue_state' );

wp_clear_scheduled_hook( 'gpshm_scheduled_check' );
