<?php
/**
 * Plugin Name: WP Site Health Monitor
 * Description: Painel de diagnostico para WordPress: PHP, SSL, plugins/temas desatualizados, cron, disco e banco de dados.
 * Version: 0.1.0
 * Author: Garion Projetos
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-site-health-monitor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPSHM_VERSION', '0.1.0' );
define( 'WPSHM_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPSHM_URL', plugin_dir_url( __FILE__ ) );
