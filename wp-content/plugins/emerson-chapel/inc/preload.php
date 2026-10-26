<?php
/**
 * Early hints for CSS, key images, and video thumbnails.
 *
 * Add entries to emerson_preload_manifest() — each item needs href + as.
 * Optional: pages ('all', 'home', page id, or list of ids), type (preload|preconnect), crossorigin.
 */

declare( strict_types=1 );

/**
 * @return list<array{href:string,as?:string,type?:string,pages?:string|int|list<int|string>,crossorigin?:bool}>
 */
function emerson_preload_manifest(): array {
	$css_path = emerson_chapel_path( 'assets/css/custom.css' );
	$css_ver  = is_readable( $css_path ) ? (string) filemtime( $css_path ) : '1';
	$css_href = emerson_chapel_url( 'assets/css/custom.css' ) . '?v=' . rawurlencode( $css_ver );

	$blossom_path = emerson_chapel_path( 'assets/vendor/blossom/blossom-carousel-web.css' );
	$blossom_href = '';
	if ( is_readable( $blossom_path ) ) {
		$blossom_href = emerson_chapel_url( 'assets/vendor/blossom/blossom-carousel-web.css' )
			. '?v=' . rawurlencode( (string) filemtime( $blossom_path ) );
	}

	$list = array(
		array(
			'href'  => $css_href,
			'as'    => 'style',
			'pages' => 'all',
		),
	);

	if ( $blossom_href !== '' ) {
		$list[] = array(
			'href'  => $blossom_href,
			'as'    => 'style',
			'pages' => 'home',
		);
	}

	return $list;
}

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

/**
 * YouTube poster for the homepage sermon iframe (when we know the video id).
 */
function emerson_preload_homepage_sermon_thumb(): string {
	if ( ! function_exists( 'emerson_homepage_sermon_url' ) || ! function_exists( 'emerson_youtube_id' ) ) {
		return '';
	}
	$url = emerson_homepage_sermon_url();
	if ( emerson_youtube_id( $url ) === '' && function_exists( 'emerson_latest_gallery_sermon' ) ) {
		$url = emerson_latest_gallery_sermon()['url'] ?? '';
	}
	$id = emerson_youtube_id( $url );
	if ( $id === '' ) {
		return '';
	}
	return 'https://i.ytimg.com/vi/' . rawurlencode( $id ) . '/hqdefault.jpg';
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
			$thumb = emerson_preload_homepage_sermon_thumb();
			if ( $thumb !== '' ) {
				$items[] = array(
					'href'  => $thumb,
					'as'    => 'image',
					'pages' => 'home',
				);
				$items[] = array(
					'href'  => 'https://i.ytimg.com',
					'type'  => 'preconnect',
					'pages' => 'home',
				);
			}
			/*
			 * Critical rainbow heading so “You Are Welcome!” does not wait on
			 * the full stylesheet (transparent text until clip CSS arrives).
			 */
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
