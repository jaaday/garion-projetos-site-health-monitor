<?php
/**
 * Admin screen: health dashboard and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Admin_Page {

	const MENU_SLUG = 'gpshm-monitor';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'Garion Projetos Site Health Monitor', 'garion-projetos-site-health-monitor' ),
			__( 'Site Health Monitor', 'garion-projetos-site-health-monitor' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' ),
			'dashicons-heart',
			82
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'gpshm-admin', GPSHM_URL . 'assets/css/admin.css', array(), GPSHM_VERSION );
	}

	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.

		return in_array( $tab, array( 'dashboard', 'settings' ), true ) ? $tab : 'dashboard';
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Site Health Monitor', 'garion-projetos-site-health-monitor' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'dashboard' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'garion-projetos-site-health-monitor' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => 'settings' ), admin_url( 'admin.php' ) ) ); ?>" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'garion-projetos-site-health-monitor' ); ?></a>
			</h2>

			<div class="gpshm-tab-content">
				<?php
				if ( 'settings' === $tab ) {
					$this->render_settings();
				} else {
					$this->render_dashboard();
				}
				?>
			</div>
		</div>
		<?php
	}

	private function category_labels() {
		return array(
			'core'         => __( 'Core', 'garion-projetos-site-health-monitor' ),
			'security'     => __( 'Security', 'garion-projetos-site-health-monitor' ),
			'updates'      => __( 'Updates', 'garion-projetos-site-health-monitor' ),
			'server'       => __( 'Server', 'garion-projetos-site-health-monitor' ),
			'errors'       => __( 'Errors', 'garion-projetos-site-health-monitor' ),
			'connectivity' => __( 'Connectivity', 'garion-projetos-site-health-monitor' ),
		);
	}

	private function render_dashboard() {
		$checks_engine = new GP_SHM_Checks();
		$report        = $checks_engine->get_report();
		$overall       = $checks_engine->get_overall_status( $report );

		$grouped = array();
		foreach ( $report as $row ) {
			$grouped[ $row['category'] ][] = $row;
		}
		?>
		<p>
			<strong><?php esc_html_e( 'Overall status:', 'garion-projetos-site-health-monitor' ); ?></strong>
			<span class="gpshm-status gpshm-status-<?php echo esc_attr( $overall ); ?>"><?php echo esc_html( strtoupper( $overall ) ); ?></span>
		</p>
		<p class="description">
			<?php esc_html_e( 'A lightweight public status endpoint is available for external uptime monitors:', 'garion-projetos-site-health-monitor' ); ?>
			<code><?php echo esc_html( rest_url( GP_SHM_REST_Controller::NAMESPACE_ . '/status' ) ); ?></code>
		</p>

		<?php foreach ( $this->category_labels() as $key => $label ) : ?>
			<?php if ( empty( $grouped[ $key ] ) ) { continue; } ?>
			<h2><?php echo esc_html( $label ); ?></h2>
			<table class="widefat striped">
				<tbody>
					<?php foreach ( $grouped[ $key ] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['label'] ); ?></td>
							<td><span class="gpshm-status gpshm-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $row['value'] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Recent debug.log entries', 'garion-projetos-site-health-monitor' ); ?></h2>
		<?php $lines = $checks_engine->get_recent_log_lines( 20 ); ?>
		<?php if ( empty( $lines ) ) : ?>
			<p><?php esc_html_e( 'No log entries to show.', 'garion-projetos-site-health-monitor' ); ?></p>
		<?php else : ?>
			<pre class="gpshm-log"><?php echo esc_html( implode( "\n", $lines ) ); ?></pre>
		<?php endif; ?>
		<?php
	}

	private function render_settings() {
		$settings = GP_SHM_Settings::get_all();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'gpshm_settings_group' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="ssl_expiry_warning_days"><?php esc_html_e( 'Warn when SSL certificate expires within (days)', 'garion-projetos-site-health-monitor' ); ?></label></th>
					<td><input type="number" id="ssl_expiry_warning_days" min="1" class="small-text" name="<?php echo esc_attr( GP_SHM_Settings::OPTION_KEY ); ?>[ssl_expiry_warning_days]" value="<?php echo esc_attr( $settings['ssl_expiry_warning_days'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="disk_space_warning_mb"><?php esc_html_e( 'Warn when free disk space is below (MB)', 'garion-projetos-site-health-monitor' ); ?></label></th>
					<td><input type="number" id="disk_space_warning_mb" min="1" class="small-text" name="<?php echo esc_attr( GP_SHM_Settings::OPTION_KEY ); ?>[disk_space_warning_mb]" value="<?php echo esc_attr( $settings['disk_space_warning_mb'] ); ?>" /></td>
				</tr>
				<tr>
					<th><label for="cron_overdue_minutes"><?php esc_html_e( 'Warn when a scheduled cron event is overdue by (minutes)', 'garion-projetos-site-health-monitor' ); ?></label></th>
					<td><input type="number" id="cron_overdue_minutes" min="1" class="small-text" name="<?php echo esc_attr( GP_SHM_Settings::OPTION_KEY ); ?>[cron_overdue_minutes]" value="<?php echo esc_attr( $settings['cron_overdue_minutes'] ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}
}
