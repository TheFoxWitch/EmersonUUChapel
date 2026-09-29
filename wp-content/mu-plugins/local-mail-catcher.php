<?php
/**
 * Plugin Name: Local mail catcher
 * Description: On the local Docker copy only. By default stops all outgoing email and saves each message to wp-content/local-mail/. If the option emerson_local_mail_redirect_to holds an address, email is sent for real but only to that address. Does nothing on the live site.
 */

$emerson_host = wp_parse_url( get_option( 'home' ), PHP_URL_HOST );
if ( ! in_array( $emerson_host, array( 'localhost', '127.0.0.1' ), true ) ) {
	return;
}

function emerson_local_mail_save( $atts, $note = '' ) {
	$dir = WP_CONTENT_DIR . '/local-mail';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	$to      = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : $atts['to'];
	$headers = is_array( $atts['headers'] ) ? implode( "\n", $atts['headers'] ) : (string) $atts['headers'];
	$body    = $note . "To: {$to}\nSubject: {$atts['subject']}\n{$headers}\n\n{$atts['message']}\n";

	$name = gmdate( 'Y-m-d_His' ) . '_' . sanitize_file_name( substr( $atts['subject'], 0, 60 ) ) . '.txt';
	file_put_contents( "{$dir}/{$name}", $body );
}

$emerson_redirect_to = get_option( 'emerson_local_mail_redirect_to' );

if ( ! is_email( $emerson_redirect_to ) ) {
	add_filter(
		'pre_wp_mail',
		function ( $return, $atts ) {
			emerson_local_mail_save( $atts );
			return true;
		},
		1,
		2
	);
	return;
}

add_filter(
	'wp_mail',
	function ( $atts ) use ( $emerson_redirect_to ) {
		$original = is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : (string) $atts['to'];

		$headers = is_array( $atts['headers'] ) ? $atts['headers'] : preg_split( "/\r\n|\n/", (string) $atts['headers'] );
		$headers = array_values(
			array_filter(
				$headers,
				function ( $line ) {
					return '' !== trim( $line ) && ! preg_match( '/^\s*b?cc\s*:/i', $line );
				}
			)
		);

		$atts['to']      = $emerson_redirect_to;
		$atts['headers'] = $headers;
		$atts['subject'] = '[LOCAL TEST → ' . $original . '] ' . $atts['subject'];

		emerson_local_mail_save( $atts, "Sent for real, redirected from: {$original}\n" );

		return $atts;
	},
	PHP_INT_MAX
);

// Catches recipients a plugin might add directly to PHPMailer after the wp_mail filter.
add_action(
	'phpmailer_init',
	function ( $phpmailer ) use ( $emerson_redirect_to ) {
		$phpmailer->clearAllRecipients();
		$phpmailer->addAddress( $emerson_redirect_to );
	},
	PHP_INT_MAX
);
