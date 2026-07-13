=== Garion Projetos Site Health Monitor ===
Contributors: garionprojetos
Tags: health, monitoring, diagnostics, security, maintenance
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.1.0
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

This plugin does not send data to external servers. All checks run locally on your server.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from the "Plugins" screen.
3. Go to "Tools > Site Health Monitor" to view the diagnostics dashboard.

== Frequently Asked Questions ==

= Does this plugin send data to external services? =

No. Diagnostics run locally, only inspecting the site's own environment.

== Changelog ==

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.1.0 =
Initial release.
