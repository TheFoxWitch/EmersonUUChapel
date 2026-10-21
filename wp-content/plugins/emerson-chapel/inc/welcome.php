<?php
/**
 * Homepage “You Are Welcome” symbols.
 *
 * WordPress turns most of the faith symbols into Twemoji images. Om (U+1F549)
 * is not in that replacement list, so it stayed a native emoji and looked
 * different. Use the same Twemoji SVG the others already use.
 */

declare( strict_types=1 );

add_filter( 'the_content', 'emerson_welcome_om_twemoji', 20 );

function emerson_welcome_om_twemoji( string $content ): string {
	if ( ! str_contains( $content, 'emerson-welcome__symbols' ) || ! str_contains( $content, '🕉' ) ) {
		return $content;
	}
	if ( str_contains( $content, '1f549.svg' ) ) {
		return $content;
	}

	$svg = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/17.0.2/svg/' );
	$img = sprintf(
		'<img draggable="false" role="img" class="emoji" alt="🕉" src="%s">',
		esc_url( $svg . '1f549.svg' )
	);

	return str_replace( '🕉', $img, $content );
}
