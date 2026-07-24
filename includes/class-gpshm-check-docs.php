<?php
/**
 * Static, factual documentation for every check GP_SHM_Checks can produce:
 * what it measures, why it typically fails, how to fix it, and where.
 *
 * This is authored explanatory copy about what each check MEANS — it never
 * supplies a value, status or count (those stay 100% live, computed by
 * GP_SHM_Checks on every request). Keyed by GP_SHM_Checks::known_keys().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Check_Docs {

	/**
	 * @return array key => { description, cause, recommendation, fix_location }
	 */
	public static function all() {
		return array(
			'wordpress_version'          => array(
				'description'    => __( 'Whether WordPress core is on the latest available version.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'Automatic background updates are disabled, or a major update requires manual confirmation.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Update WordPress from the Dashboard > Updates screen as soon as possible.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'wp-admin > Dashboard > Updates', 'garion-projetos-site-health-monitor' ),
			),
			'php_version'                => array(
				'description'    => __( 'Whether the server runs a PHP version still receiving security support.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'The hosting plan has not been upgraded to a newer PHP version.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Ask your hosting provider to upgrade PHP, then test the site on a staging copy before switching production.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'Hosting control panel > PHP version', 'garion-projetos-site-health-monitor' ),
			),
			'ssl_https'                  => array(
				'description'    => __( 'Whether the current request reached the site over HTTPS.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'No SSL certificate is installed, or the site is not forcing HTTPS redirects.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Install an SSL certificate and force HTTPS for all requests (via the host or a redirect rule).', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'Hosting control panel > SSL/TLS, or Settings > General (Site/WordPress Address)', 'garion-projetos-site-health-monitor' ),
			),
			'ssl_certificate_expiry'     => array(
				'description'    => __( 'How many days remain before the SSL certificate expires.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'The certificate is nearing its expiry date and has not been renewed yet, or automatic renewal failed.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Renew the certificate before it expires, and confirm automatic renewal is active if the host supports it.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'Hosting control panel > SSL/TLS', 'garion-projetos-site-health-monitor' ),
			),
			'outdated_plugins'           => array(
				'description'    => __( 'Number of installed plugins with an available update.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'Plugin authors released newer versions that have not been installed yet.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Review and apply plugin updates, ideally on staging first for major version jumps.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'wp-admin > Plugins', 'garion-projetos-site-health-monitor' ),
			),
			'outdated_themes'            => array(
				'description'    => __( 'Number of installed themes with an available update.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'Theme authors released newer versions that have not been installed yet.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Review and apply theme updates, checking any customizations for compatibility first.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'wp-admin > Appearance > Themes', 'garion-projetos-site-health-monitor' ),
			),
			'disk_space'                 => array(
				'description'    => __( 'Free disk space on the server, compared against the configured warning threshold.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'Media uploads, backups, logs or database growth have consumed most of the available storage.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Remove unused media/backups/logs, or upgrade the hosting storage plan.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'Hosting control panel > File Manager / Storage, or Settings > Disk space warning threshold', 'garion-projetos-site-health-monitor' ),
			),
			'cron'                       => array(
				'description'    => __( 'Whether scheduled WP-Cron events are running on time.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'The site has very low traffic (WP-Cron runs on page loads by default), or DISABLE_WP_CRON is set without a real system cron replacing it.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Set up a real system cron job calling wp-cron.php on a fixed interval instead of relying on page-load triggers.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'Hosting control panel > Cron Jobs, or wp-config.php', 'garion-projetos-site-health-monitor' ),
			),
			'debug_log'                  => array(
				'description'    => __( 'Whether debug.log exists and was modified recently, indicating active PHP errors or warnings.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'A plugin, theme or PHP version mismatch is triggering warnings, notices or errors on the site.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Open the Logs tab to read the recent entries and identify which file/line is generating them.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'wp-content/debug.log', 'garion-projetos-site-health-monitor' ),
			),
			'file_permissions_wpconfig'  => array(
				'description'    => __( 'Whether wp-config.php is writable by other users on the server (world-writable).', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'File permissions were set too permissively (commonly 666 or 777) during an upload or a hosting misconfiguration.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Restrict wp-config.php permissions to 600 or 640 so only the owning user can write to it.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'File manager or SSH/SFTP, in the site root (wp-config.php)', 'garion-projetos-site-health-monitor' ),
			),
			'file_permissions_wpcontent' => array(
				'description'    => __( 'Whether wp-content is writable, which WordPress needs for uploads, automatic updates and cache files.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'File ownership or permissions prevent the PHP process from writing to wp-content.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Set wp-content to 755 (directories) and confirm it is owned by the same user the web server runs as.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'File manager or SSH/SFTP, in the site root (wp-content)', 'garion-projetos-site-health-monitor' ),
			),
			'database'                   => array(
				'description'    => __( 'Whether the database is reachable and the core WordPress tables exist.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'Incorrect database credentials in wp-config.php, or the database server is down.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Verify DB_HOST/DB_NAME/DB_USER/DB_PASSWORD in wp-config.php and confirm the database server is running.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'wp-config.php, or hosting control panel > Databases', 'garion-projetos-site-health-monitor' ),
			),
			'object_cache'               => array(
				'description'    => __( 'Whether a persistent object cache (Redis, Memcached, etc.) is active instead of the default per-request database cache.', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'No persistent object cache backend is installed or configured on this server.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'This is informational, not a defect: a persistent object cache can improve performance on busy sites but is optional.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'Hosting control panel (Redis/Memcached add-on) plus a compatible drop-in plugin', 'garion-projetos-site-health-monitor' ),
			),
			'outbound_connectivity'      => array(
				'description'    => __( 'Whether the server can make outgoing HTTP requests to api.wordpress.org (used for core/plugin/theme update checks).', 'garion-projetos-site-health-monitor' ),
				'cause'          => __( 'A server or network firewall is blocking outbound HTTP(S) requests.', 'garion-projetos-site-health-monitor' ),
				'recommendation' => __( 'Ask your hosting provider to allow outbound HTTPS requests to wordpress.org domains; without it, update checks cannot run.', 'garion-projetos-site-health-monitor' ),
				'fix_location'   => __( 'Hosting control panel > Firewall / Outbound rules', 'garion-projetos-site-health-monitor' ),
			),
		);
	}

	public static function get( $key ) {
		$all = self::all();

		return $all[ $key ] ?? array(
			'description'    => '',
			'cause'          => '',
			'recommendation' => '',
			'fix_location'   => '',
		);
	}
}
