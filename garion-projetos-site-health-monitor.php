<?php
/**
 * Plugin Name: Garion Projetos Site Health Monitor
 * Description: WordPress diagnostics dashboard: PHP, SSL, outdated plugins/themes, cron, disk space and database status.
 * Version: 0.4.3
 * Author: Garion Projetos
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: garion-projetos-site-health-monitor
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GPSHM_VERSION', '0.4.3' );
define( 'GPSHM_PATH', plugin_dir_path( __FILE__ ) );
define( 'GPSHM_URL', plugin_dir_url( __FILE__ ) );

require_once GPSHM_PATH . 'includes/class-gpshm-settings.php';
require_once GPSHM_PATH . 'includes/class-gpshm-history.php';
require_once GPSHM_PATH . 'includes/class-gpshm-issue-state.php';
require_once GPSHM_PATH . 'includes/class-gpshm-checks.php';
require_once GPSHM_PATH . 'includes/class-gpshm-check-docs.php';
require_once GPSHM_PATH . 'includes/class-gpshm-cron.php';
require_once GPSHM_PATH . 'includes/class-gpshm-rest-controller.php';
require_once GPSHM_PATH . 'admin/class-gpshm-admin-ui.php';
require_once GPSHM_PATH . 'admin/class-gpshm-admin-page.php';

register_activation_hook( __FILE__, 'gpshm_activate' );
register_deactivation_hook( __FILE__, 'gpshm_deactivate' );

/**
 * Only sets autoload=false on the new options up front; both classes already
 * fall back to an empty array via get_option() if this never ran (e.g. on a
 * plugin update without reactivation), so this is an optimization, not a
 * correctness requirement. Reschedule is safe to call unconditionally:
 * monitoring_enabled defaults to false, so a fresh install schedules nothing.
 */
function gpshm_activate() {
	add_option( GP_SHM_History::OPTION_KEY, array(), '', false );
	add_option( GP_SHM_Issue_State::OPTION_KEY, array(), '', false );

	GP_SHM_Cron::reschedule();
}

function gpshm_deactivate() {
	GP_SHM_Cron::deactivate();
}

/**
 * Load the plugin's own .mo files. Not distributed via wordpress.org, so
 * translations are never fetched automatically and must ship in /languages.
 */
function gpshm_load_textdomain() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- this plugin is not distributed via wordpress.org, so WP never auto-loads its translations; this call is required for the bundled languages/*.mo files to load at all.
	load_plugin_textdomain( 'garion-projetos-site-health-monitor', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'init', 'gpshm_load_textdomain' );
add_action( 'plugins_loaded', 'gpshm_init' );

function gpshm_init() {
	new GP_SHM_Settings();
	new GP_SHM_Cron();
	new GP_SHM_REST_Controller();

	if ( is_admin() ) {
		new GP_SHM_Admin_Page();
	}
}
