=== Garion Projetos Site Health Monitor ===
Contributors: garionprojetos
Tags: health, monitoring, diagnostics, security, maintenance
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 0.4.3
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

= 0.4.3 =
* Fixed 6 WordPress Coding Standards errors and 2 warnings surfaced by a Plugin Check scan: missing translators comments on two JS-facing i18n strings (`%d selected`, `Page %1$d of %2$d`), a missing escaping-exception comment on a hardcoded HTML tag name, mismatched/misplaced `phpcs:ignore` comments on `fopen()` and a direct `$wpdb->get_var()` call (the suppression comment was one line off from where the rule actually anchors), and a missing suppression comment on `is_writable()` — also de-duplicated a redundant double call to `is_writable( WP_CONTENT_DIR )` into one. All are code-quality/documentation fixes; no check logic or output changed.

= 0.4.2 =
* Changed: the grouped navigation labels ("Monitoring", "Diagnostics", "System") looked like disconnected floating text above their tabs. Each group is now its own bordered, shadowed card so the label is visually tied to the buttons below it.

= 0.4.1 =
* Fixed: the WordPress version check called an admin-only function (`get_core_updates()`) without loading its file first, causing a fatal error whenever a check ran through the REST API (the "Run check now" button) instead of a normal admin page load.
* Fixed: the database check compared `(int)` against a table name string instead of `(bool)`, which made it always report "Core tables not found" even when the database was fully reachable and the tables existed. Both are correctness fixes to existing checks — no thresholds, labels or check semantics changed.
* Changed: removed the always-visible "Actions" column/menu from the collapsed checks table. Actions (re-run, copy technical data, ignore/reopen) now only appear at the bottom of a check's detail panel once it's expanded, since "view details"/"view recommendation" no longer make sense as separate actions when the panel showing them is already open.
* Fixed: the score-hero ring aligned its number by text baseline instead of centering it, and dashicon glyphs (e.g. on the "Run check now" button) weren't vertically centered within their own icon box — both are now properly centered.

= 0.4.0 =
* Redesigned the admin interface again for a more professional, SaaS-style look: a compact header with monitoring status, last-checked time and the "Run check now" action in a single row; grouped, badge-annotated navigation (Monitoring / Diagnostics / System); a two-column health-score hero with a comparison to the previous recorded check, a "How is this score calculated?" breakdown, and category summary cards with no dead space.
* Every check row now expands into a detail view: description, current/recommended value, likely cause, recommendation and likely fix location (static, factual documentation — the values themselves stay 100% live).
* The Problems tab is now a resolution center: search, severity/category/status/period filters, sorting, pagination and bulk ignore/reopen, with "new" and "recurring" badges derived from the history log (additive `active_issues` field — old entries remain fully compatible via a safe fallback).
* Reopening a check now keeps a short-lived record (7 days) instead of discarding it, so a "Reopened" badge can be shown; ignoring keeps its confirmation/reason/undo-toast flow.
* Added an optional, real WP-Cron background check ("Automatic monitoring" + "Check frequency" in Settings) as an alternative to only checking on page load or "Run check now" — disabled by default; nothing changes unless explicitly enabled.
* Settings reorganized into "Checks" and "Monitoring" cards with labels, units, defaults, help text, save/restore-defaults actions and an unsaved-changes warning; the public status endpoint is now its own card with copy and a real self-test button.
* Full interface typography, spacing, component and responsiveness pass (1440 down to 375px), plus keyboard/aria-current navigation and focus-visible states throughout.
* Added complete translations: Portuguese (Brazil), Spanish, Russian and Simplified Chinese, loaded via `load_plugin_textdomain()` from the new `languages/` directory — resolves the previous mix of translated WordPress chrome and untranslated plugin text.
* No existing check's logic, thresholds or labels changed, and the `/status` and `/report` REST contracts are unchanged.

= 0.3.0 =
* Redesigned the admin interface: a new dashboard with a 0-100 health score, per-area tabs (WordPress, Security, Performance, Server), a Problems view with search/severity filtering, a History log, and a dedicated Logs tab.
* Added a lightweight rolling history log (recorded on every check run, throttled to avoid duplicate entries) so trends over time can be reviewed on the new History tab.
* Added the ability to ignore a specific check (with an optional reason) so it stops counting toward the health score and the Problems list until reopened. Checks are still computed live — ignoring one does not disable it, only acknowledges its current state.
* Added authenticated REST routes (`manage_options` + nonce) to run a fresh check and to ignore/reopen a check from the admin screen without a full page reload. The existing public `/status` endpoint is unchanged.
* No existing check's logic, thresholds or labels changed. No data migration is required.

= 0.2.0 =
* Implemented all planned checks: WordPress/PHP version, SSL certificate expiry, outdated plugins/themes, disk space, WP-Cron status, recent debug.log entries, file permissions, database status, object cache and outbound connectivity.
* Added a health dashboard grouped by category, with a Settings tab for warning thresholds.
* Added a public, lightweight REST status endpoint for external uptime monitoring.

= 0.1.0 =
* Initial release.

== Upgrade Notice ==

= 0.2.0 =
Adds full functionality. Review the new "Site Health Monitor" admin menu after updating.
