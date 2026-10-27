<?php
/**
 * Plugin Name: Emerson LAN preview
 * Description: On the local Docker copy only. Lets a phone on the same Wi‑Fi open the site at http://YOUR-MAC-IP:8080 instead of localhost (which is the phone itself). Rewrites localhost links in the HTML for that request. Does nothing on the live site, and does nothing when you are already on localhost:8080.
 */

declare( strict_types=1 );

/**
 * Request host with non-default port restored if Apache omitted it from HTTP_HOST.
 */
function emerson_lan_preview_request_host(): string {
	if ( empty( $_SERVER['HTTP_HOST'] ) || ! is_string( $_SERVER['HTTP_HOST'] ) ) {
		return '';
	}
	$host = strtolower( (string) $_SERVER['HTTP_HOST'] );
	if ( str_contains( $host, ':' ) ) {
		return $host;
	}
	$port = isset( $_SERVER['SERVER_PORT'] ) ? (int) $_SERVER['SERVER_PORT'] : 0;
	if ( $port > 0 && $port !== 80 && $port !== 443 ) {
		return $host . ':' . $port;
	}
	return $host;
}

/**
 * True when the host is a private LAN IPv4 (with optional port). Not localhost.
 */
function emerson_lan_preview_is_lan_host( string $host ): bool {
	$ip = $host;
	if ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $host, $m ) ) {
		$ip = $m[1];
	}
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) === false ) {
		return false;
	}
	return (bool) preg_match(
		'/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/',
		$ip
	);
}

/**
 * LAN origin for this request, or empty when we should leave URLs alone.
 */
function emerson_lan_preview_origin(): string {
	$host = emerson_lan_preview_request_host();
	if ( $host === '' || ! emerson_lan_preview_is_lan_host( $host ) ) {
		return '';
	}
	$db_host = (string) ( wp_parse_url( (string) get_option( 'home', '' ), PHP_URL_HOST ) ?? '' );
	if ( ! in_array( $db_host, array( 'localhost', '127.0.0.1' ), true ) ) {
		// Live / staging site — do not rewrite.
		return '';
	}
	$https = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' )
		|| ( isset( $_SERVER['SERVER_PORT'] ) && (string) $_SERVER['SERVER_PORT'] === '443' );
	return ( $https ? 'https://' : 'http://' ) . $host;
}

$emerson_lan_origin = emerson_lan_preview_origin();
if ( $emerson_lan_origin === '' ) {
	return;
}

add_filter(
	'option_home',
	static function () use ( $emerson_lan_origin ) {
		return $emerson_lan_origin;
	}
);
add_filter(
	'option_siteurl',
	static function () use ( $emerson_lan_origin ) {
		return $emerson_lan_origin;
	}
);

/**
 * Post content still says http://localhost:8080 — swap for this LAN request only.
 */
add_action(
	'template_redirect',
	static function () use ( $emerson_lan_origin ): void {
		$lan_host = (string) ( wp_parse_url( $emerson_lan_origin, PHP_URL_HOST ) ?? '' );
		$lan_port = wp_parse_url( $emerson_lan_origin, PHP_URL_PORT );
		$lan_hostport = $lan_host;
		if ( ! empty( $lan_port ) ) {
			$lan_hostport .= ':' . $lan_port;
		}
		ob_start(
			static function ( string $html ) use ( $emerson_lan_origin, $lan_hostport ): string {
				return str_replace(
					array(
						'http://localhost:8080',
						'https://localhost:8080',
						'//localhost:8080',
					),
					array(
						$emerson_lan_origin,
						$emerson_lan_origin,
						'//' . $lan_hostport,
					),
					$html
				);
			}
		);
	},
	0
);
