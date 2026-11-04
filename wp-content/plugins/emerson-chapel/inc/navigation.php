<?php
/**
 * Header menu: About and Worship are labels, not pages.
 *
 * WordPress still wraps submenu parents in <a>. If the item has no URL
 * (or only #), turn that tag into a span so it is not a link.
 */

declare( strict_types=1 );

/**
 * Old Engage page slug → Community Opportunities.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( is_admin() || ! is_404() ) {
			return;
		}
		$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
		if ( $path !== 'engage' ) {
			return;
		}
		$target = get_page_by_path( 'community-opportunities' );
		if ( ! ( $target instanceof WP_Post ) ) {
			return;
		}
		wp_safe_redirect( get_permalink( $target ), 301 );
		exit;
	},
	1
);

add_filter(
	'render_block_core/navigation-submenu',
	static function ( string $html, array $block ): string {
		$url = isset( $block['attrs']['url'] ) ? trim( (string) $block['attrs']['url'] ) : '';
		if ( '' !== $url && '#' !== $url ) {
			return $html;
		}

		$replaced = preg_replace_callback(
			'#<a class="wp-block-navigation-item__content"([^>]*)>#',
			static function ( array $match ): string {
				$attrs = preg_replace( '/\s(?:href|target|rel)=(?:"[^"]*"|\'[^\']*\')/', '', $match[1] );
				return '<span class="wp-block-navigation-item__content"' . $attrs . '>';
			},
			$html,
			1
		);

		if ( ! is_string( $replaced ) ) {
			return $html;
		}

		$replaced = preg_replace( '#</a>(\s*<button\b)#', '</span>$1', $replaced, 1 );

		return is_string( $replaced ) ? $replaced : $html;
	},
	10,
	2
);
