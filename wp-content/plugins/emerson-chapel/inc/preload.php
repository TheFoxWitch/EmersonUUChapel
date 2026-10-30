<?php
/**
 * Early hints for late-discovered assets (images, etc.).
 *
 * Do not preload chapel / Blossom CSS here: those already load as normal
 * <link rel="stylesheet"> tags from wp_enqueue_style. A matching preload still
 * makes Chromium warn that the preload was unused.
 *
 * Add entries to emerson_preload_manifest() — each item needs href + as.
 * Optional: pages ('all', 'home', page id, or list of ids), type (preload|preconnect), crossorigin.
 */

declare( strict_types=1 );

/**
 * @return list<array{href:string,as?:string,type?:string,pages?:string|int|list<int|string>,crossorigin?:bool}>
 */
function emerson_preload_manifest(): array {
	return array();
}

/**
 * WordPress preloads @wordpress/interactivity for the navigation block, but the
 * nav view module often runs late (footer + fetchpriority=low), so Chromium
 * warns the modulepreload was unused. Enqueue the library itself so it prints
 * as a real module script instead of a head-only preload.
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( is_admin() || ! function_exists( 'wp_enqueue_script_module' ) ) {
			return;
		}
		wp_enqueue_script_module( '@wordpress/interactivity' );
	},
	20
);

/**
 * @param string|int|list<int|string>|null $pages
 */
function emerson_preload_pages_match( $pages ): bool {
	if ( $pages === null || $pages === 'all' || $pages === '' ) {
		return true;
	}
	if ( is_array( $pages ) ) {
		foreach ( $pages as $page ) {
			if ( emerson_preload_pages_match( $page ) ) {
				return true;
			}
		}
		return false;
	}
	if ( $pages === 'home' || $pages === 'front' ) {
		return is_front_page();
	}
	if ( is_numeric( $pages ) ) {
		return is_page( (int) $pages );
	}
	return is_page( (string) $pages );
}

/** PayPal’s hosted Donate button GIF (the slow CDN asset). */
const EMERSON_PAYPAL_DONATE_GIF_REMOTE = 'https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif';

/**
 * Local URL for the Donate button artwork, or empty if the file is missing.
 */
function emerson_paypal_donate_button_local_url(): string {
	$path = emerson_chapel_path( 'assets/images/btn_donateCC_LG.gif' );
	if ( ! is_readable( $path ) ) {
		return '';
	}
	return emerson_chapel_url( 'assets/images/btn_donateCC_LG.gif' )
		. '?v=' . rawurlencode( (string) filemtime( $path ) );
}

/**
 * True when the main query post still references the PayPal Donate GIF
 * (remote CDN or our local copy) — any page, not just Giving.
 */
function emerson_content_has_paypal_donate_gif(): bool {
	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	$content = (string) $post->post_content;
	return str_contains( $content, 'paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif' )
		|| str_contains( $content, 'assets/images/btn_donateCC_LG.gif' );
}

/**
 * Serve the Donate button artwork from this site; the form still posts to PayPal.
 * Rewrites every matching src in the content (moved button or extra copies).
 */
function emerson_paypal_donate_button_src( string $content ): string {
	$local = emerson_paypal_donate_button_local_url();
	if ( $local === '' || ! str_contains( $content, 'btn_donateCC_LG.gif' ) ) {
		return $content;
	}
	return str_replace( EMERSON_PAYPAL_DONATE_GIF_REMOTE, $local, $content );
}
add_filter( 'the_content', 'emerson_paypal_donate_button_src', 12 );

add_action(
	'wp_head',
	static function (): void {
		if ( is_admin() ) {
			return;
		}

		$items = emerson_preload_manifest();
		$paypal_local = emerson_paypal_donate_button_local_url();
		if ( $paypal_local !== '' && emerson_content_has_paypal_donate_gif() ) {
			$items[] = array(
				'href'  => $paypal_local,
				'as'    => 'image',
				'pages' => 'all',
			);
		}
		if ( is_front_page() ) {
			/*
			 * Critical rainbow heading so “You Are Welcome!” does not wait on
			 * the full stylesheet (transparent text until clip CSS arrives).
			 * Sermon is click-to-load: preload the YouTube poster the page shows.
			 */
			if ( function_exists( 'emerson_latest_sermon_resolved' ) ) {
				$sermon = emerson_latest_sermon_resolved();
				if ( $sermon['id'] !== '' && function_exists( 'emerson_youtube_poster_url' ) ) {
					$poster = emerson_youtube_poster_url( $sermon['id'] );
					echo '<link rel="preload" href="' . esc_url( $poster ) . '" as="image">'."\n";
				}
			}
			echo "<style id=\"emerson-welcome-rainbow-critical\">"
				. ".emerson-welcome__rainbow{"
				. "color:#6d28d9;"
				. "background-image:linear-gradient(90deg,#c41e3a,#e85d04,#ca8a04,#15803d,#1d4ed8,#7c3aed);"
				. "-webkit-background-clip:text;background-clip:text"
				. "}"
				. "@supports ((-webkit-background-clip:text) or (background-clip:text)){"
				. ".emerson-welcome__rainbow{color:transparent;-webkit-text-fill-color:transparent}"
				. "}"
				. "</style>\n";
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$href = trim( (string) ( $item['href'] ?? '' ) );
			if ( $href === '' || ! emerson_preload_pages_match( $item['pages'] ?? 'all' ) ) {
				continue;
			}
			$kind = (string) ( $item['type'] ?? 'preload' );
			if ( $kind === 'preconnect' ) {
				$cross = ! empty( $item['crossorigin'] ) ? ' crossorigin' : '';
				printf(
					'<link rel="preconnect" href="%s"%s />' . "\n",
					esc_url( $href ),
					$cross // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute or empty
				);
				continue;
			}
			$as = trim( (string) ( $item['as'] ?? '' ) );
			if ( $as === '' ) {
				continue;
			}
			$cross = ! empty( $item['crossorigin'] ) ? ' crossorigin' : '';
			printf(
				'<link rel="preload" href="%s" as="%s"%s />' . "\n",
				esc_url( $href ),
				esc_attr( $as ),
				$cross // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed attribute or empty
			);
		}
	},
	1
);
