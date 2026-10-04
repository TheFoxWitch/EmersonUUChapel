<?php
/**
 * Emerson UU Chapel child theme — enqueue editable assets from this folder.
 */

declare( strict_types=1 );

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$css_path = get_stylesheet_directory() . '/assets/css/custom.css';
		$js_path  = get_stylesheet_directory() . '/assets/js/custom.js';

		wp_enqueue_style(
			'emerson-uuchapel-custom',
			get_stylesheet_directory_uri() . '/assets/css/custom.css',
			array(),
			(string) filemtime( $css_path )
		);

		wp_enqueue_script(
			'emerson-uuchapel-custom',
			get_stylesheet_directory_uri() . '/assets/js/custom.js',
			array(),
			(string) filemtime( $js_path ),
			true
		);

		$newsletter_js = get_stylesheet_directory() . '/assets/js/newsletter.js';
		wp_enqueue_script(
			'emerson-uuchapel-newsletter',
			get_stylesheet_directory_uri() . '/assets/js/newsletter.js',
			array(),
			(string) filemtime( $newsletter_js ),
			true
		);
	}
);

require_once get_stylesheet_directory() . '/inc/newsletter.php';
require_once get_stylesheet_directory() . '/inc/members.php';
require_once get_stylesheet_directory() . '/inc/serving-dates.php';
require_once get_stylesheet_directory() . '/inc/calendar-pdfs.php';
require_once get_stylesheet_directory() . '/inc/service-schedule.php';
require_once get_stylesheet_directory() . '/inc/navigation.php';
