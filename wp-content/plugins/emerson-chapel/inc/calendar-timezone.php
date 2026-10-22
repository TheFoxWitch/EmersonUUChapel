<?php
/**
 * Church Admin calendar “today” uses the site timezone (America/Chicago).
 *
 * Stock Church Admin JS builds the first month load with Date.toJSON(), which is UTC and
 * jumps a day after evening Central. PHP may also use date() (UTC in Docker) for the Today
 * button. Keep the vendor plugin untouched so updates do not wipe this; Emerson corrects it.
 */

declare( strict_types=1 );

/**
 * Y-m-d in the WordPress timezone (Settings → General).
 */
function emerson_calendar_site_today(): string {
	return current_time( 'Y-m-d' );
}

/**
 * IANA timezone string for JS (falls back to America/Chicago for this chapel).
 */
function emerson_calendar_site_timezone(): string {
	$tz = function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : '';
	if ( $tz === '' || str_contains( $tz, ':' ) ) {
		return 'America/Chicago';
	}
	return $tz;
}

/**
 * Init AJAX sends cat_id / fac_ids; prev/next do not. Force site today on that first load.
 */
function emerson_calendar_force_site_today_on_init(): void {
	$method = isset( $_REQUEST['method'] ) ? sanitize_key( wp_unslash( $_REQUEST['method'] ) ) : '';
	if ( 'calendar-render' !== $method ) {
		return;
	}
	if ( ! array_key_exists( 'cat_id', $_REQUEST ) && ! array_key_exists( 'fac_ids', $_REQUEST ) ) {
		return;
	}
	$today               = emerson_calendar_site_today();
	$_REQUEST['date']    = $today;
	$_POST['date']       = $today;
	$_GET['date']        = $today;
}

add_action( 'wp_ajax_church_admin', 'emerson_calendar_force_site_today_on_init', 0 );
add_action( 'wp_ajax_nopriv_church_admin', 'emerson_calendar_force_site_today_on_init', 0 );

/**
 * Ensure the Today button’s data-date matches the site calendar day.
 *
 * @param string              $output Shortcode HTML.
 * @param string              $tag    Shortcode name.
 * @param array<string,mixed> $attr   Shortcode attributes.
 */
function emerson_calendar_fix_today_button( $output, string $tag, $attr ) {
	if ( 'church_admin' !== $tag || ! is_string( $output ) || ! str_contains( $output, 'ca-today-button' ) ) {
		return $output;
	}
	$today = emerson_calendar_site_today();
	$fixed = preg_replace(
		'/(<div class="ca-today-button">\s*<button\b[^>]*\bdata-date=")[^"]*/',
		'${1}' . esc_attr( $today ),
		$output,
		1
	);
	return is_string( $fixed ) ? $fixed : $output;
}

add_filter( 'do_shortcode_tag', 'emerson_calendar_fix_today_button', 20, 3 );

/**
 * Put ca-today on the site’s calendar day in month JSON (vendor may still use UTC date()).
 *
 * @param array<string,mixed> $data Month render payload.
 * @return array<string,mixed>
 */
function emerson_calendar_fix_today_markers( array $data ): array {
	if ( empty( $data['dates'] ) || ! is_array( $data['dates'] ) ) {
		return $data;
	}
	$today = emerson_calendar_site_today();
	$part  = isset( $data['partDate'] ) ? (string) $data['partDate'] : '';
	$nums  = isset( $data['dateNumber'] ) && is_array( $data['dateNumber'] ) ? $data['dateNumber'] : array();

	foreach ( $data['dates'] as $i => $html ) {
		if ( ! is_string( $html ) || $html === '' ) {
			continue;
		}
		$html = preg_replace( '/<span class="ca-today">(\d+)<\/span>/', '$1', $html ) ?? $html;
		$num  = isset( $nums[ $i ] ) ? (string) $nums[ $i ] : '';
		if ( $part !== '' && $num !== '' && ( $part . $num ) === $today ) {
			$html = preg_replace( '/^(\d+)/', '<span class="ca-today">$1</span>', $html, 1 ) ?? $html;
		}
		$data['dates'][ $i ] = $html;
	}
	return $data;
}

add_action( 'wp_ajax_church_admin', 'emerson_calendar_buffer_today_markers', 1 );
add_action( 'wp_ajax_nopriv_church_admin', 'emerson_calendar_buffer_today_markers', 1 );

function emerson_calendar_buffer_today_markers(): void {
	$method = isset( $_REQUEST['method'] ) ? sanitize_key( wp_unslash( $_REQUEST['method'] ) ) : '';
	if ( 'calendar-render' !== $method ) {
		return;
	}
	ob_start(
		static function ( string $json ): string {
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				return $json;
			}
			return wp_json_encode( emerson_calendar_fix_today_markers( $data ) ) ?: $json;
		}
	);
}

/**
 * Shim before Church Admin’s calendar script: init load uses site today, not UTC toJSON().
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$path = emerson_chapel_path( 'assets/js/calendar-timezone.js' );
		if ( ! is_readable( $path ) ) {
			return;
		}
		wp_enqueue_script(
			'emerson-chapel-calendar-timezone',
			emerson_chapel_url( 'assets/js/calendar-timezone.js' ),
			array( 'jquery' ),
			(string) filemtime( $path ),
			true
		);
		wp_localize_script(
			'emerson-chapel-calendar-timezone',
			'emersonCalendarTz',
			array(
				'today'    => emerson_calendar_site_today(),
				'timezone' => emerson_calendar_site_timezone(),
			)
		);
		// Ensure our shim is listed before Church Admin’s calendar when both print.
		$wp_scripts = wp_scripts();
		if ( isset( $wp_scripts->registered['church-admin-calendar'] ) ) {
			$deps   = $wp_scripts->registered['church-admin-calendar']->deps;
			$deps[] = 'emerson-chapel-calendar-timezone';
			$wp_scripts->registered['church-admin-calendar']->deps = array_values( array_unique( $deps ) );
		}
	},
	20
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! wp_script_is( 'emerson-chapel', 'enqueued' ) && ! wp_script_is( 'emerson-chapel', 'registered' ) ) {
			return;
		}
		wp_localize_script(
			'emerson-chapel',
			'emersonChapel',
			array(
				'timezone' => emerson_calendar_site_timezone(),
				'today'    => emerson_calendar_site_today(),
			)
		);
	},
	30
);
