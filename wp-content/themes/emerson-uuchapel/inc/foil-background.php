<?php
/**
 * Optional foil background: the chapel symbols, with a moving shine.
 *
 * A rainbow button in the corner turns it on. The choice stays in this browser.
 */

declare( strict_types=1 );

const EMERSON_FOIL_SYMBOLS_OPTION = 'emerson_foil_symbols';

/**
 * Symbols shipped with the theme. Used until someone saves a list in the dashboard.
 *
 * @return list<string>
 */
function emerson_foil_default_symbols(): array {
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

/**
 * One stored symbol, trimmed. Empty strings are dropped by the caller.
 */
function emerson_foil_clean_symbol( string $symbol ): string {
	$symbol = trim( wp_strip_all_tags( $symbol ) );
	if ( $symbol === '' ) {
		return '';
	}
	if ( function_exists( 'mb_substr' ) && mb_strlen( $symbol ) > 16 ) {
		$symbol = mb_substr( $symbol, 0, 16 );
	}
	return $symbol;
}

/**
 * The symbols painted on the optional page background.
 *
 * @return list<string>
 */
function emerson_foil_symbols(): array {
	$stored = get_option( EMERSON_FOIL_SYMBOLS_OPTION, null );
	if ( ! is_array( $stored ) ) {
		return emerson_foil_default_symbols();
	}
	$clean = array();
	foreach ( $stored as $symbol ) {
		if ( ! is_string( $symbol ) ) {
			continue;
		}
		$symbol = emerson_foil_clean_symbol( $symbol );
		if ( $symbol === '' ) {
			continue;
		}
		$clean[] = $symbol;
		if ( count( $clean ) >= 32 ) {
			break;
		}
	}
	return $clean !== array() ? $clean : emerson_foil_default_symbols();
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

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Page background',
			'Page background',
			'edit_pages',
			'emerson-foil',
			'emerson_foil_admin_page',
			'dashicons-art',
			64
		);
	}
);

/**
 * @return string|null Null when this request is not a save.
 */
function emerson_foil_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_foil_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_foil_save' );
	$posted = isset( $_POST['foil_symbols'] ) ? wp_unslash( $_POST['foil_symbols'] ) : array();
	if ( ! is_array( $posted ) ) {
		$posted = array();
	}
	$clean = array();
	foreach ( $posted as $symbol ) {
		if ( ! is_string( $symbol ) ) {
			continue;
		}
		$symbol = emerson_foil_clean_symbol( $symbol );
		if ( $symbol === '' ) {
			continue;
		}
		$clean[] = $symbol;
		if ( count( $clean ) >= 32 ) {
			break;
		}
	}
	if ( $clean === array() ) {
		return 'Keep at least one symbol. Nothing was changed.';
	}
	update_option( EMERSON_FOIL_SYMBOLS_OPTION, $clean, false );
	return 'Saved. The background repeats ' . ( count( $clean ) === 1 ? 'this symbol.' : 'these ' . count( $clean ) . ' symbols.' );
}

function emerson_foil_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit the page background.' ) );
	}
	$notice  = emerson_foil_admin_save();
	$symbols = emerson_foil_symbols();
	echo '<div class="wrap">';
	echo '<h1>Page background</h1>';
	echo '<p>These symbols repeat across the optional page background. Visitors turn that background on with the rainbow button. Add a symbol, change one, or remove one, then save. One symbol fills the field. Three symbols repeat those three.</p>';
	if ( is_string( $notice ) ) {
		$is_error = str_starts_with( $notice, 'Keep at least' );
		echo '<div class="notice notice-' . ( $is_error ? 'error' : 'success' ) . '"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<style>
		.emerson-foil-symbol{display:flex;align-items:center;gap:0.5rem;margin:0 0 0.5rem;}
		.emerson-foil-symbol input{font-size:1.4rem;width:8rem;}
	</style>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_foil_save' );
	echo '<div class="emerson-foil-symbols">';
	foreach ( $symbols as $symbol ) {
		echo '<div class="emerson-foil-symbol">';
		echo '<input type="text" name="foil_symbols[]" maxlength="16" value="' . esc_attr( $symbol ) . '" aria-label="Background symbol">';
		echo '<button type="button" class="button emerson-foil-remove">Remove</button>';
		echo '</div>';
	}
	echo '</div>';
	echo '<p><button type="button" class="button emerson-foil-add">Add a symbol</button></p>';
	echo '<p><button type="submit" name="emerson_foil_save" value="1" class="button button-primary">Save symbols</button></p>';
	echo '</form></div>';
	echo '<script>
		(function () {
			var list = document.querySelector(".emerson-foil-symbols");
			var add = document.querySelector(".emerson-foil-add");
			if (!list || !add) return;
			function row(value) {
				var line = document.createElement("div");
				line.className = "emerson-foil-symbol";
				line.innerHTML = "<input type=\\"text\\" name=\\"foil_symbols[]\\" maxlength=\\"16\\" aria-label=\\"Background symbol\\"> <button type=\\"button\\" class=\\"button emerson-foil-remove\\">Remove</button>";
				line.querySelector("input").value = value;
				return line;
			}
			add.addEventListener("click", function () {
				list.appendChild(row(""));
				list.lastElementChild.querySelector("input").focus();
			});
			list.addEventListener("click", function (event) {
				var button = event.target.closest(".emerson-foil-remove");
				if (!button) return;
				var lines = list.querySelectorAll(".emerson-foil-symbol");
				if (lines.length === 1) {
					lines[0].querySelector("input").value = "";
					lines[0].querySelector("input").focus();
					return;
				}
				button.closest(".emerson-foil-symbol").remove();
			});
		})();
	</script>';
}
