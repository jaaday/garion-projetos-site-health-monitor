<?php
/**
 * Uninstall routine: removes the settings option created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gpshm_settings' );
