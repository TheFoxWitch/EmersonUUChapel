<?php
/**
 * Religious Education: the handprint mural is a banner above the page title.
 *
 * The page content no longer includes that photo. The larger original file
 * is used so the banner stays sharp across the width of the page.
 */

declare( strict_types=1 );

const EMERSON_RE_PAGE_ID    = 203;
const EMERSON_RE_MURAL_ID   = 348;
const EMERSON_RE_MURAL_CROP = 349;

function emerson_re_banner_html(): string {
	$src = wp_get_attachment_image_url( EMERSON_RE_MURAL_ID, 'full' );
	if ( ! is_string( $src ) || $src === '' ) {
		return '';
	}
	$alt = (string) get_post_meta( EMERSON_RE_MURAL_ID, '_wp_attachment_image_alt', true );
	if ( $alt === '' ) {
		$alt = (string) get_post_meta( EMERSON_RE_MURAL_CROP, '_wp_attachment_image_alt', true );
	}
	if ( $alt === '' ) {
		$alt = 'A white mural covered in colorful painted handprints of many sizes';
	}

	return '<figure class="emerson-re-banner alignfull">'
		. '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" width="2302" height="446">'
		. '</figure>';
}

/**
 * @param array<string,mixed> $block
 */
function emerson_re_render_block( string $content, array $block ): string {
	if ( ! is_page( EMERSON_RE_PAGE_ID ) ) {
		return $content;
	}
	$name = $block['blockName'] ?? '';
	if ( $name === 'core/post-title' ) {
		return emerson_re_banner_html() . $content;
	}
	if ( $name === 'core/image' && in_array( (int) ( $block['attrs']['id'] ?? 0 ), array( EMERSON_RE_MURAL_ID, EMERSON_RE_MURAL_CROP ), true ) ) {
		return '';
	}
	return $content;
}
add_filter( 'render_block', 'emerson_re_render_block', 10, 2 );
