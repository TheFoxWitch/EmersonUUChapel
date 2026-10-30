<?php
/**
 * Plugin Name: Emerson Chapel
 * Description: Chapel features that should survive a theme switch: Sunday Location, Documents, Modals, members tools, newsletter, foil background, and related CSS/JS.
 * Version: 1.0.0
 * Author: Emerson UU Chapel
 * Text Domain: emerson-chapel
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EMERSON_CHAPEL_FILE', __FILE__ );
define( 'EMERSON_CHAPEL_DIR', plugin_dir_path( __FILE__ ) );
define( 'EMERSON_CHAPEL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path inside this plugin.
 */
function emerson_chapel_path( string $relative = '' ): string {
	$relative = ltrim( $relative, '/' );
	return $relative === '' ? EMERSON_CHAPEL_DIR : EMERSON_CHAPEL_DIR . $relative;
}

/**
 * Public URL inside this plugin.
 */
function emerson_chapel_url( string $relative = '' ): string {
	$relative = ltrim( $relative, '/' );
	return $relative === '' ? EMERSON_CHAPEL_URL : EMERSON_CHAPEL_URL . $relative;
}

/**
 * Shared circular dismiss control for dialogs (survey, pledge, custom Modals, PayPal).
 * SVG X is geometrically centred — the × glyph sits optically low in most fonts.
 */
function emerson_modal_close_button( string $class ): string {
	$class = sanitize_html_class( $class );
	return '<button type="button" class="' . esc_attr( $class ) . '" aria-label="Close">'
		. '<svg class="emerson-modal-close__icon" viewBox="0 0 12 12" width="14" height="14" aria-hidden="true" focusable="false">'
		/* Two bars through the viewBox centre — fill, not stroke, so edges stay even. */
		. '<g fill="currentColor" transform="translate(6 6) rotate(45)">'
		. '<rect x="-5" y="-0.85" width="10" height="1.7" rx="0.85"/>'
		. '<rect x="-0.85" y="-5" width="1.7" height="10" rx="0.85"/>'
		. '</g>'
		. '</svg>'
		. '</button>';
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$css_path = emerson_chapel_path( 'assets/css/custom.css' );
		$js_path  = emerson_chapel_path( 'assets/js/custom.js' );

		wp_enqueue_style(
			'emerson-chapel',
			emerson_chapel_url( 'assets/css/custom.css' ),
			array(),
			(string) filemtime( $css_path )
		);

		wp_enqueue_script(
			'emerson-chapel',
			emerson_chapel_url( 'assets/js/custom.js' ),
			array(),
			(string) filemtime( $js_path ),
			true
		);

		$newsletter_js = emerson_chapel_path( 'assets/js/newsletter.js' );
		wp_enqueue_script(
			'emerson-chapel-newsletter',
			emerson_chapel_url( 'assets/js/newsletter.js' ),
			array(),
			(string) filemtime( $newsletter_js ),
			true
		);

		$cookies_js = emerson_chapel_path( 'assets/js/cookies.js' );
		wp_enqueue_script(
			'emerson-chapel-cookies',
			emerson_chapel_url( 'assets/js/cookies.js' ),
			array(),
			(string) filemtime( $cookies_js ),
			true
		);
	}
);

$emerson_chapel_modules = array(
	'preload.php',
	'welcome.php',
	'newsletter.php',
	'members.php',
	'serving-dates.php',
	'calendar-listed-events.php',
	'calendar-timezone.php',
	'calendar-pdfs.php',
	'sunday-location.php',
	'board-location.php',
	'event-places.php',
	'sunday-schedule-sheet.php',
	'service-schedule.php',
	'site-icon.php',
	'navigation.php',
	'private-files.php',
	'cookies.php',
	'recommitment.php',
	'modals.php',
	'office-mail.php',
	'home-gallery.php',
	'latest-sermon.php',
	'youtube-sermons.php',
	'religious-education.php',
	'documents.php',
	'foil-background.php',
);

foreach ( $emerson_chapel_modules as $emerson_chapel_module ) {
	require_once emerson_chapel_path( 'inc/' . $emerson_chapel_module );
}
