<?php
/**
 * Homepage welcome photos: the Gallery block on the page, shown as a Blossom carousel.
 *
 * The block editor keeps a normal gallery, so the pictures can be added, reordered,
 * and removed there. On the front page that gallery is the slider.
 */

declare( strict_types=1 );

function emerson_home_gallery_enqueue(): void {
	if ( ! is_front_page() ) {
		return;
	}
	$css = emerson_chapel_path( 'assets/vendor/blossom/blossom-carousel-web.css' );
	$js  = emerson_chapel_path( 'assets/vendor/blossom/blossom-carousel-web.umd.js' );
	if ( ! is_readable( $css ) || ! is_readable( $js ) ) {
		return;
	}
	wp_enqueue_style(
		'blossom-carousel',
		emerson_chapel_url( 'assets/vendor/blossom/blossom-carousel-web.css' ),
		array(),
		(string) filemtime( $css )
	);
	wp_enqueue_script(
		'blossom-carousel',
		emerson_chapel_url( 'assets/vendor/blossom/blossom-carousel-web.umd.js' ),
		array(),
		(string) filemtime( $js ),
		true
	);
	wp_add_inline_script(
		'blossom-carousel',
		<<<'JS'
(function () {
	var carousel = document.getElementById('emerson-home-gallery');
	if (!carousel) {
		return;
	}
	var slides = carousel.querySelectorAll('[data-blossom-slide]');
	if (slides.length < 2) {
		return;
	}
	function watch(host) {
		var button = host && host.querySelector('button');
		if (!button) {
			return null;
		}
		var enable = function () {
			if (button.disabled) {
				button.disabled = false;
			}
		};
		new MutationObserver(enable).observe(button, { attributes: true, attributeFilter: ['disabled'] });
		enable();
		return button;
	}
	var prev = watch(document.querySelector('blossom-prev[for="emerson-home-gallery"]'));
	var next = watch(document.querySelector('blossom-next[for="emerson-home-gallery"]'));
	function motion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
	}
	function atStart() {
		return carousel.scrollLeft <= 2;
	}
	function atEnd() {
		return carousel.scrollLeft >= carousel.scrollWidth - carousel.clientWidth - 2;
	}
	if (prev) {
		prev.addEventListener('click', function () {
			if (atStart()) {
				carousel.scrollTo({ left: slides[slides.length - 1].offsetLeft, behavior: motion() });
			}
		});
	}
	if (next) {
		next.addEventListener('click', function () {
			if (atEnd()) {
				carousel.scrollTo({ left: 0, behavior: motion() });
			}
		});
	}
})();
JS
	);
}
add_action( 'wp_enqueue_scripts', 'emerson_home_gallery_enqueue' );

/**
 * Alt text stored on an image block.
 */
function emerson_home_gallery_alt_from_html( string $html ): string {
	if ( preg_match( '/\balt="([^"]*)"/', $html, $match ) ) {
		return html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
	}
	return '';
}

/**
 * Slides in gallery order. Each item is attachment ID and alt text.
 *
 * @param array<string,mixed> $block
 * @return array<int,array{id:int,alt:string}>
 */
function emerson_home_gallery_slides_from_block( array $block ): array {
	$slides = array();
	foreach ( $block['innerBlocks'] ?? array() as $inner ) {
		if ( ! is_array( $inner ) || ( $inner['blockName'] ?? '' ) !== 'core/image' ) {
			continue;
		}
		$html = (string) ( $inner['innerHTML'] ?? '' );
		$id   = (int) ( $inner['attrs']['id'] ?? 0 );
		if ( $id <= 0 && preg_match( '/wp-image-(\d+)/', $html, $match ) ) {
			$id = (int) $match[1];
		}
		if ( $id <= 0 ) {
			continue;
		}
		$alt = emerson_home_gallery_alt_from_html( $html );
		if ( $alt === '' ) {
			$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		}
		$slides[] = array(
			'id'  => $id,
			'alt' => $alt,
		);
	}
	return $slides;
}

/**
 * @param array<int,array{id:int,alt:string}> $slides
 */
function emerson_home_gallery_html( array $slides ): string {
	$markup = '';
	$index  = 0;
	foreach ( $slides as $slide ) {
		$src = wp_get_attachment_image_url( $slide['id'], 'large' );
		if ( ! is_string( $src ) || $src === '' ) {
			continue;
		}
		++$index;
		$markup .= '<div class="emerson-home-gallery__slide" data-blossom-slide>';
		$markup .= '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $slide['alt'] ) . '" width="1024" height="768"';
		if ( 1 === $index ) {
			$markup .= ' fetchpriority="high"';
		} else {
			$markup .= ' loading="lazy"';
		}
		$markup .= '>';
		$markup .= '</div>';
	}
	if ( $markup === '' ) {
		return '';
	}
	return '<div class="emerson-home-gallery">'
		. '<p class="screen-reader-text">Community photos</p>'
		. '<blossom-carousel id="emerson-home-gallery" class="emerson-home-gallery__track">' . $markup . '</blossom-carousel>'
		. '<div class="emerson-home-gallery__controls">'
		. '<blossom-prev for="emerson-home-gallery">Previous</blossom-prev>'
		. '<blossom-dots for="emerson-home-gallery"></blossom-dots>'
		. '<blossom-next for="emerson-home-gallery">Next</blossom-next>'
		. '</div></div>';
}

/**
 * @param array<string,mixed> $block
 */
function emerson_home_gallery_render_block( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/gallery' || ! is_front_page() ) {
		return $content;
	}
	$class = (string) ( $block['attrs']['className'] ?? '' );
	if ( ! str_contains( $class, 'emerson-home-gallery-source' ) ) {
		return $content;
	}
	$gallery = emerson_home_gallery_html( emerson_home_gallery_slides_from_block( $block ) );
	return $gallery !== '' ? $gallery : $content;
}
add_filter( 'render_block', 'emerson_home_gallery_render_block', 10, 2 );
