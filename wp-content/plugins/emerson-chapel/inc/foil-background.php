<?php
/**
 * Optional foil background: the chapel symbols, with a moving shine.
 *
 * A rainbow button in the corner turns it on. The choice stays in this browser.
 * Symbols and motion settings are edited under Page background in the dashboard.
 */

declare( strict_types=1 );

const EMERSON_FOIL_SYMBOLS_OPTION = 'emerson_foil_symbols';
const EMERSON_FOIL_STYLE_OPTION   = 'emerson_foil_style';

/**
 * Symbols shipped with the plugin. Used until someone saves a list in the dashboard.
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
 * Motion and element defaults for the foil background.
 *
 * @return array{
 *   drift_seconds:float,
 *   shine_seconds:float,
 *   canvas_opacity:float,
 *   shine_opacity:float,
 *   shine_angle:float,
 *   drift_on:bool,
 *   shine_on:bool,
 *   shine_rainbow:bool,
 *   glow_on:bool,
 *   randomize_on_load:bool,
 *   layer_color:string,
 *   toggle_emoji:string,
 *   glow_exclude:list<string>
 * }
 */
function emerson_foil_style_defaults(): array {
	return array(
		'drift_seconds'     => 36.0,
		'shine_seconds'     => 3.2,
		'canvas_opacity'    => 0.25,
		'shine_opacity'     => 0.25,
		'shine_angle'       => 0.0,
		'drift_on'          => true,
		'shine_on'          => true,
		'shine_rainbow'     => false,
		'glow_on'           => true,
		'randomize_on_load' => false,
		'layer_color'       => '#f4f7f1',
		'toggle_emoji'      => '🌈',
		'glow_exclude'      => array(
			'button',
			'.wp-element-button',
			'input',
			'select',
			'textarea',
			'.wp-block-button__link',
			'.wp-block-cover',
			'.wp-block-columns',
			'.emerson-welcome__rainbow',
		),
	);
}

/**
 * First Unicode grapheme cluster (one emoji / character), or empty.
 * Emoji lengths vary; string length / maxlength cannot mean “one emoji”.
 */
function emerson_foil_one_grapheme( string $text ): string {
	$text = trim( $text );
	if ( $text === '' ) {
		return '';
	}
	if ( function_exists( 'grapheme_substr' ) ) {
		$one = grapheme_substr( $text, 0, 1 );
		return is_string( $one ) ? $one : '';
	}
	if ( class_exists( 'IntlBreakIterator' ) ) {
		$breaker = \IntlBreakIterator::createCharacterInstance();
		if ( $breaker ) {
			$breaker->setText( $text );
			$start = $breaker->first();
			$end   = $breaker->next();
			if ( is_int( $start ) && is_int( $end ) && $end > $start ) {
				return substr( $text, $start, $end - $start );
			}
		}
	}
	/* Last resort: first UTF-8 code point (may split some multi-code-point emoji). */
	if ( function_exists( 'mb_substr' ) ) {
		return (string) mb_substr( $text, 0, 1, 'UTF-8' );
	}
	return $text;
}

/**
 * One stored symbol, trimmed to a single grapheme. Empty strings are dropped by the caller.
 */
function emerson_foil_clean_symbol( string $symbol ): string {
	return emerson_foil_one_grapheme( wp_strip_all_tags( $symbol ) );
}

/**
 * Keep only plain CSS selectors (tags, classes, ids, simple combinators).
 */
function emerson_foil_clean_selector( string $selector ): string {
	$selector = trim( wp_strip_all_tags( $selector ) );
	if ( $selector === '' ) {
		return '';
	}
	if ( ! preg_match( '/^[a-zA-Z0-9_\-.#:>+~\s\[\]="\',|*]+$/', $selector ) ) {
		return '';
	}
	if ( preg_match( '/[{}<>]|url\s*\(|expression\s*\(/i', $selector ) ) {
		return '';
	}
	if ( function_exists( 'mb_substr' ) && mb_strlen( $selector ) > 80 ) {
		$selector = mb_substr( $selector, 0, 80 );
	}
	return $selector;
}

/**
 * @param mixed $value
 */
function emerson_foil_clamp_float( $value, float $min, float $max, float $fallback ): float {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}
	$number = (float) $value;
	if ( $number < $min ) {
		return $min;
	}
	if ( $number > $max ) {
		return $max;
	}
	return $number;
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

/**
 * Saved motion and element settings, merged with defaults.
 *
 * @return array{
 *   drift_seconds:float,
 *   shine_seconds:float,
 *   canvas_opacity:float,
 *   shine_opacity:float,
 *   shine_angle:float,
 *   drift_on:bool,
 *   shine_on:bool,
 *   shine_rainbow:bool,
 *   glow_on:bool,
 *   randomize_on_load:bool,
 *   layer_color:string,
 *   toggle_emoji:string,
 *   glow_exclude:list<string>
 * }
 */
function emerson_foil_style(): array {
	$defaults = emerson_foil_style_defaults();
	$stored   = get_option( EMERSON_FOIL_STYLE_OPTION, null );
	if ( ! is_array( $stored ) ) {
		return $defaults;
	}

	$color = sanitize_hex_color( (string) ( $stored['layer_color'] ?? '' ) );
	if ( ! is_string( $color ) || $color === '' ) {
		$color = $defaults['layer_color'];
	}

	$emoji = emerson_foil_clean_symbol( (string) ( $stored['toggle_emoji'] ?? '' ) );
	if ( $emoji === '' ) {
		$emoji = $defaults['toggle_emoji'];
	}

	$exclude = array();
	$raw_exclude = $stored['glow_exclude'] ?? $defaults['glow_exclude'];
	if ( is_string( $raw_exclude ) ) {
		$raw_exclude = preg_split( '/[\r\n,]+/', $raw_exclude ) ?: array();
	}
	if ( is_array( $raw_exclude ) ) {
		foreach ( $raw_exclude as $selector ) {
			if ( ! is_string( $selector ) ) {
				continue;
			}
			$selector = emerson_foil_clean_selector( $selector );
			if ( $selector === '' ) {
				continue;
			}
			$exclude[] = $selector;
			if ( count( $exclude ) >= 40 ) {
				break;
			}
		}
	}
	if ( $exclude === array() ) {
		$exclude = $defaults['glow_exclude'];
	}

	return array(
		'drift_seconds'     => emerson_foil_clamp_float( $stored['drift_seconds'] ?? null, 1.0, 120.0, $defaults['drift_seconds'] ),
		'shine_seconds'     => emerson_foil_clamp_float( $stored['shine_seconds'] ?? null, 0.5, 30.0, $defaults['shine_seconds'] ),
		'canvas_opacity'    => emerson_foil_clamp_float( $stored['canvas_opacity'] ?? null, 0.05, 1.0, $defaults['canvas_opacity'] ),
		'shine_opacity'     => emerson_foil_clamp_float( $stored['shine_opacity'] ?? null, 0.05, 1.0, $defaults['shine_opacity'] ),
		'shine_angle'       => emerson_foil_clamp_float( $stored['shine_angle'] ?? null, 0.0, 360.0, $defaults['shine_angle'] ),
		'drift_on'          => array_key_exists( 'drift_on', $stored ) ? ! empty( $stored['drift_on'] ) : $defaults['drift_on'],
		'shine_on'          => array_key_exists( 'shine_on', $stored ) ? ! empty( $stored['shine_on'] ) : $defaults['shine_on'],
		'shine_rainbow'     => array_key_exists( 'shine_rainbow', $stored ) ? ! empty( $stored['shine_rainbow'] ) : $defaults['shine_rainbow'],
		'glow_on'           => array_key_exists( 'glow_on', $stored ) ? ! empty( $stored['glow_on'] ) : $defaults['glow_on'],
		'randomize_on_load' => array_key_exists( 'randomize_on_load', $stored ) ? ! empty( $stored['randomize_on_load'] ) : $defaults['randomize_on_load'],
		'layer_color'       => $color,
		'toggle_emoji'      => $emoji,
		'glow_exclude'      => $exclude,
	);
}

add_action(
	'wp_head',
	static function (): void {
		if ( is_admin() ) {
			return;
		}
		$style = emerson_foil_style();
		/*
		 * Critical foil CSS before the body: without position:fixed in time, the huge
		 * symbol canvas sits in normal flow, the document height collapses when CSS
		 * arrives, and the browser clamps scroll to the top on back-navigation.
		 */
		/* Wash on immediately; keep the icon sheet masked shut until custom.css wipe runs. */
		$wash = esc_attr( $style['layer_color'] );
		echo '<style id="emerson-foil-critical">'
			. '.emerson-foil-layer{position:fixed!important;inset:0!important;z-index:0;overflow:hidden!important;pointer-events:none;contain:paint;transform:translateZ(0);background:' . $wash . ';opacity:0;visibility:hidden}'
			/* Layer stays hidden on bare emerson-foil; wipe/ready classes reveal it. */
			. 'html.emerson-foil.emerson-foil-ready .emerson-foil-layer,html.emerson-foil.emerson-foil-wiping-in .emerson-foil-layer,html.emerson-foil.emerson-foil-wiping-out .emerson-foil-layer{opacity:1;visibility:visible}'
			. 'html.emerson-foil.emerson-foil-ready:not(.emerson-foil-wiping-out):not(.emerson-foil-wiping-in),html.emerson-foil.emerson-foil-ready:not(.emerson-foil-wiping-out):not(.emerson-foil-wiping-in) body{background-color:' . $wash . '!important;background-image:none!important}'
			. 'html.emerson-foil .wp-site-blocks{background-color:transparent!important;background-image:none!important;position:relative;z-index:1}'
			. '.emerson-foil__sheet{position:relative;width:max-content;line-height:1;'
			. '-webkit-mask-image:linear-gradient(90deg,#000 0%,#000 38%,transparent 52%,transparent 100%);mask-image:linear-gradient(90deg,#000 0%,#000 38%,transparent 52%,transparent 100%);'
			. '-webkit-mask-size:280% 280%;mask-size:280% 280%;-webkit-mask-repeat:no-repeat;mask-repeat:no-repeat;'
			. '-webkit-mask-position:100% 50%;mask-position:100% 50%}'
			. '.emerson-foil-toggle{position:fixed!important;z-index:40;right:1.5rem;bottom:1.5rem}'
			. "</style>\n";
		/* Let the browser own back/forward scroll again — manual restore was leaving people at the top. */
		echo "<script>try{if(localStorage.getItem('emerson-foil')==='1')document.documentElement.classList.add('emerson-foil');}catch(e){}</script>\n";
	},
	0
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( is_admin() ) {
			return;
		}
		$style       = emerson_foil_style();
		$exclude_css = $style['glow_exclude'] !== array() ? implode( ', ', $style['glow_exclude'] ) : 'button';
		$glow_shadow = $style['glow_on']
			? '0 0 0.28em #fff, 0 0 0.28em #fff, 0 0 0.12em #fff'
			: 'none';
		$drift_anim  = $style['drift_on']
			? 'emerson-foil-drift ' . $style['drift_seconds'] . 's cubic-bezier(0.3, 0, 0.7, 1) infinite alternate'
			: 'none';
		$shine_anim  = $style['shine_on']
			? 'emerson-foil-pan ' . $style['shine_seconds'] . 's linear infinite'
			: 'none';

		$css = ':root{'
			. '--emerson-foil-layer-color:' . $style['layer_color'] . ';'
			. '--emerson-foil-canvas-opacity:' . $style['canvas_opacity'] . ';'
			. '--emerson-foil-shine-opacity:' . $style['shine_opacity'] . ';'
			. '--emerson-foil-shine-angle:' . $style['shine_angle'] . 'deg;'
			. '--emerson-foil-drift-seconds:' . $style['drift_seconds'] . ';'
			. '--emerson-foil-shine-seconds:' . $style['shine_seconds'] . ';'
			. '--emerson-foil-drift-animation:' . $drift_anim . ';'
			. '--emerson-foil-shine-animation:' . $shine_anim . ';'
			. '--emerson-foil-glow:' . $glow_shadow . ';'
			/* Wipe axis defaults (shine 0°); JS updates for other angles. */
			. '--emerson-foil-wipe-closed:100% 50%;'
			. '--emerson-foil-wipe-open:0% 50%;'
			. '}'
			. 'html.emerson-foil .wp-site-blocks :is(' . $exclude_css . '){text-shadow:none;}';

		wp_add_inline_style( 'emerson-chapel', $css );
	},
	20
);

add_action(
	'wp_body_open',
	static function (): void {
		if ( is_admin() ) {
			return;
		}
		$symbols = emerson_foil_symbols();
		$style   = emerson_foil_style();
		/* Inline fixed box so a deferred stylesheet cannot leave this in normal flow. */
		echo '<div class="emerson-foil-layer" aria-hidden="true" style="position:fixed;inset:0;z-index:0;overflow:hidden;pointer-events:none">';
		echo '<div class="emerson-foil__sheet">';
		echo '<canvas class="emerson-foil__canvas" data-symbols="' . esc_attr( wp_json_encode( $symbols ) ) . '"'
			. ( ! empty( $style['randomize_on_load'] ) ? ' data-randomize="1"' : '' )
			. '></canvas>';
		echo '</div>';
		/* Shine is a sibling of the sheet so it sweeps the viewport, not the huge canvas. */
		$shine_class = 'emerson-foil__shine' . ( ! empty( $style['shine_rainbow'] ) ? ' emerson-foil__shine--rainbow' : '' );
		echo '<div class="' . esc_attr( $shine_class ) . '"><span></span></div>';
		echo '</div>';
		echo '<button type="button" class="emerson-foil-toggle wp-exclude-emoji" aria-pressed="false" aria-label="Switch the page background" style="position:fixed;z-index:40;right:1.5rem;bottom:1.5rem">'
			. esc_html( $style['toggle_emoji'] )
			. '</button>';
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

/**
 * @return string|null Null when this request is not a save.
 */
function emerson_foil_style_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_foil_style_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_foil_style_save' );

	$defaults = emerson_foil_style_defaults();
	$color    = sanitize_hex_color( (string) ( wp_unslash( $_POST['foil_layer_color'] ?? '' ) ) );
	if ( ! is_string( $color ) || $color === '' ) {
		$color = $defaults['layer_color'];
	}

	$emoji = emerson_foil_clean_symbol( (string) ( wp_unslash( $_POST['foil_toggle_emoji'] ?? '' ) ) );
	if ( $emoji === '' ) {
		$emoji = $defaults['toggle_emoji'];
	}

	$exclude = array();
	$raw     = (string) ( wp_unslash( $_POST['foil_glow_exclude'] ?? '' ) );
	foreach ( preg_split( '/[\r\n,]+/', $raw ) ?: array() as $selector ) {
		$selector = emerson_foil_clean_selector( $selector );
		if ( $selector === '' ) {
			continue;
		}
		$exclude[] = $selector;
		if ( count( $exclude ) >= 40 ) {
			break;
		}
	}
	if ( $exclude === array() ) {
		$exclude = $defaults['glow_exclude'];
	}

	$saved = array(
		'drift_seconds'     => emerson_foil_clamp_float( wp_unslash( $_POST['foil_drift_seconds'] ?? null ), 1.0, 120.0, $defaults['drift_seconds'] ),
		'shine_seconds'     => emerson_foil_clamp_float( wp_unslash( $_POST['foil_shine_seconds'] ?? null ), 0.5, 30.0, $defaults['shine_seconds'] ),
		'canvas_opacity'    => emerson_foil_clamp_float( wp_unslash( $_POST['foil_canvas_opacity'] ?? null ), 5.0, 100.0, $defaults['canvas_opacity'] * 100 ) / 100,
		'shine_opacity'     => emerson_foil_clamp_float( wp_unslash( $_POST['foil_shine_opacity'] ?? null ), 5.0, 100.0, $defaults['shine_opacity'] * 100 ) / 100,
		'shine_angle'       => emerson_foil_clamp_float( wp_unslash( $_POST['foil_shine_angle'] ?? null ), 0.0, 360.0, $defaults['shine_angle'] ),
		'drift_on'          => ! empty( $_POST['foil_drift_on'] ),
		'shine_on'          => ! empty( $_POST['foil_shine_on'] ),
		'shine_rainbow'     => ! empty( $_POST['foil_shine_rainbow'] ),
		'glow_on'           => ! empty( $_POST['foil_glow_on'] ),
		'randomize_on_load' => ! empty( $_POST['foil_randomize_on_load'] ),
		'layer_color'       => $color,
		'toggle_emoji'      => $emoji,
		'glow_exclude'      => $exclude,
	);
	update_option( EMERSON_FOIL_STYLE_OPTION, $saved, false );
	return 'Saved motion and elements for the page background.';
}

function emerson_foil_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit the page background.' ) );
	}
	$symbols_notice = emerson_foil_admin_save();
	$style_notice   = emerson_foil_style_save();
	$symbols        = emerson_foil_symbols();
	$style          = emerson_foil_style();

	echo '<div class="wrap">';
	echo '<h1>Page background</h1>';
	echo '<p>Visitors turn the optional background on with the corner button. Symbols fill the field. Motion and which elements keep a reading glow are set below.</p>';

	foreach ( array( $symbols_notice, $style_notice ) as $notice ) {
		if ( ! is_string( $notice ) ) {
			continue;
		}
		$is_error = str_starts_with( $notice, 'Keep at least' );
		echo '<div class="notice notice-' . ( $is_error ? 'error' : 'success' ) . '"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<style>
		.emerson-foil-symbol{display:flex;align-items:center;gap:0.5rem;margin:0 0 0.5rem;}
		.emerson-foil-symbol input{font-size:1.4rem;width:8rem;}
		.emerson-foil-panel{margin:1.5rem 0;padding:1rem 1rem 0;background:#fff;border:1px solid #c3c4c7;max-width:46rem;}
		.emerson-foil-panel textarea{width:100%;min-height:7rem;font-family:Menlo,Consolas,monospace;}
		.emerson-foil-panel .form-table th{width:12rem;}
	</style>';

	echo '<form method="post" class="emerson-foil-panel">';
	wp_nonce_field( 'emerson_foil_save' );
	echo '<h2>Symbols</h2>';
	echo '<p>These symbols repeat across the background. Each field holds <strong>one</strong> emoji (extra characters are trimmed). Add more fields for more symbols.</p>';
	echo '<div class="emerson-foil-symbols">';
	foreach ( $symbols as $symbol ) {
		echo '<div class="emerson-foil-symbol">';
		echo '<input type="text" class="emerson-foil-one-emoji" name="foil_symbols[]" value="' . esc_attr( $symbol ) . '" aria-label="Background symbol" autocomplete="off">';
		echo '<button type="button" class="button emerson-foil-remove">Remove</button>';
		echo '</div>';
	}
	echo '</div>';
	echo '<p><button type="button" class="button emerson-foil-add">Add a symbol</button></p>';
	echo '<p class="submit"><button type="submit" name="emerson_foil_save" value="1" class="button button-primary">Save symbols</button></p>';
	echo '</form>';

	echo '<form method="post" class="emerson-foil-panel">';
	wp_nonce_field( 'emerson_foil_style_save' );
	echo '<h2>Motion and elements</h2>';
	echo '<p>Drift moves the whole field. Shine is the light band across the symbols. Prefer reduced motion still turns both animations off in that browser.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';

	echo '<tr><th scope="row">Drift</th><td>';
	echo '<label><input type="checkbox" name="foil_drift_on" value="1"' . checked( $style['drift_on'], true, false ) . '> Move the symbol field</label>';
	echo '<p><label for="emerson-foil-drift-seconds">Seconds for one drift pass </label>';
	echo '<input type="number" id="emerson-foil-drift-seconds" name="foil_drift_seconds" min="1" max="120" step="0.5" value="' . esc_attr( (string) $style['drift_seconds'] ) . '"></p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">Pattern</th><td>';
	echo '<label><input type="checkbox" name="foil_randomize_on_load" value="1"' . checked( $style['randomize_on_load'], true, false ) . '> Randomize on load</label>';
	echo '<p class="description">Shuffles the symbol order each time the page loads and when the corner button turns the background on. Icons wipe in and out with the shine band (not a flat fade). Same symbols, speeds, and opacities.</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">Shine</th><td>';
	echo '<label><input type="checkbox" name="foil_shine_on" value="1"' . checked( $style['shine_on'], true, false ) . '> Show the moving shine</label>';
	echo '<p><label for="emerson-foil-shine-seconds">Seconds for one shine pass </label>';
	echo '<input type="number" id="emerson-foil-shine-seconds" name="foil_shine_seconds" min="0.5" max="30" step="0.1" value="' . esc_attr( (string) $style['shine_seconds'] ) . '"></p>';
	echo '<p><label for="emerson-foil-shine-angle">Shine angle (degrees) </label>';
	echo '<input type="number" id="emerson-foil-shine-angle" name="foil_shine_angle" min="0" max="360" step="1" value="' . esc_attr( (string) $style['shine_angle'] ) . '"></p>';
	echo '<p class="description">0 is upright (the usual look). 45 or −45 / 315 is a clear diagonal; 90 lays the band on its side.</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="emerson-foil-canvas-opacity">Symbol opacity</label></th><td>';
	echo '<input type="number" id="emerson-foil-canvas-opacity" name="foil_canvas_opacity" min="5" max="100" step="1" value="' . esc_attr( (string) round( $style['canvas_opacity'] * 100 ) ) . '"> %';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="emerson-foil-shine-opacity">Shine opacity</label></th><td>';
	echo '<input type="number" id="emerson-foil-shine-opacity" name="foil_shine_opacity" min="5" max="100" step="1" value="' . esc_attr( (string) round( $style['shine_opacity'] * 100 ) ) . '"> %';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="emerson-foil-layer-color">Background colour</label></th><td>';
	echo '<input type="color" id="emerson-foil-layer-color" name="foil_layer_color" value="' . esc_attr( $style['layer_color'] ) . '">';
	echo ' <label style="margin-left:0.75rem"><input type="checkbox" name="foil_shine_rainbow" value="1"' . checked( $style['shine_rainbow'], true, false ) . '> Rainbow shine</label>';
	echo '<p class="description">Wash colour for the page. Rainbow shine swaps the moving band for a pride gradient (still respects shine opacity and angle).</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row"><label for="emerson-foil-toggle-emoji">Corner button</label></th><td>';
	echo '<input type="text" class="emerson-foil-toggle-emoji emerson-foil-one-emoji" id="emerson-foil-toggle-emoji" name="foil_toggle_emoji" value="' . esc_attr( $style['toggle_emoji'] ) . '" autocomplete="off">';
	echo '<p class="description">One emoji only. Kept as a real emoji (<code>wp-exclude-emoji</code>), so WordPress does not replace it with an image.</p>';
	echo '</td></tr>';

	echo '<tr><th scope="row">Reading glow</th><td>';
	echo '<label><input type="checkbox" name="foil_glow_on" value="1"' . checked( $style['glow_on'], true, false ) . '> Soft white glow on page text</label>';
	echo '<p><label for="emerson-foil-glow-exclude">Skip glow on these elements (one CSS selector per line)</label></p>';
	echo '<textarea id="emerson-foil-glow-exclude" name="foil_glow_exclude" rows="8">' . esc_textarea( implode( "\n", $style['glow_exclude'] ) ) . '</textarea>';
	echo '<p class="description">Use this for banners, buttons, rainbow headings, or anything the emoji script wraps. Example: <code>.emerson-welcome__rainbow</code></p>';
	echo '</td></tr>';

	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_foil_style_save" value="1" class="button button-primary">Save motion and elements</button></p>';
	echo '</form></div>';

	echo '<script>
		(function () {
			var segmenter = (typeof Intl !== "undefined" && Intl.Segmenter)
				? new Intl.Segmenter(undefined, { granularity: "grapheme" })
				: null;
			function firstGrapheme(value) {
				value = String(value || "").trim();
				if (!value) return "";
				if (segmenter) {
					for (var part of segmenter.segment(value)) {
						return part.segment;
					}
					return "";
				}
				return Array.from(value)[0] || "";
			}
			function bindOneEmoji(input) {
				if (!input || input.dataset.emersonOneEmoji === "1") return;
				input.dataset.emersonOneEmoji = "1";
				function trim() {
					var one = firstGrapheme(input.value);
					if (input.value !== one) {
						input.value = one;
					}
				}
				input.addEventListener("input", trim);
				input.addEventListener("blur", trim);
				trim();
			}
			document.querySelectorAll(".emerson-foil-one-emoji").forEach(bindOneEmoji);
			var list = document.querySelector(".emerson-foil-symbols");
			var add = document.querySelector(".emerson-foil-add");
			if (!list || !add) return;
			function row(value) {
				var line = document.createElement("div");
				line.className = "emerson-foil-symbol";
				line.innerHTML = "<input type=\\"text\\" class=\\"emerson-foil-one-emoji\\" name=\\"foil_symbols[]\\" aria-label=\\"Background symbol\\" autocomplete=\\"off\\"> <button type=\\"button\\" class=\\"button emerson-foil-remove\\">Remove</button>";
				var input = line.querySelector("input");
				input.value = firstGrapheme(value);
				bindOneEmoji(input);
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
