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
	'welcome.php',
	'newsletter.php',
	'members.php',
	'serving-dates.php',
	'calendar-listed-events.php',
	'calendar-pdfs.php',
	'sunday-location.php',
	'board-location.php',
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
	'religious-education.php',
	'documents.php',
	'foil-background.php',
);

foreach ( $emerson_chapel_modules as $emerson_chapel_module ) {
	require_once emerson_chapel_path( 'inc/' . $emerson_chapel_module );
}
