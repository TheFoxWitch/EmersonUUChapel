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

/**
 * Swap localhost origins for this request’s LAN origin.
 */
function emerson_lan_preview_rewrite_url( string $url, string $origin ): string {
	if ( $url === '' || $origin === '' ) {
		return $url;
	}
	$host = (string) ( wp_parse_url( $origin, PHP_URL_HOST ) ?? '' );
	$port = wp_parse_url( $origin, PHP_URL_PORT );
	$hostport = $host;
	if ( ! empty( $port ) ) {
		$hostport .= ':' . $port;
	}
	return str_replace(
		array(
			'http://localhost:8080',
			'https://localhost:8080',
			'//localhost:8080',
		),
		array(
			$origin,
			$origin,
			'//' . $hostport,
		),
		$url
	);
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

/*
 * WP_CONTENT_URL is defined from siteurl *before* mu-plugins load, so it stays
 * http://localhost:8080/wp-content even after option_siteurl is filtered. Upload
 * and attachment URLs (directory avatars) then 404 on a phone. Rewrite them.
 */
add_filter(
	'upload_dir',
	static function ( array $dirs ) use ( $emerson_lan_origin ): array {
		foreach ( array( 'url', 'baseurl' ) as $key ) {
			if ( ! empty( $dirs[ $key ] ) && is_string( $dirs[ $key ] ) ) {
				$dirs[ $key ] = emerson_lan_preview_rewrite_url( $dirs[ $key ], $emerson_lan_origin );
			}
		}
		return $dirs;
	}
);

add_filter(
	'wp_get_attachment_url',
	static function ( $url ) use ( $emerson_lan_origin ) {
		return is_string( $url ) ? emerson_lan_preview_rewrite_url( $url, $emerson_lan_origin ) : $url;
	}
);

add_filter(
	'wp_get_attachment_image_src',
	static function ( $image ) use ( $emerson_lan_origin ) {
		if ( is_array( $image ) && ! empty( $image[0] ) && is_string( $image[0] ) ) {
			$image[0] = emerson_lan_preview_rewrite_url( $image[0], $emerson_lan_origin );
		}
		return $image;
	}
);

add_filter(
	'wp_calculate_image_srcset',
	static function ( $sources ) use ( $emerson_lan_origin ) {
		if ( ! is_array( $sources ) ) {
			return $sources;
		}
		foreach ( $sources as $width => $source ) {
			if ( ! empty( $source['url'] ) && is_string( $source['url'] ) ) {
				$sources[ $width ]['url'] = emerson_lan_preview_rewrite_url( $source['url'], $emerson_lan_origin );
			}
		}
		return $sources;
	}
);

add_filter(
	'content_url',
	static function ( $url ) use ( $emerson_lan_origin ) {
		return is_string( $url ) ? emerson_lan_preview_rewrite_url( $url, $emerson_lan_origin ) : $url;
	}
);

/**
 * Rewrite localhost in full HTML (front pages) and in admin-ajax HTML (directory cards).
 *
 * @param string $html Document or fragment.
 */
$emerson_lan_rewrite_html = static function ( string $html ) use ( $emerson_lan_origin ): string {
	$lan_host = (string) ( wp_parse_url( $emerson_lan_origin, PHP_URL_HOST ) ?? '' );
	$lan_port = wp_parse_url( $emerson_lan_origin, PHP_URL_PORT );
	$lan_hostport = $lan_host;
	if ( ! empty( $lan_port ) ) {
		$lan_hostport .= ':' . $lan_port;
	}
	return str_replace(
		array(
			'http://localhost:8080',
			'https://localhost:8080',
			'//localhost:8080',
			"href='//localhost'",
			'href="//localhost"',
		),
		array(
			$emerson_lan_origin,
			$emerson_lan_origin,
			'//' . $lan_hostport,
			"href='//" . $lan_hostport . "'",
			'href="//' . $lan_hostport . '"',
		),
		$html
	);
};

add_action(
	'template_redirect',
	static function () use ( $emerson_lan_rewrite_html ): void {
		ob_start( $emerson_lan_rewrite_html );
	},
	0
);

add_action(
	'admin_init',
	static function () use ( $emerson_lan_rewrite_html ): void {
		if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
			return;
		}
		ob_start( $emerson_lan_rewrite_html );
	},
	0
);
