<?php
/**
 * Plugin Name: Site Health Monitor
 * Description: WordPress diagnostics dashboard: PHP, SSL, outdated plugins/themes, cron, disk space and database status.
 * Version: 0.1.0
 * Author: Garion Projetos
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: site-health-monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSHM_VERSION', '0.1.0' );
define( 'WPSHM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPSHM_URL', plugin_dir_url( __FILE__ ) );
