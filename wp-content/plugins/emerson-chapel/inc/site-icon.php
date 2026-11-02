<?php
/**
 * Emerson tab icon, built from images/EmersonUUChapelLogoOriginal.png
 * (the round green badge). Every advertised URL is a same-origin root file
 * with max-age=0 so browsers cannot flip between a fresh ICO and a year-cached
 * plugin PNG. Safari asks for /apple-touch-icon.png and /favicon.ico at the
 * site root (it does not use <link rel="icon"> the way Chrome does).
 */

declare( strict_types=1 );

function emerson_site_icon_png_path(): string {
	return emerson_chapel_path( 'assets/images/emerson-site-icon.png' );
}

function emerson_favicon_ico_path(): string {
	return emerson_chapel_path( 'assets/images/favicon.ico' );
}

function emerson_favicon_16_path(): string {
	return emerson_chapel_path( 'assets/images/emerson-favicon-16.png' );
}

function emerson_favicon_32_path(): string {
	return emerson_chapel_path( 'assets/images/emerson-favicon-32.png' );
}

function emerson_apple_touch_path(): string {
	return emerson_chapel_path( 'assets/images/apple-touch-icon.png' );
}

function emerson_favicon_version(): string {
	$path = emerson_site_icon_png_path();
	return is_readable( $path ) ? (string) filemtime( $path ) : (string) time();
}

/**
 * Root paths browsers may request. Masters live in the plugin; copies are
 * placed at ABSPATH so Apache can serve them with Emerson cache rules.
 *
 * @return array<string, array{path: string, type: string}>
 */
function emerson_root_icon_map(): array {
	$ico   = emerson_favicon_ico_path();
	$png16 = emerson_favicon_16_path();
	$png32 = emerson_favicon_32_path();
	$png512 = emerson_site_icon_png_path();
	$touch = emerson_apple_touch_path();
	$mask  = emerson_chapel_path( 'assets/images/safari-pinned-tab.svg' );
	$svg   = emerson_chapel_path( 'assets/images/favicon.svg' );

	return array(
		'/favicon.ico'                      => array( 'path' => $ico, 'type' => 'image/x-icon' ),
		'/favicon.svg'                      => array( 'path' => $svg, 'type' => 'image/svg+xml' ),
		'/favicon-16.png'                   => array( 'path' => $png16, 'type' => 'image/png' ),
		'/favicon-32.png'                   => array( 'path' => $png32, 'type' => 'image/png' ),
		'/emerson-site-icon.png'            => array( 'path' => $png512, 'type' => 'image/png' ),
		'/apple-touch-icon.png'             => array( 'path' => $touch, 'type' => 'image/png' ),
		'/apple-touch-icon-precomposed.png' => array( 'path' => $touch, 'type' => 'image/png' ),
		'/apple-touch-icon-180x180.png'     => array( 'path' => $touch, 'type' => 'image/png' ),
		'/safari-pinned-tab.svg'            => array( 'path' => $mask, 'type' => 'image/svg+xml' ),
	);
}

function emerson_copy_root_icons(): void {
	foreach ( emerson_root_icon_map() as $url_path => $icon ) {
		if ( ! is_readable( $icon['path'] ) ) {
			continue;
		}
		$dest = ABSPATH . ltrim( $url_path, '/' );
		if ( ! is_file( $dest ) || md5_file( $icon['path'] ) !== md5_file( $dest ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			@copy( $icon['path'], $dest );
		}
	}
}

function emerson_icon_cache_rules(): void {
	$htaccess = ABSPATH . '.htaccess';
	if ( ! is_writable( $htaccess ) ) {
		return;
	}

	// W3 Total Cache sets image/* under wp-content to one year. Root icon
	// filenames must never get that — they flip the tab icon otherwise.
	$rules = array(
		'<IfModule mod_expires.c>',
		"\t" . '<FilesMatch "^(favicon(-16|-32)?\.(ico|svg|png)|emerson-site-icon\.png|apple-touch-icon.*\.png|safari-pinned-tab\.svg)$">',
		"\t\t" . 'ExpiresActive On',
		"\t\t" . 'ExpiresByType image/x-icon "access plus 0 seconds"',
		"\t\t" . 'ExpiresByType image/vnd.microsoft.icon "access plus 0 seconds"',
		"\t\t" . 'ExpiresByType image/png "access plus 0 seconds"',
		"\t\t" . 'ExpiresByType image/svg+xml "access plus 0 seconds"',
		"\t\t" . 'ExpiresDefault "access plus 0 seconds"',
		"\t" . '</FilesMatch>',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		"\t" . '<FilesMatch "^(favicon(-16|-32)?\.(ico|svg|png)|emerson-site-icon\.png|apple-touch-icon.*\.png|safari-pinned-tab\.svg)$">',
		"\t\t" . 'Header unset Expires',
		"\t\t" . 'Header always set Cache-Control "public, max-age=0, must-revalidate"',
		"\t" . '</FilesMatch>',
		'</IfModule>',
	);

	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	insert_with_markers( $htaccess, 'Emerson icons', $rules );
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

/**
 * Keep Appearance → Site Icon in sync with the plugin badge (attachment 999 locally).
 */
function emerson_sync_wp_site_icon_file(): void {
	$sid = (int) get_option( 'site_icon', 0 );
	if ( $sid <= 0 || ! is_readable( emerson_site_icon_png_path() ) ) {
		return;
	}
	$file = get_attached_file( $sid );
	if ( ! is_string( $file ) || $file === '' ) {
		return;
	}
	$src = emerson_site_icon_png_path();
	if ( is_file( $file ) && md5_file( $src ) === md5_file( $file ) ) {
		return;
	}
	$dir = dirname( $file );
	if ( ! is_dir( $dir ) ) {
		return;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
	if ( ! @copy( $src, $file ) ) {
		return;
	}
	if ( function_exists( 'wp_generate_attachment_metadata' ) && function_exists( 'wp_update_attachment_metadata' ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $sid, $file );
		if ( is_array( $meta ) ) {
			wp_update_attachment_metadata( $sid, $meta );
		}
	}
}

add_action(
	'init',
	static function (): void {
		remove_action( 'wp_head', 'wp_site_icon', 99 );
		remove_action( 'admin_head', 'wp_site_icon' );
		remove_action( 'do_faviconico', 'do_favicon' );
		emerson_copy_root_icons();
		emerson_icon_cache_rules();
		emerson_sync_wp_site_icon_file();
		emerson_serve_root_icon();
	},
	0
);

add_action( 'do_faviconico', 'emerson_serve_root_icon', 0 );

add_filter(
	'get_site_icon_url',
	static function ( $url, $size ) {
		$ver = emerson_favicon_version();
		if ( (int) $size <= 32 ) {
			return add_query_arg( 'v', $ver, home_url( '/favicon-32.png' ) );
		}
		if ( (int) $size <= 180 ) {
			return add_query_arg( 'v', $ver, home_url( '/apple-touch-icon.png' ) );
		}
		return add_query_arg( 'v', $ver, home_url( '/emerson-site-icon.png' ) );
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
	$png16 = add_query_arg( 'v', $ver, home_url( '/favicon-16.png' ) );
	$png32 = add_query_arg( 'v', $ver, home_url( '/favicon-32.png' ) );
	$png512 = add_query_arg( 'v', $ver, home_url( '/emerson-site-icon.png' ) );
	$touch = add_query_arg( 'v', $ver, home_url( '/apple-touch-icon.png' ) );
	$mask  = add_query_arg( 'v', $ver, home_url( '/safari-pinned-tab.svg' ) );

	// Only root URLs (max-age=0). Do not point at wp-content plugin PNGs —
	// W3 Total Cache gives those a one-year cache and the tab flips back.
	printf( '<link rel="icon" href="%s" sizes="any" />' . "\n", esc_url( $ico ) );
	printf( '<link rel="icon" href="%s" type="image/svg+xml" />' . "\n", esc_url( $svg ) );
	printf( '<link rel="icon" type="image/png" sizes="16x16" href="%s" />' . "\n", esc_url( $png16 ) );
	printf( '<link rel="icon" type="image/png" sizes="32x32" href="%s" />' . "\n", esc_url( $png32 ) );
	printf( '<link rel="icon" type="image/png" sizes="512x512" href="%s" />' . "\n", esc_url( $png512 ) );
	printf( '<link rel="apple-touch-icon" href="%s" />' . "\n", esc_url( $touch ) );
	printf( '<link rel="mask-icon" href="%s" color="#1B7A4B" />' . "\n", esc_url( $mask ) );
}
