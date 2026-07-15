<?php
/**
 * Plugin Name: Garion Projetos Site Health Monitor
 * Description: WordPress diagnostics dashboard: PHP, SSL, outdated plugins/themes, cron, disk space and database status.
 * Version: 0.2.0
 * Author: Garion Projetos
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: garion-projetos-site-health-monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GPSHM_VERSION', '0.2.0' );
define( 'GPSHM_PATH', plugin_dir_path( __FILE__ ) );
define( 'GPSHM_URL', plugin_dir_url( __FILE__ ) );

require_once GPSHM_PATH . 'includes/class-gpshm-settings.php';
require_once GPSHM_PATH . 'includes/class-gpshm-checks.php';
require_once GPSHM_PATH . 'includes/class-gpshm-rest-controller.php';
require_once GPSHM_PATH . 'admin/class-gpshm-admin-page.php';

add_action( 'plugins_loaded', 'gpshm_init' );

function gpshm_init() {
	new GP_SHM_Settings();
	new GP_SHM_REST_Controller();

	if ( is_admin() ) {
		new GP_SHM_Admin_Page();
	}
}
