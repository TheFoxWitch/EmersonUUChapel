<?php
/**
 * Plugin Name: Local loopback fix
 * Description: On the local Docker copy only. Lets WordPress call itself (WP-Cron, background jobs, Site Health), which otherwise fails because the site address uses the Mac's port 8080 while Apache inside Docker listens on port 80. Does nothing on the live site.
 */

$emerson_home = wp_parse_url( get_option( 'home' ) );
if ( 'localhost' !== ( $emerson_home['host'] ?? '' ) || 8080 !== (int) ( $emerson_home['port'] ?? 0 ) ) {
	return;
}

add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( false !== $pre || 0 !== strpos( $url, 'http://localhost:8080/' ) ) {
			return $pre;
		}

		// "wordpress" is the web container's name in docker-compose.yml; it works from both containers.
		$args['headers']         = (array) ( $args['headers'] ?? array() );
		$args['headers']['Host'] = 'localhost:8080';
		return wp_remote_request( 'http://wordpress/' . substr( $url, strlen( 'http://localhost:8080/' ) ), $args );
	},
	10,
	3
);
