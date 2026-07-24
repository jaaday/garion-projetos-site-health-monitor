<?php
/**
 * Reusable, escaped presentation helpers shared by every admin screen: header,
 * grouped navigation, badges, cards, metric/category tiles, the health-score
 * hero + context + explanation, action menu, expandable detail rows,
 * pagination, settings fields, the public-endpoint card, alerts, empty
 * states, tabs, tooltips and the responsive table shell.
 *
 * Every method either returns a pre-escaped HTML string or echoes directly;
 * callers are still responsible for escaping any dynamic values they pass in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GP_SHM_Admin_UI {

	/**
	 * Map a check/overall status to a visual tone slug used by badges and cards.
	 */
	public static function status_tone( $status ) {
		$map = array(
			'ok'        => 'success',
			'warning'   => 'warning',
			'critical'  => 'critical',
			'ignored'   => 'neutral',
			'reopened'  => 'info',
		);

		return $map[ $status ] ?? 'neutral';
	}

	public static function status_icon( $status ) {
		$map = array(
			'ok'       => 'dashicons-yes-alt',
			'warning'  => 'dashicons-warning',
			'critical' => 'dashicons-dismiss',
			'ignored'  => 'dashicons-hidden',
			'reopened' => 'dashicons-image-rotate',
		);

		return $map[ $status ] ?? 'dashicons-marker';
	}

	/**
	 * A small inline dashicon. $label is optional visually-hidden text for screen readers
	 * when the icon is used without adjacent visible text.
	 */
	public static function icon( $dashicon, $label = '' ) {
		$html = '<span class="dashicons ' . esc_attr( $dashicon ) . ' gpshm-icon" aria-hidden="true"></span>';

		if ( $label ) {
			$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
		}

		return $html;
	}

	/**
	 * A pill badge. Never relies on color alone: always paired with an icon and text.
	 */
	public static function badge( $label, $tone = 'neutral', $icon = '' ) {
		$tone = sanitize_html_class( $tone );

		return sprintf(
			'<span class="gpshm-badge gpshm-badge--%1$s">%2$s%3$s</span>',
			esc_attr( $tone ),
			$icon ? self::icon( $icon ) : '',
			esc_html( $label )
		);
	}

	public static function status_badge( $status, $label = '' ) {
		return self::badge( $label ? $label : ucfirst( $status ), self::status_tone( $status ), self::status_icon( $status ) );
	}

	/**
	 * Plugin header: icon, name, version, description, current monitoring
	 * status, last-checked time and the primary "run check" action, all in a
	 * single compact row instead of the action sitting on its own line below.
	 */
	public static function header( $title, $version, $description, $overall_status, $checked_at_label, $run_button_html ) {
		echo '<div class="gpshm-header">';
		echo '<div class="gpshm-header__icon" aria-hidden="true"><span class="dashicons dashicons-heart"></span></div>';
		echo '<div class="gpshm-header__text">';
		printf(
			'<h1 class="gpshm-header__title">%1$s <span class="gpshm-header__version">v%2$s</span></h1>',
			esc_html( $title ),
			esc_html( $version )
		);
		echo '<p class="gpshm-header__desc">' . esc_html( $description ) . '</p>';
		echo '</div>';

		echo '<div class="gpshm-header__meta">';
		printf(
			'<span class="gpshm-header__meta-item">%1$s</span>',
			self::status_badge( $overall_status ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_badge() escapes its own parts.
		);
		printf(
			'<span class="gpshm-header__meta-item gpshm-header__checked">%1$s%2$s</span>',
			self::icon( 'dashicons-clock' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			esc_html( $checked_at_label )
		);
		echo '</div>';

		echo '<div class="gpshm-header__action">' . $run_button_html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the caller with its own escaping.
		echo '</div>';
	}

	/**
	 * Grouped tab navigation. $groups is group_label => [ slug => label ].
	 * $badges is slug => open-problem count (0 or absent renders no badge).
	 */
	public static function tabs( array $groups, $current, $base_url, array $icons = array(), array $badges = array() ) {
		echo '<div class="gpshm-nav" role="tablist" aria-label="' . esc_attr__( 'Site Health Monitor sections', 'garion-projetos-site-health-monitor' ) . '">';

		foreach ( $groups as $group_label => $tabs ) {
			echo '<div class="gpshm-nav__group">';
			echo '<span class="gpshm-nav__group-label">' . esc_html( $group_label ) . '</span>';
			echo '<div class="gpshm-nav__links">';

			foreach ( $tabs as $slug => $label ) {
				$active = $current === $slug;
				$count  = (int) ( $badges[ $slug ] ?? 0 );

				printf(
					'<a role="tab" aria-selected="%1$s"%2$s href="%3$s" class="gpshm-nav__link%4$s">%5$s<span class="gpshm-nav__link-text">%6$s</span>%7$s</a>',
					$active ? 'true' : 'false',
					$active ? ' aria-current="page"' : '',
					esc_url( add_query_arg( array( 'tab' => $slug ), $base_url ) ),
					$active ? ' gpshm-nav__link--active' : '',
					isset( $icons[ $slug ] ) ? self::icon( $icons[ $slug ] ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
					esc_html( $label ),
					$count > 0 ? '<span class="gpshm-nav__badge">' . esc_html( (string) $count ) . '</span>' : ''
				);
			}

			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * A metric tile for the dashboard grid. When $href is set the whole tile
	 * becomes a link (to a pre-filtered Problems view); $context is an
	 * optional contextual sentence under the value.
	 */
	public static function metric_card( $value, $label, $tone = 'default', $icon = '', $context = '', $href = '' ) {
		$tag        = $href ? 'a' : 'div';
		$href_attr  = $href ? ' href="' . esc_url( $href ) . '"' : '';
		$context_ht = $context ? '<span class="gpshm-metric__context">' . esc_html( $context ) . '</span>' : '';

		printf(
			'<%1$s%2$s class="gpshm-metric gpshm-metric--%3$s">%4$s<div class="gpshm-metric__body"><strong class="gpshm-metric__value">%5$s</strong><span class="gpshm-metric__label">%6$s</span>%7$s</div></%1$s>',
			$tag, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded to 'a' or 'div' above, never user input.
			$href_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url() applied above.
			esc_attr( sanitize_html_class( $tone ) ),
			$icon ? '<span class="gpshm-metric__icon">' . self::icon( $icon ) . '</span>' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			esc_html( $value ),
			esc_html( $label ),
			$context_ht // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html() above.
		);
	}

	/**
	 * A category summary tile (WordPress/Security/Performance/Server) for the
	 * Overview grid: its own score, status, totals, top problem and a link
	 * to the group's own tab.
	 */
	public static function category_card( $label, $status, $total, $open_count, $top_problem, $href ) {
		printf(
			'<a class="gpshm-category-card gpshm-category-card--%1$s" href="%2$s">',
			esc_attr( self::status_tone( $status ) ),
			esc_url( $href )
		);
		echo '<div class="gpshm-category-card__head"><span class="gpshm-category-card__label">' . esc_html( $label ) . '</span>' . self::status_badge( $status ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_badge() escapes its own parts.

		printf(
			'<span class="gpshm-category-card__count">%1$s</span>',
			esc_html(
				$open_count
					? sprintf( /* translators: 1: number of problems, 2: total checks in this category. */ __( '%1$d of %2$d checks need attention', 'garion-projetos-site-health-monitor' ), $open_count, $total )
					: sprintf( /* translators: %d: total checks in this category. */ __( 'All %d checks passing', 'garion-projetos-site-health-monitor' ), $total )
			)
		);

		if ( $top_problem ) {
			echo '<span class="gpshm-category-card__top">' . esc_html( $top_problem ) . '</span>';
		}

		echo '<span class="gpshm-category-card__link">' . esc_html__( 'View details', 'garion-projetos-site-health-monitor' ) . ' &rarr;</span>';
		echo '</a>';
	}

	/**
	 * The big score number + tier ring shown at the top of the Overview tab
	 * (left column of the health-hero row).
	 */
	public static function score_hero( $score, $tier_label, $overall ) {
		printf(
			'<div class="gpshm-score-hero gpshm-score-hero--%1$s"><div class="gpshm-score-hero__ring"><span class="gpshm-score-hero__value">%2$s</span><span class="gpshm-score-hero__max">/100</span></div><div class="gpshm-score-hero__text"><span class="gpshm-score-hero__tier">%3$s</span>%4$s</div></div>',
			esc_attr( sanitize_html_class( self::status_tone( $overall ) ) ),
			esc_html( $score ),
			esc_html( $tier_label ),
			self::status_badge( $overall ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_badge() escapes its own parts.
		);
	}

	/**
	 * Right column of the health-hero row: fills the dead space next to the
	 * score ring with real, already-computed context instead of leaving it
	 * empty. $args: checked_at, delta (int|null), total_checks (int),
	 * top_problem (string|null), critical_url (string|null).
	 */
	public static function score_context( array $args ) {
		echo '<div class="gpshm-score-context">';

		printf(
			'<p class="gpshm-score-context__row">%1$s%2$s</p>',
			self::icon( 'dashicons-clock' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html(
				sprintf( /* translators: %s: date/time of this check run. */ __( 'Last checked: %s', 'garion-projetos-site-health-monitor' ), $args['checked_at'] )
			)
		);

		if ( null !== $args['delta'] ) {
			$delta = (int) $args['delta'];
			if ( 0 === $delta ) {
				$delta_text = __( 'Same score as the last recorded check', 'garion-projetos-site-health-monitor' );
				$delta_tone = 'neutral';
			} elseif ( $delta > 0 ) {
				/* translators: %d: number of points the score improved by. */
				$delta_text = sprintf( __( '+%d points since the last recorded check', 'garion-projetos-site-health-monitor' ), $delta );
				$delta_tone = 'success';
			} else {
				/* translators: %d: number of points the score dropped by. */
				$delta_text = sprintf( __( '%d points since the last recorded check', 'garion-projetos-site-health-monitor' ), $delta );
				$delta_tone = 'critical';
			}
			printf( '<p class="gpshm-score-context__row gpshm-score-context__delta gpshm-score-context__delta--%1$s">%2$s</p>', esc_attr( $delta_tone ), esc_html( $delta_text ) );
		} else {
			echo '<p class="gpshm-score-context__row gpshm-score-context__delta">' . esc_html__( 'First recorded check — no previous score to compare yet.', 'garion-projetos-site-health-monitor' ) . '</p>';
		}

		printf(
			'<p class="gpshm-score-context__row">%1$s</p>',
			esc_html(
				sprintf( /* translators: %d: total number of checks that ran. */ _n( '%d check performed', '%d checks performed', $args['total_checks'], 'garion-projetos-site-health-monitor' ), $args['total_checks'] )
			)
		);

		if ( $args['top_problem'] ) {
			printf(
				'<p class="gpshm-score-context__row gpshm-score-context__top">%1$s%2$s</p>',
				self::icon( 'dashicons-flag' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html(
					sprintf( /* translators: %s: label of the most severe active problem. */ __( 'Top problem: %s', 'garion-projetos-site-health-monitor' ), $args['top_problem'] )
				)
			);

			if ( $args['critical_url'] ) {
				printf(
					'<a class="button" href="%1$s">%2$s</a>',
					esc_url( $args['critical_url'] ),
					esc_html__( 'View critical problems', 'garion-projetos-site-health-monitor' )
				);
			}
		} else {
			echo '<p class="gpshm-score-context__row gpshm-score-context__top gpshm-score-context__top--ok">' . self::icon( 'dashicons-yes-alt' ) . esc_html__( 'No active problems right now.', 'garion-projetos-site-health-monitor' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		echo '</div>';
	}

	/**
	 * "How is this score calculated?" disclosure. Pure native <details>, no
	 * JS required. $contributors is a list of [label, status] for every
	 * active non-ok row; $ignored_count is the number excluded from scoring.
	 */
	public static function score_explanation( $total_checks, $weight_warning, $weight_critical, array $contributors, $ignored_count, $checked_at_label ) {
		echo '<details class="gpshm-disclosure gpshm-score-explanation">';
		echo '<summary>' . esc_html__( 'How is this score calculated?', 'garion-projetos-site-health-monitor' ) . '</summary>';
		echo '<div class="gpshm-disclosure__body">';

		printf( '<p>%s</p>', esc_html( sprintf( /* translators: %d: number of checks the score is based on. */ __( 'This score is based on %d checks run at the time shown below.', 'garion-projetos-site-health-monitor' ), $total_checks ) ) );
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: points deducted per warning, 2: points deducted per critical problem. */
					__( 'Each warning subtracts %1$d points; each critical problem subtracts %2$d points. Any critical problem caps the score at 49; any warning caps it at 89.', 'garion-projetos-site-health-monitor' ),
					$weight_warning,
					$weight_critical
				)
			)
		);

		if ( $contributors ) {
			echo '<p>' . esc_html__( 'Problems currently reducing the score:', 'garion-projetos-site-health-monitor' ) . '</p><ul class="gpshm-disclosure__list">';
			foreach ( $contributors as $row ) {
				printf( '<li>%1$s %2$s</li>', self::status_badge( $row['status'] ), esc_html( $row['label'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- status_badge() escapes its own parts.
			}
			echo '</ul>';
		} else {
			echo '<p>' . esc_html__( 'No problems are currently reducing the score.', 'garion-projetos-site-health-monitor' ) . '</p>';
		}

		if ( $ignored_count > 0 ) {
			printf( '<p>%s</p>', esc_html( sprintf( /* translators: %d: number of ignored checks excluded from scoring. */ _n( '%d ignored check is excluded from this calculation.', '%d ignored checks are excluded from this calculation.', $ignored_count, 'garion-projetos-site-health-monitor' ), $ignored_count ) ) );
		}

		printf( '<p class="description">%s</p>', esc_html( sprintf( /* translators: %s: date/time the score was calculated. */ __( 'Calculated as of: %s', 'garion-projetos-site-health-monitor' ), $checked_at_label ) ) );

		echo '</div></details>';
	}

	/**
	 * Notice/alert box. Icon always accompanies color so meaning is never color-only.
	 */
	public static function alert( $message, $type = 'info' ) {
		$icons = array(
			'info'    => 'dashicons-info-outline',
			'success' => 'dashicons-yes-alt',
			'warning' => 'dashicons-warning',
			'danger'  => 'dashicons-dismiss',
		);
		$icon  = $icons[ $type ] ?? 'dashicons-info-outline';

		printf(
			'<div class="gpshm-alert gpshm-alert--%1$s">%2$s<div class="gpshm-alert__body">%3$s</div></div>',
			esc_attr( sanitize_html_class( $type ) ),
			self::icon( $icon ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			wp_kses_post( $message )
		);
	}

	/**
	 * Empty-state placeholder shown when a section has no data yet.
	 */
	public static function empty_state( $title, $description = '', $icon = 'dashicons-info-outline', $action_html = '' ) {
		printf(
			'<div class="gpshm-empty-state">%1$s<p class="gpshm-empty-state__title">%2$s</p>%3$s%4$s</div>',
			self::icon( $icon ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- icon() escapes its own parts.
			esc_html( $title ),
			$description ? '<p class="gpshm-empty-state__desc">' . esc_html( $description ) . '</p>' : '',
			$action_html ? '<div class="gpshm-empty-state__action">' . $action_html . '</div>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- action markup is built by trusted callers with their own escaping.
		);
	}

	public static function empty_row( $colspan, $title, $description = '' ) {
		printf(
			'<tr class="gpshm-empty-row"><td colspan="%1$d">%2$s</td></tr>',
			(int) $colspan,
			self::empty_state( $title, $description ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- empty_state() escapes its own parts.
		);
	}

	public static function card_start( $title = '', $subtitle = '', $class = '' ) {
		printf( '<div class="gpshm-card %s">', esc_attr( $class ) );

		if ( $title ) {
			echo '<div class="gpshm-card__header"><h2 class="gpshm-card__title">' . esc_html( $title ) . '</h2>';
			if ( $subtitle ) {
				echo '<p class="gpshm-card__subtitle">' . esc_html( $subtitle ) . '</p>';
			}
			echo '</div>';
		}

		echo '<div class="gpshm-card__body">';
	}

	public static function card_end() {
		echo '</div></div>';
	}

	/**
	 * Small "(?)" tooltip trigger with accessible text, for labels that need extra context.
	 */
	public static function tooltip( $text ) {
		return sprintf(
			'<span class="gpshm-tooltip" tabindex="0" data-gpshm-tooltip="%1$s"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span><span class="screen-reader-text">%1$s</span></span>',
			esc_attr( $text )
		);
	}

	/**
	 * Opens the responsive table shell. $headers is an ordered list of column labels
	 * used both for the visible <thead> and as the data-label attribute the CSS uses
	 * to relabel cells when the table collapses into stacked cards on small screens.
	 */
	public static function table_start( array $headers, $class = '' ) {
		echo '<div class="gpshm-table-wrap"><table class="gpshm-table widefat striped ' . esc_attr( $class ) . '"><thead><tr>';

		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}

		echo '</tr></thead><tbody>';
	}

	public static function table_end() {
		echo '</tbody></table></div>';
	}

	/**
	 * Action buttons shown at the end of an expanded detail panel — actions
	 * are only ever reachable once a row is opened, there is no menu on the
	 * collapsed row. $items is a list of [ label, icon, attrs_html ] —
	 * attrs_html carries data-action/data-* attributes the delegated JS
	 * listener reads.
	 */
	public static function detail_actions( array $items ) {
		if ( ! $items ) {
			return;
		}

		echo '<div class="gpshm-detail__actions">';

		foreach ( $items as $item ) {
			printf(
				'<button type="button" class="button gpshm-detail__action" %1$s>%2$s%3$s</button>',
				$item['attrs'] ?? '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers build attrs with esc_attr().
				isset( $item['icon'] ) ? self::icon( $item['icon'] ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $item['label'] )
			);
		}

		echo '</div>';
	}

	/**
	 * Expandable detail row shown under a check's table row. $row is the live
	 * report row (key/label/value/status/category); $doc is the static
	 * documentation from GP_SHM_Check_Docs; $meta carries ignored/reopened
	 * state text, a short recent-history summary (already-formatted strings)
	 * and the list of action items rendered at the end via detail_actions().
	 */
	public static function detail_row( $colspan, array $row, array $doc, array $meta ) {
		printf( '<tr class="gpshm-detail-row" id="gpshm-detail-%1$s" hidden><td colspan="%2$d">', esc_attr( $row['key'] ), (int) $colspan );
		echo '<div class="gpshm-detail">';

		echo '<dl class="gpshm-detail__grid">';
		self::detail_item( __( 'Current value', 'garion-projetos-site-health-monitor' ), $row['value'] );
		self::detail_item( __( 'Severity', 'garion-projetos-site-health-monitor' ), self::status_badge( $row['status'] ), true );
		self::detail_item( __( 'Category', 'garion-projetos-site-health-monitor' ), $row['category'] );
		self::detail_item( __( 'Technical key', 'garion-projetos-site-health-monitor' ), $row['key'] );
		if ( ! empty( $meta['last_checked'] ) ) {
			self::detail_item( __( 'Last checked', 'garion-projetos-site-health-monitor' ), $meta['last_checked'] );
		}
		echo '</dl>';

		if ( ! empty( $doc['description'] ) ) {
			echo '<p class="gpshm-detail__section"><strong>' . esc_html__( 'What this check means', 'garion-projetos-site-health-monitor' ) . ':</strong> ' . esc_html( $doc['description'] ) . '</p>';
		}
		if ( ! empty( $doc['cause'] ) ) {
			echo '<p class="gpshm-detail__section"><strong>' . esc_html__( 'Likely cause', 'garion-projetos-site-health-monitor' ) . ':</strong> ' . esc_html( $doc['cause'] ) . '</p>';
		}
		if ( ! empty( $doc['recommendation'] ) ) {
			echo '<p class="gpshm-detail__section gpshm-detail__recommendation"><strong>' . esc_html__( 'Recommendation', 'garion-projetos-site-health-monitor' ) . ':</strong> ' . esc_html( $doc['recommendation'] ) . '</p>';
		}
		if ( ! empty( $doc['fix_location'] ) ) {
			echo '<p class="gpshm-detail__section"><strong>' . esc_html__( 'Likely fix location', 'garion-projetos-site-health-monitor' ) . ':</strong> ' . esc_html( $doc['fix_location'] ) . '</p>';
		}
		if ( ! empty( $meta['state_note'] ) ) {
			echo '<p class="gpshm-detail__section gpshm-detail__state">' . esc_html( $meta['state_note'] ) . '</p>';
		}
		if ( ! empty( $meta['recent_history'] ) ) {
			echo '<p class="gpshm-detail__section"><strong>' . esc_html__( 'Recent history', 'garion-projetos-site-health-monitor' ) . ':</strong> ' . esc_html( $meta['recent_history'] ) . '</p>';
		}

		if ( ! empty( $meta['actions'] ) ) {
			self::detail_actions( $meta['actions'] );
		}

		echo '</div></td></tr>';
	}

	private static function detail_item( $label, $value, $raw_html = false ) {
		printf(
			'<div class="gpshm-detail__item"><dt>%1$s</dt><dd>%2$s</dd></div>',
			esc_html( $label ),
			$raw_html ? $value : esc_html( $value ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $raw_html only true for pre-escaped badge markup from this same class.
		);
	}

	/**
	 * Client-side pagination control. Rendering only — admin.js reads
	 * data-gpshm-page on each table row and toggles visibility; this control
	 * just dispatches a custom event the JS listens for.
	 */
	public static function pagination( $per_page ) {
		printf(
			'<div class="gpshm-pagination" data-gpshm-per-page="%1$d"><button type="button" class="button gpshm-pagination__prev">%2$s</button><span class="gpshm-pagination__status" aria-live="polite"></span><button type="button" class="button gpshm-pagination__next">%3$s</button></div>',
			(int) $per_page,
			esc_html__( 'Previous', 'garion-projetos-site-health-monitor' ),
			esc_html__( 'Next', 'garion-projetos-site-health-monitor' )
		);
	}

	/**
	 * A standardized settings field: label, input, unit, help text — replaces
	 * the bare form-table rows from round 1.
	 */
	public static function settings_field( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'          => '',
				'label'       => '',
				'description' => '',
				'unit'        => '',
				'type'        => 'number',
				'name'        => '',
				'value'       => '',
				'min'         => null,
				'max'         => null,
				'options'     => array(),
			)
		);

		echo '<div class="gpshm-field">';
		printf( '<label class="gpshm-field__label" for="%1$s">%2$s</label>', esc_attr( $args['id'] ), esc_html( $args['label'] ) );

		echo '<div class="gpshm-field__control">';
		if ( 'checkbox' === $args['type'] ) {
			printf(
				'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s />',
				esc_attr( $args['id'] ),
				esc_attr( $args['name'] ),
				checked( (bool) $args['value'], true, false )
			);
		} elseif ( 'select' === $args['type'] ) {
			printf( '<select id="%1$s" name="%2$s">', esc_attr( $args['id'] ), esc_attr( $args['name'] ) );
			foreach ( $args['options'] as $option_value => $option_label ) {
				printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option_value ), selected( $args['value'], $option_value, false ), esc_html( $option_label ) );
			}
			echo '</select>';
		} else {
			printf(
				'<input type="number" id="%1$s" name="%2$s" value="%3$s" class="small-text"%4$s%5$s />',
				esc_attr( $args['id'] ),
				esc_attr( $args['name'] ),
				esc_attr( $args['value'] ),
				null !== $args['min'] ? ' min="' . esc_attr( $args['min'] ) . '"' : '',
				null !== $args['max'] ? ' max="' . esc_attr( $args['max'] ) . '"' : ''
			);
			if ( $args['unit'] ) {
				echo ' <span class="gpshm-field__unit">' . esc_html( $args['unit'] ) . '</span>';
			}
		}
		echo '</div>';

		if ( $args['description'] ) {
			echo '<p class="gpshm-field__desc">' . esc_html( $args['description'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * The public monitoring endpoint, promoted from a bare <code> line into
	 * its own component: status, abbreviated URL, copy + real self-test.
	 */
	public static function endpoint_card( $url ) {
		self::card_start( __( 'Monitoring endpoint', 'garion-projetos-site-health-monitor' ) );

		echo '<div class="gpshm-endpoint">';
		echo '<div class="gpshm-endpoint__row">';
		echo self::badge( __( 'Active', 'garion-projetos-site-health-monitor' ), 'success', 'dashicons-yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		printf( '<code class="gpshm-endpoint__url" title="%1$s">%2$s</code>', esc_attr( $url ), esc_html( $url ) );
		echo '</div>';
		echo '<div class="gpshm-endpoint__actions">';
		printf( '<button type="button" class="button gpshm-endpoint-copy" data-gpshm-url="%1$s">%2$s</button>', esc_attr( $url ), esc_html__( 'Copy URL', 'garion-projetos-site-health-monitor' ) );
		printf( '<button type="button" class="button gpshm-endpoint-test" data-gpshm-url="%1$s">%2$s</button>', esc_attr( $url ), esc_html__( 'Test endpoint', 'garion-projetos-site-health-monitor' ) );
		echo '<span class="gpshm-endpoint__result" aria-live="polite"></span>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'This URL is public and unauthenticated by design — it only ever returns an overall ok/warning/critical status, never the detailed report, so it is safe to give to external uptime monitoring services.', 'garion-projetos-site-health-monitor' ) . '</p>';
		echo '</div>';

		self::card_end();
	}
}
