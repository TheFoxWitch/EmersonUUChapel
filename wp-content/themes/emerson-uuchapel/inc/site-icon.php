<?php
/**
 * Emerson tab icon. Safari asks for /apple-touch-icon.png and /favicon.ico at
 * the site root (it does not use <link rel="icon"> the way Chrome does).
 * Those paths used to 404 as WordPress HTML, so Safari kept the blue “W”.
 */

declare( strict_types=1 );

function emerson_site_icon_png_path(): string {
	return get_stylesheet_directory() . '/assets/images/emerson-site-icon.png';
}

function emerson_favicon_ico_path(): string {
	return get_stylesheet_directory() . '/assets/images/favicon.ico';
}

function emerson_favicon_32_path(): string {
	return get_stylesheet_directory() . '/assets/images/emerson-favicon-32.png';
}

function emerson_apple_touch_path(): string {
	return get_stylesheet_directory() . '/assets/images/apple-touch-icon.png';
}

function emerson_favicon_version(): string {
	$path = emerson_apple_touch_path();
	return is_readable( $path ) ? (string) filemtime( $path ) : (string) time();
}

/**
 * @return array<string, array{path: string, type: string}>
 */
function emerson_root_icon_map(): array {
	$ico = emerson_favicon_ico_path();
	$png = emerson_apple_touch_path();

	$mask = get_stylesheet_directory() . '/assets/images/safari-pinned-tab.svg';
	$svg  = get_stylesheet_directory() . '/assets/images/favicon.svg';

	return array(
		'/favicon.ico'                      => array( 'path' => $ico, 'type' => 'image/x-icon' ),
		'/favicon.svg'                      => array( 'path' => $svg, 'type' => 'image/svg+xml' ),
		'/apple-touch-icon.png'             => array( 'path' => $png, 'type' => 'image/png' ),
		'/apple-touch-icon-precomposed.png' => array( 'path' => $png, 'type' => 'image/png' ),
		'/apple-touch-icon-180x180.png'     => array( 'path' => $png, 'type' => 'image/png' ),
		'/safari-pinned-tab.svg'            => array( 'path' => $mask, 'type' => 'image/svg+xml' ),
	);
}

function emerson_copy_root_icons(): void {
	foreach ( emerson_root_icon_map() as $url_path => $icon ) {
		if ( ! is_readable( $icon['path'] ) ) {
			continue;
		}
		$dest = ABSPATH . ltrim( $url_path, '/' );
		if ( ! is_readable( $dest ) || md5_file( $icon['path'] ) !== md5_file( $dest ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			@copy( $icon['path'], $dest );
		}
	}
}

function emerson_serve_root_icon(): void {
	$path = (string) parse_url( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
	$path = untrailingslashit( $path );
	$map  = emerson_root_icon_map();

	if ( ! isset( $map[ $path ] ) || ! is_readable( $map[ $path ]['path'] ) ) {
		return;
	}

	$file = $map[ $path ]['path'];
	header( 'Content-Type: ' . $map[ $path ]['type'] );
	header( 'Content-Length: ' . (string) filesize( $file ) );
	header( 'Cache-Control: public, max-age=0, must-revalidate' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	readfile( $file );
	exit;
}

add_action(
	'init',
	static function (): void {
		remove_action( 'wp_head', 'wp_site_icon', 99 );
		remove_action( 'admin_head', 'wp_site_icon' );
		remove_action( 'do_faviconico', 'do_favicon' );
		emerson_copy_root_icons();
		emerson_serve_root_icon();
	},
	0
);

add_action( 'do_faviconico', 'emerson_serve_root_icon', 0 );

add_filter(
	'get_site_icon_url',
	static function ( $url, $size ) {
		$ver = emerson_favicon_version();
		if ( (int) $size <= 32 && is_readable( emerson_favicon_32_path() ) ) {
			return add_query_arg( 'v', $ver, get_theme_file_uri( 'assets/images/emerson-favicon-32.png' ) );
		}
		if ( is_readable( emerson_site_icon_png_path() ) ) {
			return add_query_arg( 'v', $ver, get_theme_file_uri( 'assets/images/emerson-site-icon.png' ) );
		}
		return $url;
	},
	10,
	2
);

add_action( 'wp_head', 'emerson_site_icon_head_links', 1 );
add_action( 'admin_head', 'emerson_site_icon_head_links', 1 );

function emerson_site_icon_head_links(): void {
	$ver   = emerson_favicon_version();
	$ico   = add_query_arg( 'v', $ver, home_url( '/favicon.ico' ) );
	$svg   = add_query_arg( 'v', $ver, home_url( '/favicon.svg' ) );
	$png16 = add_query_arg( 'v', $ver, get_theme_file_uri( 'assets/images/emerson-favicon-16.png' ) );
	$png32 = add_query_arg( 'v', $ver, get_theme_file_uri( 'assets/images/emerson-favicon-32.png' ) );
	$touch = add_query_arg( 'v', $ver, home_url( '/apple-touch-icon.png' ) );
	$mask  = add_query_arg( 'v', $ver, home_url( '/safari-pinned-tab.svg' ) );

	// Regular tabs: ICO + SVG (Safari 26+) + 16/32 PNG. Pinned tabs: mask-icon.
	// Apple requires mask-icon viewBox="0 0 16 16" and a single layer.
	// Previous circular set is in assets/images/circle-logo/.
	printf( '<link rel="icon" href="%s" sizes="any" />' . "\n", esc_url( $ico ) );
	printf( '<link rel="icon" href="%s" type="image/svg+xml" />' . "\n", esc_url( $svg ) );
	printf( '<link rel="icon" type="image/png" sizes="16x16" href="%s" />' . "\n", esc_url( $png16 ) );
	printf( '<link rel="icon" type="image/png" sizes="32x32" href="%s" />' . "\n", esc_url( $png32 ) );
	printf( '<link rel="apple-touch-icon" href="%s" />' . "\n", esc_url( $touch ) );
	printf( '<link rel="mask-icon" href="%s" color="#1B7A4B" />' . "\n", esc_url( $mask ) );
}
