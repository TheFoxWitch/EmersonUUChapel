<?php
/**
 * Optional foil background: the chapel symbols, with a moving shine.
 *
 * A rainbow button in the corner turns it on. The choice stays in this browser.
 */

declare( strict_types=1 );

/**
 * Emoji from the Love-at-the-center graphic.
 *
 * @return list<string>
 */
function emerson_foil_symbols(): array {
	return array(
		'🏳️‍🌈',
		'🍽️',
		'🙏',
		'🫶',
		'🦋',
		'☯️',
		'☸️',
		'🕊️',
		'☮️',
		'🐛',
		'🪔',
		'🕉️',
		'✊',
		'💗',
		'♻️',
		'🏳️‍⚧️',
	);
}

add_action(
	'wp_head',
	static function (): void {
		if ( is_admin() ) {
			return;
		}
		echo "<script>try{if(localStorage.getItem('emerson-foil')==='1')document.documentElement.classList.add('emerson-foil');}catch(e){}</script>\n";
	},
	0
);

add_action(
	'wp_body_open',
	static function (): void {
		if ( is_admin() ) {
			return;
		}
		$symbols = emerson_foil_symbols();
		echo '<div class="emerson-foil-layer" aria-hidden="true">';
		echo '<div class="emerson-foil__sheet">';
		echo '<canvas class="emerson-foil__canvas" data-symbols="' . esc_attr( wp_json_encode( $symbols ) ) . '"></canvas>';
		echo '<div class="emerson-foil__shine"><span></span></div>';
		echo '</div></div>';
		echo '<button type="button" class="emerson-foil-toggle" aria-pressed="false" aria-label="Switch the page background">';
		echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5l1.6 4.9 4.9 1.6-4.9 1.6L12 15.5l-1.6-4.9L5.5 9l4.9-1.6L12 2.5zm6.2 10.2l.8 2.3 2.3.8-2.3.8-.8 2.3-.8-2.3-2.3-.8 2.3-.8.8-2.3zM6.2 14.2l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7.7-2z"/></svg>';
		echo '</button>';
	}
);
