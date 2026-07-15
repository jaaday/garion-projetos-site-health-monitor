=== Garion Projetos Site Health Monitor ===
Contributors: garionprojetos
Tags: health, monitoring, diagnostics, security, maintenance
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress diagnostics dashboard: PHP, SSL, outdated plugins/themes, cron, disk space and database status.

== Description ==

Site Health Monitor centralizes site health checks in a single dashboard:

* WordPress version
* PHP version
* SSL certificate
* Outdated plugins and themes
* Disk space
* Cron job status
* Recent errors (debug.log)
* File permissions
* Database status
* Active cache
* Connectivity to services configured on the site

A lightweight, public REST endpoint (`/wp-json/garion-projetos-site-health-monitor/v1/status`) exposes only the overall status (ok/warning/critical), so external uptime monitoring services can poll your site's health without exposing detailed server information.

= External connectivity =

To test outbound connectivity, this plugin makes one request per dashboard view to `api.wordpress.org` (the same endpoint WordPress core itself uses to check for updates) and, when your site runs over HTTPS, opens a plain TLS connection to your own domain to read its SSL certificate expiry date. No site content, visitor data or credentials are sent in either case.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the "Plugins" screen.
3. Open the new "Site Health Monitor" menu in the admin sidebar to view the dashboard, or configure warning thresholds under its Settings tab.

== Frequently Asked Questions ==

= Does this plugin send data to external services? =

It makes a small connectivity check to api.wordpress.org and, for the SSL check, connects to your own site's domain to read the certificate. No site or visitor data is transmitted.

= Is the public status endpoint safe to expose? =

Yes. It only returns an overall ok/warning/critical status, never the detailed report, plugin list, PHP version or log contents.

== Changelog ==

= 0.2.0 =
* Implemented all planned checks: WordPress/PHP version, SSL certificate expiry, outdated plugins/themes, disk space, WP-Cron status, recent debug.log entries, file permissions, database status, object cache and outbound connectivity.
* Added a health dashboard grouped by category, with a Settings tab for warning thresholds.
* Added a public, lightweight REST status endpoint for external uptime monitoring.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.2.0 =
Adds full functionality. Review the new "Site Health Monitor" admin menu after updating.
