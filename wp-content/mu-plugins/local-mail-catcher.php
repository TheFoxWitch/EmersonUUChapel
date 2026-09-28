<?php
/**
 * Plugin Name: Local mail catcher
 * Description: On the local Docker copy only, stops all outgoing email and saves each message to wp-content/local-mail/ instead. Does nothing on the live site.
 */

$emerson_host = wp_parse_url( get_option( 'home' ), PHP_URL_HOST );
if ( ! in_array( $emerson_host, array( 'localhost', '127.0.0.1' ), true ) ) {
	return;
}

add_filter(
	'pre_wp_mail',
	function ( $return, $atts ) {
		$dir = WP_CONTENT_DIR . '/local-mail';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$to      = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : $atts['to'];
		$headers = is_array( $atts['headers'] ) ? implode( "\n", $atts['headers'] ) : (string) $atts['headers'];
		$body    = "To: {$to}\nSubject: {$atts['subject']}\n{$headers}\n\n{$atts['message']}\n";

		$name = gmdate( 'Y-m-d_His' ) . '_' . sanitize_file_name( substr( $atts['subject'], 0, 60 ) ) . '.txt';
		file_put_contents( "{$dir}/{$name}", $body );

		return true;
	},
	1,
	2
);
