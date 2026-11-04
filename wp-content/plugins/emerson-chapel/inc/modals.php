<?php
/**
 * One dashboard screen for the site dialogs.
 *
 * Built-in dialogs (recommitment form, annual pledge form, community survey,
 * PayPal fees) keep their own settings. Custom dialogs are added below: each
 * gets a Gutenberg Button class (or shortcode) for a Microsoft/Google or CFF form.
 */

declare( strict_types=1 );

const EMERSON_PAYPAL_FORM_ID        = 8;
const EMERSON_PAYPAL_LABEL_OPTION   = 'emerson_paypal_button_label';
const EMERSON_PAYPAL_HEADING_OPTION = 'emerson_paypal_dialog_heading';
const EMERSON_PAYPAL_LABEL_DEFAULT  = 'Approximate PayPal Fees';
/* Legacy option keys — recommitment modal (Gutenberg class: recommitment-form). */
const EMERSON_PLEDGE_LABEL_OPTION   = 'emerson_pledge_label';
const EMERSON_PLEDGE_URL_OPTION     = 'emerson_pledge_form_url';
const EMERSON_PLEDGE_LABEL_DEFAULT  = '2026-27 Recommitment Form';
/* Annual Google pledge form (Gutenberg class: pledge-card; legacy: pledge-form). */
const EMERSON_ANNUAL_PLEDGE_LABEL_OPTION   = 'emerson_annual_pledge_label';
const EMERSON_ANNUAL_PLEDGE_URL_OPTION     = 'emerson_annual_pledge_url';
const EMERSON_ANNUAL_PLEDGE_HEADING_OPTION = 'emerson_annual_pledge_heading';
const EMERSON_ANNUAL_PLEDGE_STYLE_OPTION   = 'emerson_annual_pledge_style';
const EMERSON_ANNUAL_PLEDGE_LABEL_DEFAULT  = '2026-27 Pledge Card';
const EMERSON_ANNUAL_PLEDGE_URL_DEFAULT    = 'https://docs.google.com/forms/d/1I5Afn2BMJWSS8cAzA8tEJyXi7F0g3tDYzsZTTVMfPJo/viewform';
const EMERSON_ANNUAL_PLEDGE_STYLE_DEFAULT  = 'outline';
const EMERSON_PLEDGE_HEADING_OPTION        = 'emerson_pledge_dialog_heading';
const EMERSON_PLEDGE_STYLE_OPTION          = 'emerson_pledge_style';
const EMERSON_PLEDGE_STYLE_DEFAULT         = 'green';
const EMERSON_PLEDGE_CLASS_OPTION          = 'emerson_pledge_button_class';
const EMERSON_PLEDGE_CLASS_DEFAULT         = 'recommitment-form';
const EMERSON_ANNUAL_PLEDGE_CLASS_OPTION   = 'emerson_annual_pledge_button_class';
const EMERSON_ANNUAL_PLEDGE_CLASS_DEFAULT  = 'pledge-card';
const EMERSON_PAYPAL_CLASS_OPTION          = 'emerson_paypal_button_class';
const EMERSON_PAYPAL_CLASS_DEFAULT         = 'paypal-fees-button';
const EMERSON_CUSTOM_MODALS_OPTION  = 'emerson_custom_modals';
const EMERSON_MODAL_OVERFLOW_ARROW_OPTION = 'emerson_modal_overflow_arrow';

/**
 * Whether overflowing form-maker dialogs show a brief bounce scroll hint.
 */
function emerson_modal_overflow_arrow_enabled(): bool {
	return get_option( EMERSON_MODAL_OVERFLOW_ARROW_OPTION, '1' ) === '1';
}

/**
 * iPhone / iPod / iPad (classic UA). iPadOS “desktop” Safari is handled in JS.
 * Used so we never print a Microsoft Forms iframe into the HTML for those phones.
 */
function emerson_is_apple_touch_ua(): bool {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
	return (bool) preg_match( '/iPhone|iPod|iPad/i', $ua );
}

/**
 * On Apple touch, skip WordPress emoji scripts/styles that hit s.w.org on every page.
 */
add_action(
	'init',
	static function (): void {
		if ( is_admin() || ! emerson_is_apple_touch_ua() ) {
			return;
		}
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	},
	1
);

/**
 * Markup for an external form inside a dialog: iframe on desktop, URL-only on Apple.
 *
 * @return string Escaped HTML.
 */
function emerson_modal_external_form_markup( string $src, string $title ): string {
	$src   = esc_url( $src );
	$title = esc_attr( $title );
	if ( $src === '' ) {
		return '<p>This form is not available right now.</p>';
	}
	if ( emerson_is_apple_touch_ua() ) {
		/* No iframe in the document — JS opens data-emerson-form-url in a new tab. */
		return '';
	}
	return '<div class="emerson-recommit__fit" data-emerson-frame-fit>'
		. '<p class="emerson-recommit__loading" hidden>Loading form…</p>'
		. '<iframe class="emerson-recommit__frame" title="' . $title . '" data-src="' . $src . '"></iframe>'
		. '</div>';
}

function emerson_modal_display_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_modal_display_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_modal_display_save' );
	update_option(
		EMERSON_MODAL_OVERFLOW_ARROW_OPTION,
		empty( $_POST['modal_overflow_arrow'] ) ? '0' : '1',
		false
	);
	return 'Saved. Display settings apply to every Emerson form dialog.';
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! wp_script_is( 'emerson-chapel', 'enqueued' ) ) {
			return;
		}
		wp_localize_script(
			'emerson-chapel',
			'emersonModals',
			array(
				'overflowArrow' => emerson_modal_overflow_arrow_enabled(),
			)
		);
	},
	30
);

/**
 * Button look variants shared with pledge (green), PayPal (blue), and survey (outline).
 *
 * @return list<string>
 */
function emerson_cta_styles(): array {
	return array( 'green', 'blue', 'outline' );
}

function emerson_cta_normalize_style( string $style ): string {
	$style = sanitize_key( $style );
	return in_array( $style, emerson_cta_styles(), true ) ? $style : 'blue';
}

/**
 * Add/replace emerson-cta--{style} on the wp-block-button wrapper that carries $marker_class.
 */
function emerson_cta_apply_style_to_wrapper( string $content, string $marker_class, string $style ): string {
	$marker_class = trim( $marker_class );
	$style        = emerson_cta_normalize_style( $style );
	if ( $marker_class === '' || $content === '' ) {
		return $content;
	}
	$updated = preg_replace_callback(
		'/(<div\b[^>]*\bclass=")([^"]+)(")/',
		static function ( array $m ) use ( $marker_class, $style ): string {
			$tokens = preg_split( '/\s+/', trim( $m[2] ) ) ?: array();
			if ( ! in_array( $marker_class, $tokens, true ) ) {
				return $m[0];
			}
			$classes = $m[2];
			if ( ! in_array( 'emerson-cta', $tokens, true ) ) {
				$classes .= ' emerson-cta';
			}
			if ( preg_match( '/\bemerson-cta--(?:green|blue|outline)\b/', $classes ) ) {
				$classes = (string) preg_replace( '/\bemerson-cta--(?:green|blue|outline)\b/', 'emerson-cta--' . $style, $classes );
			} else {
				$classes .= ' emerson-cta--' . $style;
			}
			return $m[1] . $classes . $m[3];
		},
		$content
	);
	return is_string( $updated ) ? $updated : $content;
}

/**
 * Admin + editor labels for the three CTA looks.
 *
 * @return array<string, string>
 */
function emerson_cta_style_labels(): array {
	return array(
		'green'   => 'Green fill (like Commitment Form)',
		'blue'    => 'Blue fill (like PayPal fees)',
		'outline' => 'Blue outline (like Community Survey)',
	);
}

/**
 * Whether this page has the home-style Buttons row (pledge and/or PayPal).
 */
/**
 * Sanitize a Gutenberg Additional CSS class for a built-in CTA Button.
 */
function emerson_sanitize_cta_class( string $class ): string {
	$class = strtolower( trim( $class ) );
	$class = preg_replace( '/[^a-z0-9-]+/', '-', $class ) ?? '';
	$class = trim( $class, '-' );
	if ( $class === '' || strlen( $class ) > 40 ) {
		return '';
	}
	/* Double hyphen breaks Gutenberg block comments. */
	if ( str_contains( $class, '--' ) ) {
		return '';
	}
	return $class;
}

/**
 * Unique list helper.
 *
 * @param list<string> ...$groups
 * @return list<string>
 */
function emerson_cta_unique_classes( array ...$groups ): array {
	$out = array();
	foreach ( $groups as $group ) {
		foreach ( $group as $class ) {
			$class = emerson_sanitize_cta_class( (string) $class );
			if ( $class !== '' && ! in_array( $class, $out, true ) ) {
				$out[] = $class;
			}
		}
	}
	return $out;
}

function emerson_recommitment_button_class(): string {
	$stored = emerson_sanitize_cta_class( (string) get_option( EMERSON_PLEDGE_CLASS_OPTION, '' ) );
	if ( $stored === '' || str_starts_with( $stored, 'emerson-modal-' ) ) {
		return EMERSON_PLEDGE_CLASS_DEFAULT;
	}
	return $stored;
}

function emerson_annual_pledge_button_class(): string {
	$stored = emerson_sanitize_cta_class( (string) get_option( EMERSON_ANNUAL_PLEDGE_CLASS_OPTION, '' ) );
	if ( $stored === '' || str_starts_with( $stored, 'emerson-modal-' ) ) {
		return EMERSON_ANNUAL_PLEDGE_CLASS_DEFAULT;
	}
	return $stored;
}

function emerson_paypal_fees_button_class(): string {
	$stored = emerson_sanitize_cta_class( (string) get_option( EMERSON_PAYPAL_CLASS_OPTION, '' ) );
	if ( $stored === '' || str_starts_with( $stored, 'emerson-modal-' ) ) {
		return EMERSON_PAYPAL_CLASS_DEFAULT;
	}
	return $stored;
}

/**
 * Gutenberg classes for the recommitment opener (configured + legacy aliases).
 *
 * @return list<string>
 */
function emerson_recommitment_button_classes(): array {
	return emerson_cta_unique_classes(
		array( emerson_recommitment_button_class() ),
		array( 'recommitment-form', 'pledge-button' )
	);
}

/**
 * Gutenberg classes for the annual Google pledge opener.
 *
 * @return list<string>
 */
function emerson_annual_pledge_button_classes(): array {
	return emerson_cta_unique_classes(
		array( emerson_annual_pledge_button_class() ),
		array( 'pledge-card', 'pledge-form' )
	);
}

/**
 * Gutenberg classes for the PayPal fees opener.
 *
 * @return list<string>
 */
function emerson_paypal_fees_button_classes(): array {
	return emerson_cta_unique_classes(
		array( emerson_paypal_fees_button_class() ),
		array( 'paypal-fees-button' )
	);
}

/**
 * Gutenberg CSS classes that mark a Buttons row as the home CTA strip.
 *
 * @return list<string>
 */
function emerson_cta_button_classes(): array {
	return emerson_cta_unique_classes(
		emerson_recommitment_button_classes(),
		emerson_annual_pledge_button_classes(),
		emerson_paypal_fees_button_classes()
	);
}

/**
 * Whether content marks the annual pledge opener.
 */
function emerson_annual_pledge_button_marked( string $content ): bool {
	foreach ( emerson_annual_pledge_button_classes() as $class ) {
		if ( emerson_content_has_css_class( $content, $class ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Additional CSS classes on Gutenberg Button blocks in post content.
 *
 * @return list<string>
 */
function emerson_collect_gutenberg_button_classes( string $content ): array {
	$found = array();
	if ( preg_match_all( '/<!--\s*wp:button\s+(\{.*?\})\s+-->/s', $content, $matches ) ) {
		foreach ( $matches[1] as $json ) {
			$attrs = json_decode( (string) $json, true );
			if ( ! is_array( $attrs ) ) {
				continue;
			}
			$raw = (string) ( $attrs['className'] ?? '' );
			$raw = str_replace( array( '\\u002d', '\u002d' ), '-', $raw );
			foreach ( preg_split( '/\s+/', trim( $raw ) ) ?: array() as $token ) {
				$token = emerson_sanitize_cta_class( $token );
				if ( $token !== '' ) {
					$found[] = $token;
				}
			}
		}
	}
	if ( preg_match_all( '/<div\b[^>]*\bclass="([^"]*\bwp-block-button\b[^"]*)"/', $content, $matches ) ) {
		foreach ( $matches[1] as $list ) {
			foreach ( preg_split( '/\s+/', trim( $list ) ) ?: array() as $token ) {
				if ( $token === 'wp-block-button' || str_starts_with( $token, 'wp-block-button__' ) ) {
					continue;
				}
				$token = emerson_sanitize_cta_class( $token );
				if ( $token !== '' ) {
					$found[] = $token;
				}
			}
		}
	}
	return array_values( array_unique( $found ) );
}

/**
 * Replace a whole CSS class token in page/post/pattern content.
 *
 * @return int Number of posts updated.
 */
function emerson_rewrite_css_class_in_posts( string $old_class, string $new_class ): int {
	$old_class = emerson_sanitize_cta_class( $old_class );
	$new_class = emerson_sanitize_cta_class( $new_class );
	if ( $old_class === '' || $new_class === '' || $old_class === $new_class ) {
		return 0;
	}
	/* Built-in CTA renames must never steal or overwrite custom modal classes. */
	if ( str_starts_with( $old_class, 'emerson-modal-' ) || str_starts_with( $new_class, 'emerson-modal-' ) ) {
		return 0;
	}
	$GLOBALS['emerson_cta_class_rewriting'] = true;
	$updated = 0;
	$query   = new WP_Query(
		array(
			'post_type'              => array( 'page', 'post', 'wp_block' ),
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $query->posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
		$content = (string) $post->post_content;
		if ( ! emerson_content_has_css_class( $content, $old_class ) ) {
			continue;
		}
		$next = preg_replace_callback(
			'/(className"\s*:\s*|class=")([^"]*)(")/',
			static function ( array $m ) use ( $old_class, $new_class ): string {
				$tokens = preg_split( '/\s+/', trim( $m[2] ) ) ?: array();
				$changed = false;
				foreach ( $tokens as $i => $token ) {
					$token = str_replace( array( '\\u002d', '\u002d' ), '-', $token );
					if ( $token === $old_class ) {
						$tokens[ $i ] = $new_class;
						$changed      = true;
					}
				}
				if ( ! $changed ) {
					return $m[0];
				}
				return $m[1] . implode( ' ', $tokens ) . $m[3];
			},
			$content
		);
		if ( ! is_string( $next ) || $next === $content ) {
			continue;
		}
		/* Keep wp:button {"className"} and the wrapper div class list aligned —
		 * Gutenberg marks the block invalid when those disagree. */
		$next = emerson_repair_button_block_classnames( $next );
		if ( $next === $content ) {
			continue;
		}
		$r = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $next,
			),
			true
		);
		if ( ! is_wp_error( $r ) ) {
			++$updated;
		}
	}
	wp_reset_postdata();
	$GLOBALS['emerson_cta_class_rewriting'] = false;
	return $updated;
}

/**
 * Extra CSS classes on a core/button wrapper (everything except wp-block-button*).
 *
 * @return list<string>
 */
function emerson_button_extra_classes_from_html( string $html ): array {
	if ( ! preg_match( '/\bclass="([^"]*\bwp-block-button\b[^"]*)"/', $html, $m ) ) {
		return array();
	}
	$extra = array();
	foreach ( preg_split( '/\s+/', trim( $m[1] ) ) ?: array() as $token ) {
		if ( $token === '' || $token === 'wp-block-button' || str_starts_with( $token, 'wp-block-button' ) ) {
			continue;
		}
		$extra[] = $token;
	}
	return $extra;
}

/**
 * Set the wrapper class list on a core/button inner HTML string.
 */
function emerson_button_html_with_classnames( string $html, string $class_name ): string {
	$class_name = trim( preg_replace( '/\s+/', ' ', $class_name ) ?? '' );
	$wrapper    = 'wp-block-button' . ( $class_name !== '' ? ' ' . $class_name : '' );
	$next       = preg_replace( '/\bclass="[^"]*\bwp-block-button\b[^"]*"/', 'class="' . esc_attr( $wrapper ) . '"', $html, 1 );
	return is_string( $next ) ? $next : $html;
}

/**
 * @param list<array<string, mixed>> $blocks
 * @return list<array<string, mixed>>
 */
function emerson_repair_button_blocks_tree( array $blocks, bool &$changed ): array {
	foreach ( $blocks as &$block ) {
		if ( ( $block['blockName'] ?? '' ) === 'core/button' ) {
			$html  = (string) ( $block['innerHTML'] ?? '' );
			$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
			$have  = str_replace(
				array( '\\u002d', '\u002d' ),
				'-',
				trim( (string) ( $attrs['className'] ?? '' ) )
			);
			$from_html = implode( ' ', emerson_button_extra_classes_from_html( $html ) );
			if ( $have === '' && $from_html !== '' ) {
				$attrs['className'] = $from_html;
				$block['attrs']     = $attrs;
				$changed            = true;
			} elseif ( $have !== '' && $from_html !== $have ) {
				$html2 = emerson_button_html_with_classnames( $html, $have );
				if ( $html2 !== $html ) {
					$block['innerHTML'] = $html2;
					if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
						foreach ( $block['innerContent'] as $i => $piece ) {
							if ( is_string( $piece ) ) {
								$block['innerContent'][ $i ] = emerson_button_html_with_classnames( $piece, $have );
							}
						}
					}
					$changed = true;
				}
			}
		}
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = emerson_repair_button_blocks_tree( $block['innerBlocks'], $changed );
		}
	}
	unset( $block );
	return $blocks;
}

/**
 * Keep wp:button attrs.className in sync with the wrapper div (parse_blocks — safe with nested JSON).
 * Gutenberg invalidates the block when those disagree (often after “Attempt recovery”).
 */
function emerson_repair_button_block_classnames( string $content ): string {
	if ( $content === '' || ! str_contains( $content, 'wp:button' ) || ! function_exists( 'parse_blocks' ) ) {
		return $content;
	}
	$changed = false;
	$blocks  = emerson_repair_button_blocks_tree( parse_blocks( $content ), $changed );
	if ( ! $changed ) {
		return $content;
	}
	return serialize_blocks( $blocks );
}

/**
 * One-time + on save: repair Button blocks whose class lives only on the div.
 */
function emerson_migrate_button_classname_sync(): void {
	static $done = false;
	if ( $done || get_option( 'emerson_button_classname_sync_v2', '' ) === '1' ) {
		return;
	}
	$done  = true;
	$query = new WP_Query(
		array(
			'post_type'              => array( 'page', 'post', 'wp_block' ),
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$GLOBALS['emerson_cta_class_rewriting'] = true;
	foreach ( $query->posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
		$content = (string) $post->post_content;
		$next    = emerson_repair_button_block_classnames( $content );
		if ( $next === $content ) {
			continue;
		}
		wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $next,
			)
		);
	}
	wp_reset_postdata();
	$GLOBALS['emerson_cta_class_rewriting'] = false;
	update_option( 'emerson_button_classname_sync_v2', '1', false );
}
add_action( 'admin_init', 'emerson_migrate_button_classname_sync', 6 );
add_action( 'init', 'emerson_migrate_button_classname_sync', 6 );

add_filter(
	'content_save_pre',
	static function ( $content ) {
		if ( ! is_string( $content ) ) {
			return $content;
		}
		return emerson_repair_button_block_classnames( $content );
	},
	5
);

/**
 * If a built-in CTA class was renamed only in Gutenberg, adopt that class in Modals.
 */
function emerson_learn_cta_classes_from_saved_post( int $post_id, WP_Post $post ): void {
	if ( ! empty( $GLOBALS['emerson_cta_class_rewriting'] ) ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'page', 'post', 'wp_block' ), true ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$present = emerson_collect_gutenberg_button_classes( (string) $post->post_content );
	if ( $present === array() ) {
		return;
	}
	$slots = array(
		array(
			'option'   => EMERSON_PLEDGE_CLASS_OPTION,
			'current'  => emerson_recommitment_button_class(),
			'claimed'  => emerson_recommitment_button_classes(),
		),
		array(
			'option'   => EMERSON_ANNUAL_PLEDGE_CLASS_OPTION,
			'current'  => emerson_annual_pledge_button_class(),
			'claimed'  => emerson_annual_pledge_button_classes(),
		),
		array(
			'option'   => EMERSON_PAYPAL_CLASS_OPTION,
			'current'  => emerson_paypal_fees_button_class(),
			'claimed'  => emerson_paypal_fees_button_classes(),
		),
	);
	$custom = array();
	foreach ( emerson_custom_modals() as $modal ) {
		foreach ( emerson_custom_modal_button_classes( (string) ( $modal['id'] ?? '' ) ) as $class ) {
			$custom[] = $class;
		}
	}
	$all_claimed = emerson_cta_unique_classes(
		emerson_recommitment_button_classes(),
		emerson_annual_pledge_button_classes(),
		emerson_paypal_fees_button_classes(),
		$custom
	);
	$known_on_page = array_values( array_intersect( $present, $all_claimed ) );
	if ( $known_on_page === array() && $custom === array() ) {
		/* Lone unknown button — too ambiguous to learn. */
		return;
	}
	foreach ( $slots as $slot ) {
		if ( array_intersect( $present, $slot['claimed'] ) !== array() ) {
			continue;
		}
		$others = array_values( array_diff( $all_claimed, $slot['claimed'] ) );
		$has_sibling = array_intersect( $present, $others ) !== array()
			|| array_intersect( $present, $custom ) !== array();
		if ( ! $has_sibling ) {
			continue;
		}
		$candidates = array_values( array_diff( $present, $others, $custom ) );
		if ( count( $candidates ) !== 1 ) {
			continue;
		}
		$new = emerson_sanitize_cta_class( $candidates[0] );
		if ( $new === '' || $new === $slot['current'] ) {
			continue;
		}
		/* Never adopt a custom-modal class as PayPal / pledge / recommitment. */
		if ( str_starts_with( $new, 'emerson-modal-' ) || in_array( $new, $custom, true ) ) {
			continue;
		}
		update_option( $slot['option'], $new, false );
	}
}
add_action( 'save_post', 'emerson_learn_cta_classes_from_saved_post', 30, 2 );

/**
 * True when $class appears as a whole CSS class token (not a substring of another class).
 * Hyphens are not word boundaries — \b would let pledge-form match
 * emerson-modal-2026-2027-pledge-form and steal the custom-modal Button.
 */
function emerson_content_has_css_class( string $html, string $class ): bool {
	$class = trim( $class );
	if ( $class === '' || $html === '' ) {
		return false;
	}
	$lists = array();
	if ( preg_match_all( '/"className"\s*:\s*"([^"]*)"/', $html, $matches ) ) {
		foreach ( $matches[1] as $raw ) {
			/* Block comments escape "--" as \u002d\u002d. */
			$lists[] = str_replace( array( '\\u002d', '\u002d' ), '-', $raw );
		}
	}
	if ( preg_match_all( '/\bclass=(["\'])([^"\']*)\1/', $html, $matches ) ) {
		foreach ( $matches[2] as $raw ) {
			$lists[] = $raw;
		}
	}
	foreach ( $lists as $list ) {
		$tokens = preg_split( '/\s+/', trim( $list ) ) ?: array();
		if ( in_array( $class, $tokens, true ) ) {
			return true;
		}
	}
	return false;
}

function emerson_page_has_cta_buttons_row(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) ) {
		return false;
	}
	$content = (string) $post->post_content;
	foreach ( emerson_cta_button_classes() as $class ) {
		if ( emerson_content_has_css_class( $content, $class ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Rewrite Button block link text for a given CSS class across pages/posts/patterns.
 * Modals options are the source of truth on the front; this keeps the editor label in sync.
 *
 * @return int Number of posts updated.
 */
function emerson_sync_button_label_for_class( string $class, string $label ): int {
	$class = trim( $class );
	$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	if ( $class === '' || $label === '' ) {
		return 0;
	}
	$safe    = esc_html( $label );
	$updated = 0;
	$query   = new WP_Query(
		array(
			'post_type'              => array( 'page', 'post', 'wp_block' ),
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$GLOBALS['emerson_cta_class_rewriting'] = true;
	foreach ( $query->posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
		$content = (string) $post->post_content;
		if ( ! emerson_content_has_css_class( $content, $class ) ) {
			continue;
		}
		/* Token-safe: do not let pledge-form match inside emerson-modal-…-pledge-form. */
		$next = preg_replace_callback(
			'#(<!--\s*wp:button\b[^>]*-->\s*<div class=")([^"]+)("[^>]*>\s*<a\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)(.*?)(</a>)#si',
			static function ( array $m ) use ( $class, $safe ): string {
				$tokens = preg_split( '/\s+/', trim( $m[2] ) ) ?: array();
				if ( ! in_array( $class, $tokens, true ) ) {
					return $m[0];
				}
				return $m[1] . $m[2] . $m[3] . $safe . $m[5];
			},
			$content
		);
		if ( ! is_string( $next ) || $next === $content ) {
			continue;
		}
		$r = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $next,
			),
			true
		);
		if ( ! is_wp_error( $r ) ) {
			++$updated;
		}
	}
	wp_reset_postdata();
	$GLOBALS['emerson_cta_class_rewriting'] = false;
	return $updated;
}

/**
 * Buttons that show this dialog’s label but still carry a built-in CTA class
 * (usually from duplicating PayPal/pledge) are re-pointed to emerson-modal-{id}.
 *
 * @return int Number of posts updated.
 */
function emerson_heal_custom_modal_button_classes( string $modal_id, string $label ): int {
	$modal_id  = sanitize_key( $modal_id );
	$label     = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	$new_class = emerson_custom_modal_button_class( $modal_id );
	if ( $modal_id === '' || $label === '' || $new_class === '' ) {
		return 0;
	}
	$built_in = emerson_cta_button_classes();
	$safe     = esc_html( $label );
	$updated  = 0;
	$query    = new WP_Query(
		array(
			'post_type'              => array( 'page', 'post', 'wp_block' ),
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$GLOBALS['emerson_cta_class_rewriting'] = true;
	foreach ( $query->posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
		$content = (string) $post->post_content;
		if ( ! str_contains( $content, 'wp:button' ) ) {
			continue;
		}
		$next = preg_replace_callback(
			'#(<!--\s*wp:button\s+)(\{.*?\})(\s+-->\s*<div class=")([^"]+)("[^>]*>\s*<a\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>)(.*?)(</a>)#si',
			static function ( array $m ) use ( $built_in, $new_class, $safe, $label ): string {
				$text = trim( html_entity_decode( wp_strip_all_tags( $m[6] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				if ( $text !== $label ) {
					return $m[0];
				}
				$tokens = preg_split( '/\s+/', trim( $m[4] ) ) ?: array();
				if ( in_array( $new_class, $tokens, true ) ) {
					return $m[0];
				}
				$stolen = array_values( array_intersect( $tokens, $built_in ) );
				if ( $stolen === array() ) {
					return $m[0];
				}
				$tokens = array_values(
					array_filter(
						$tokens,
						static fn( string $t ): bool => ! in_array( $t, $stolen, true )
					)
				);
				$tokens[] = $new_class;
				$class_list = implode( ' ', $tokens );
				$attrs      = json_decode( $m[2], true );
				if ( ! is_array( $attrs ) ) {
					$attrs = array();
				}
				$attrs['className'] = $new_class;
				$json = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( ! is_string( $json ) ) {
					return $m[0];
				}
				return $m[1] . $json . $m[3] . $class_list . $m[5] . $safe . $m[7];
			},
			$content
		);
		if ( ! is_string( $next ) || $next === $content ) {
			continue;
		}
		$next = emerson_repair_button_block_classnames( $next );
		$r    = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $next,
			),
			true
		);
		if ( ! is_wp_error( $r ) ) {
			++$updated;
		}
	}
	wp_reset_postdata();
	$GLOBALS['emerson_cta_class_rewriting'] = false;
	return $updated;
}

/**
 * Replace a custom modal id (CSS class + shortcode) in page content.
 *
 * @return int Number of posts updated.
 */
function emerson_rewrite_custom_modal_id_in_posts( string $old_id, string $new_id ): int {
	$old_id = sanitize_key( $old_id );
	$new_id = sanitize_key( $new_id );
	if ( $old_id === '' || $new_id === '' || $old_id === $new_id ) {
		return 0;
	}
	$new_class = emerson_custom_modal_button_class( $new_id );
	$updated   = 0;
	$GLOBALS['emerson_cta_class_rewriting'] = true;
	$query     = new WP_Query(
		array(
			'post_type'              => array( 'page', 'post', 'wp_block' ),
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $query->posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
		$content = (string) $post->post_content;
		$next    = $content;
		foreach ( emerson_custom_modal_button_classes( $old_id ) as $old_class ) {
			$next = preg_replace_callback(
				'/(className"\s*:\s*|class=")([^"]*)(")/',
				static function ( array $m ) use ( $old_class, $new_class ): string {
					$tokens  = preg_split( '/\s+/', trim( $m[2] ) ) ?: array();
					$changed = false;
					foreach ( $tokens as $i => $token ) {
						$token = str_replace( array( '\\u002d', '\u002d' ), '-', $token );
						if ( $token === $old_class ) {
							$tokens[ $i ] = $new_class;
							$changed      = true;
						}
					}
					return $changed ? $m[1] . implode( ' ', $tokens ) . $m[3] : $m[0];
				},
				$next
			) ?? $next;
		}
		/* Block-comment escapes for legacy "--" in className. */
		$next = str_replace(
			'emerson-modal\\u002d\\u002d' . $old_id,
			'emerson-modal-' . $new_id,
			$next
		);
		$next = emerson_repair_button_block_classnames( is_string( $next ) ? $next : $content );
		$next = str_replace(
			array(
				'[emerson_modal id="' . $old_id . '"]',
				"[emerson_modal id='" . $old_id . "']",
			),
			array(
				'[emerson_modal id="' . $new_id . '"]',
				"[emerson_modal id='" . $new_id . "']",
			),
			$next
		);
		/* Attribute form without quotes / with other attrs. */
		$next = preg_replace(
			'/(\[emerson_modal\b[^\]]*?\bid=)["\']?' . preg_quote( $old_id, '/' ) . '["\']?/',
			'${1}"' . $new_id . '"',
			$next
		);
		if ( ! is_string( $next ) || $next === $content ) {
			continue;
		}
		$r = wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $next,
			),
			true
		);
		if ( ! is_wp_error( $r ) ) {
			++$updated;
		}
	}
	wp_reset_postdata();
	$GLOBALS['emerson_cta_class_rewriting'] = false;
	return $updated;
}

/**
 * Turn a Google Forms /edit (or messy) URL into a public /viewform address.
 */
function emerson_google_forms_public_url( string $url ): string {
	$url = trim( $url );
	if ( $url === '' ) {
		return '';
	}
	if ( preg_match( '#^(https://docs\.google\.com/forms/d/[a-zA-Z0-9_-]+)/(?:edit|viewform)(?:[/?#].*)?$#i', $url, $m ) ) {
		return $m[1] . '/viewform';
	}
	return $url;
}

/**
 * @deprecated Injection made the front show buttons that were not in Gutenberg.
 * Custom modals are placed only with class emerson-modal-{id} (or a Shortcode block).
 */
function emerson_cta_will_inject_into_buttons(): bool {
	return false;
}

/**
 * @return list<array{id:string,label:string,heading:string,source:string,url:string,cff_id:int,style:string}>
 */
function emerson_custom_modals(): array {
	$stored = get_option( EMERSON_CUSTOM_MODALS_OPTION, null );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$clean = array();
	foreach ( $stored as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$modal = emerson_custom_modal_normalize( $row );
		if ( $modal !== null ) {
			$clean[] = $modal;
		}
	}
	return $clean;
}

/**
 * @param array<string, mixed> $row
 * @return array{id:string,label:string,heading:string,source:string,url:string,cff_id:int,style:string}|null
 */
function emerson_custom_modal_normalize( array $row ): ?array {
	$id = sanitize_key( (string) ( $row['id'] ?? '' ) );
	if ( $id === '' || strlen( $id ) > 40 ) {
		return null;
	}
	$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
	$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	if ( $label === '' || mb_strlen( $label ) > 80 ) {
		return null;
	}
	$heading = sanitize_text_field( (string) ( $row['heading'] ?? '' ) );
	$heading = trim( preg_replace( '/\s+/', ' ', $heading ) ?? '' );
	if ( mb_strlen( $heading ) > 80 ) {
		$heading = mb_substr( $heading, 0, 80 );
	}
	$source = sanitize_key( (string) ( $row['source'] ?? 'url' ) );
	if ( ! in_array( $source, array( 'url', 'cff' ), true ) ) {
		$source = 'url';
	}
	$url    = '';
	$cff_id = 0;
	if ( 'cff' === $source ) {
		$cff_id = absint( $row['cff_id'] ?? 0 );
		if ( $cff_id <= 0 ) {
			return null;
		}
	} else {
		$raw = (string) ( $row['url'] ?? '' );
		$url = function_exists( 'emerson_recommit_clean_form_url' )
			? emerson_recommit_clean_form_url( $raw )
			: esc_url_raw( trim( $raw ) );
		if ( ! emerson_pledge_is_form_url( $url ) ) {
			return null;
		}
	}
	$style = emerson_cta_normalize_style( (string) ( $row['style'] ?? 'blue' ) );
	return array(
		'id'      => $id,
		'label'   => $label,
		'heading' => $heading,
		'source'  => $source,
		'url'     => $url,
		'cff_id'  => $cff_id,
		'style'   => $style,
	);
}

function emerson_custom_modal( string $id ): ?array {
	$id = sanitize_key( $id );
	foreach ( emerson_custom_modals() as $modal ) {
		if ( $modal['id'] === $id ) {
			return $modal;
		}
	}
	return null;
}

/**
 * @return array<int, string> id => form name
 */
function emerson_modal_cff_forms(): array {
	if ( ! defined( 'CP_CALCULATEDFIELDSF_FORMS_TABLE' ) ) {
		return array();
	}
	global $wpdb;
	$table = $wpdb->prefix . CP_CALCULATEDFIELDSF_FORMS_TABLE;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows  = $wpdb->get_results( "SELECT id, form_name FROM `{$table}` ORDER BY id ASC", ARRAY_A );
	$out   = array();
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $row ) {
		$out[ (int) $row['id'] ] = (string) $row['form_name'];
	}
	return $out;
}

/**
 * Resolve forms.gle → docs.google.com/…/viewform (cached). Short links force an
 * extra redirect inside the iframe and are slower / flakier than the embed URL.
 */
function emerson_resolve_forms_gle_url( string $url ): string {
	$url = trim( $url );
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || strtolower( (string) ( $parts['host'] ?? '' ) ) !== 'forms.gle' ) {
		return $url;
	}
	$key = 'emerson_gle_' . md5( $url );
	$cached = get_transient( $key );
	if ( is_string( $cached ) && $cached !== '' && emerson_pledge_is_form_url( $cached ) ) {
		return $cached;
	}
	$response = wp_remote_head(
		$url,
		array(
			'timeout'     => 5,
			'redirection' => 0,
			'user-agent'  => 'EmersonChapel/1.0; WordPress',
		)
	);
	$loc = '';
	if ( ! is_wp_error( $response ) ) {
		$loc = (string) wp_remote_retrieve_header( $response, 'location' );
	}
	if ( $loc === '' ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 0,
				'user-agent'  => 'EmersonChapel/1.0; WordPress',
			)
		);
		if ( ! is_wp_error( $response ) ) {
			$loc = (string) wp_remote_retrieve_header( $response, 'location' );
		}
	}
	if ( $loc === '' || ! emerson_pledge_is_form_url( $loc ) ) {
		return $url;
	}
	/* Drop usp=send_form tracking; keep a clean viewform address. */
	$loc = emerson_google_forms_public_url( $loc );
	$loc_parts = wp_parse_url( $loc );
	if ( is_array( $loc_parts ) && ! empty( $loc_parts['scheme'] ) && ! empty( $loc_parts['host'] ) && ! empty( $loc_parts['path'] ) ) {
		$loc = $loc_parts['scheme'] . '://' . $loc_parts['host'] . $loc_parts['path'];
	}
	set_transient( $key, $loc, WEEK_IN_SECONDS );
	return $loc;
}

/**
 * Canonical iframe src for a Microsoft/Google form URL.
 */
function emerson_external_form_embed_src( string $url ): string {
	$url = trim( $url );
	if ( $url === '' ) {
		return '';
	}
	$url = emerson_resolve_forms_gle_url( $url );
	$url = emerson_google_forms_public_url( $url );
	if ( ! emerson_pledge_is_form_url( $url ) ) {
		return '';
	}
	if ( function_exists( 'emerson_recommit_is_form_url' ) && emerson_recommit_is_form_url( $url ) ) {
		if ( preg_match( '/(?:^|[?&])embed=true(?:&|$)/i', $url ) ) {
			return $url;
		}
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . 'embed=true';
	}
	$parts = wp_parse_url( $url );
	$host  = strtolower( (string) ( $parts['host'] ?? '' ) );
	if ( 'docs.google.com' === $host && ! preg_match( '/(?:^|[?&])embedded=true(?:&|$)/i', $url ) ) {
		return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . 'embedded=true';
	}
	return $url;
}

function emerson_custom_modal_embed_url( array $modal ): string {
	if ( ( $modal['source'] ?? '' ) !== 'url' || ( $modal['url'] ?? '' ) === '' ) {
		return '';
	}
	return emerson_external_form_embed_src( (string) $modal['url'] );
}

function emerson_custom_modal_unique_id( string $label, string $exclude = '' ): string {
	$base = sanitize_title( $label );
	if ( $base === '' ) {
		$base = 'modal';
	}
	if ( strlen( $base ) > 36 ) {
		$base = substr( $base, 0, 36 );
	}
	$id    = $base;
	$n     = 2;
	$taken = array();
	foreach ( emerson_custom_modals() as $modal ) {
		$taken[ $modal['id'] ] = true;
	}
	while ( isset( $taken[ $id ] ) && $id !== $exclude ) {
		$id = $base . '-' . $n;
		++$n;
	}
	return sanitize_key( $id );
}

/**
 * @return list<string>
 */
function emerson_custom_modals_used_on_page(): array {
	static $used = null;
	if ( is_array( $used ) ) {
		return $used;
	}
	$used = array();
	if ( ! empty( $GLOBALS['emerson_modals_used'] ) && is_array( $GLOBALS['emerson_modals_used'] ) ) {
		foreach ( array_keys( $GLOBALS['emerson_modals_used'] ) as $id ) {
			$id = sanitize_key( (string) $id );
			if ( $id !== '' ) {
				$used[] = $id;
			}
		}
	}
	if ( is_singular() ) {
		$post = get_post();
		if ( $post instanceof WP_Post ) {
			$content = (string) $post->post_content;
			foreach ( emerson_custom_modal_ids_from_classes( $content ) as $id ) {
				$used[] = $id;
			}
			if ( has_shortcode( $content, 'emerson_modal' )
				&& preg_match_all( '/\[emerson_modal\b([^\]]*)\]/', $content, $matches )
			) {
				foreach ( $matches[1] as $atts ) {
					if ( preg_match( '/\bid=["\']?([a-z0-9_-]+)/i', $atts, $m ) ) {
						$used[] = sanitize_key( $m[1] );
					}
				}
			}
		}
	}
	$used = array_values( array_unique( array_filter( $used ) ) );
	return $used;
}

/**
 * Whether block HTML may be rewritten into a modal opener (front only).
 * Skip admin + REST so the editor keeps a normal <a> Button and stays valid.
 */
function emerson_modal_buttons_may_rewrite(): bool {
	if ( is_admin() ) {
		return false;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return false;
	}
	return true;
}

/**
 * Gutenberg-safe CSS class for a custom modal Button: emerson-modal-{id}.
 * Single hyphen only — a "--" in className is escaped as \u002d\u002d in block
 * comments and often marks the Button “invalid” in the editor.
 */
function emerson_custom_modal_button_class( string $id ): string {
	$id = sanitize_key( $id );
	return $id !== '' ? 'emerson-modal-' . $id : '';
}

/**
 * Legacy BEM class (emerson-modal--{id}) still recognized on the front.
 */
function emerson_custom_modal_button_class_legacy( string $id ): string {
	$id = sanitize_key( $id );
	return $id !== '' ? 'emerson-modal--' . $id : '';
}

/**
 * Current + legacy class names for one modal id.
 *
 * @return list<string>
 */
function emerson_custom_modal_button_classes( string $id ): array {
	$classes = array();
	$current = emerson_custom_modal_button_class( $id );
	$legacy  = emerson_custom_modal_button_class_legacy( $id );
	if ( $current !== '' ) {
		$classes[] = $current;
	}
	if ( $legacy !== '' && $legacy !== $current ) {
		$classes[] = $legacy;
	}
	return $classes;
}

/**
 * Modal ids referenced by emerson-modal-{id} (or legacy --) in HTML / post content.
 *
 * @return list<string>
 */
function emerson_custom_modal_ids_from_classes( string $html ): array {
	if ( $html === '' ) {
		return array();
	}
	$ids = array();
	foreach ( emerson_custom_modals() as $modal ) {
		foreach ( emerson_custom_modal_button_classes( $modal['id'] ) as $class ) {
			if ( emerson_content_has_css_class( $html, $class ) ) {
				$ids[] = $modal['id'];
				break;
			}
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * One-time: rewrite emerson-modal--{id} → emerson-modal-{id} in page content
 * so Gutenberg stops flagging those Buttons as invalid.
 */
function emerson_migrate_modal_double_hyphen_classes(): void {
	static $done = false;
	if ( $done || get_option( 'emerson_modal_class_single_hyphen', '' ) === '1' ) {
		return;
	}
	$done = true;
	$query = new WP_Query(
		array(
			'post_type'              => array( 'page', 'post', 'wp_block' ),
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => -1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	foreach ( $query->posts as $post ) {
		if ( ! ( $post instanceof WP_Post ) ) {
			continue;
		}
		$content = (string) $post->post_content;
		if ( ! str_contains( $content, 'emerson-modal--' ) && ! str_contains( $content, 'emerson-modal\\u002d\\u002d' ) ) {
			continue;
		}
		$next = str_replace(
			array( 'emerson-modal--', 'emerson-modal\\u002d\\u002d' ),
			array( 'emerson-modal-', 'emerson-modal-' ),
			$content
		);
		if ( $next === $content ) {
			continue;
		}
		wp_update_post(
			array(
				'ID'           => (int) $post->ID,
				'post_content' => $next,
			)
		);
	}
	wp_reset_postdata();
	update_option( 'emerson_modal_class_single_hyphen', '1', false );
}
add_action( 'admin_init', 'emerson_migrate_modal_double_hyphen_classes', 5 );
add_action( 'init', 'emerson_migrate_modal_double_hyphen_classes', 5 );

/**
 * Markup for one custom-modal opener (core button shape + shared CTA style class).
 *
 * @param array{id:string,label:string,style:string} $modal
 */
function emerson_custom_modal_button_html( array $modal ): string {
	$style = emerson_cta_normalize_style( (string) ( $modal['style'] ?? 'blue' ) );
	$class = emerson_custom_modal_button_class( $modal['id'] );
	return '<div class="wp-block-button emerson-modal-button ' . esc_attr( $class ) . ' emerson-cta emerson-cta--' . esc_attr( $style ) . '">'
		. '<button type="button" class="wp-block-button__link wp-element-button" data-emerson-modal="'
		. esc_attr( $modal['id'] ) . '">' . esc_html( $modal['label'] ) . '</button></div>';
}

function emerson_custom_modal_shortcode( $atts ): string {
	$atts = shortcode_atts( array( 'id' => '' ), $atts, 'emerson_modal' );
	$modal = emerson_custom_modal( (string) $atts['id'] );
	if ( $modal === null ) {
		return '';
	}
	if ( ! isset( $GLOBALS['emerson_modals_used'] ) || ! is_array( $GLOBALS['emerson_modals_used'] ) ) {
		$GLOBALS['emerson_modals_used'] = array();
	}
	$GLOBALS['emerson_modals_used'][ $modal['id'] ] = true;
	return '<div class="emerson-cta-standalone">' . emerson_custom_modal_button_html( $modal ) . '</div>';
}
add_shortcode( 'emerson_modal', 'emerson_custom_modal_shortcode' );

/**
 * Front-end: normal Gutenberg Button with class emerson-modal-{id} → modal opener.
 * Prefer this over pasting [emerson_modal] inside a Button block (that invalidates Gutenberg).
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_custom_modal_render_button( string $content, array $block ): string {
	if ( ! emerson_modal_buttons_may_rewrite() || ( $block['blockName'] ?? '' ) !== 'core/button' ) {
		return $content;
	}
	$ids = emerson_custom_modal_ids_from_classes( $content );
	if ( $ids === array() ) {
		return $content;
	}
	$modal = emerson_custom_modal( $ids[0] );
	if ( $modal === null ) {
		return $content;
	}
	if ( ! isset( $GLOBALS['emerson_modals_used'] ) || ! is_array( $GLOBALS['emerson_modals_used'] ) ) {
		$GLOBALS['emerson_modals_used'] = array();
	}
	$GLOBALS['emerson_modals_used'][ $modal['id'] ] = true;
	$style  = emerson_cta_normalize_style( (string) ( $modal['style'] ?? 'blue' ) );
	$class  = emerson_custom_modal_button_class( $modal['id'] );
	$label  = esc_html( $modal['label'] );
	$button = '<button type="button" class="wp-block-button__link wp-element-button" data-emerson-modal="'
		. esc_attr( $modal['id'] ) . '">' . $label . '</button>';
	$updated = preg_replace(
		'#<(a|button)\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>.*?</\1>#s',
		$button,
		$content,
		1
	);
	if ( ! is_string( $updated ) ) {
		return $content;
	}
	/* Same emerson-cta--{style} wrapper classes the shortcode button uses. */
	$match_classes = emerson_custom_modal_button_classes( $modal['id'] );
	foreach ( $match_classes as $match_class ) {
		$q       = preg_quote( $match_class, '/' );
		$replaced = preg_replace_callback(
			'/(<div\b[^>]*\bclass=")([^"]*\b' . $q . '\b[^"]*)(")/',
			static function ( array $m ) use ( $style, $class ): string {
				$classes = $m[2];
				/* Prefer the Gutenberg-safe single-hyphen class on the wrapper. */
				if ( $class !== '' && ! str_contains( $classes, $class ) ) {
					$classes .= ' ' . $class;
				}
				if ( ! str_contains( $classes, 'emerson-modal-button' ) ) {
					$classes .= ' emerson-modal-button';
				}
				if ( ! str_contains( $classes, 'emerson-cta' ) ) {
					$classes .= ' emerson-cta';
				}
				if ( preg_match( '/\bemerson-cta--(?:green|blue|outline)\b/', $classes ) ) {
					$classes = (string) preg_replace( '/\bemerson-cta--(?:green|blue|outline)\b/', 'emerson-cta--' . $style, $classes );
				} else {
					$classes .= ' emerson-cta--' . $style;
				}
				return $m[1] . $classes . $m[3];
			},
			$updated,
			1
		);
		if ( is_string( $replaced ) && $replaced !== $updated ) {
			$updated = $replaced;
			break;
		}
	}
	return is_string( $updated ) ? $updated : $content;
}
add_filter( 'render_block', 'emerson_custom_modal_render_button', 10, 2 );

/**
 * Block editor: paint CTA fills so a Button with emerson-modal-{id} matches the front.
 */
function emerson_custom_modal_editor_cta_styles(): void {
	wp_register_style( 'emerson-chapel-editor-cta', false, array(), null );
	wp_enqueue_style( 'emerson-chapel-editor-cta' );
	$fill = array(
		'green'   => 'border-radius:25px;background:var(--wp--preset--color--vivid-green-cyan,#00d084);color:#1e1e1e;',
		'blue'    => 'border-radius:25px;background:var(--emerson-blue,#3f4fa0);color:#fff;',
		'outline' => 'border:2px solid var(--emerson-blue,#3f4fa0);border-radius:25px;background:#fff;color:var(--emerson-blue,#3f4fa0);',
	);
	$css = ':root{--emerson-blue:#3f4fa0;}';
	foreach ( emerson_recommitment_button_classes() as $class ) {
		$css .= '.' . $class . ' .wp-block-button__link{' . $fill[ emerson_pledge_button_style() ] . '}';
	}
	foreach ( emerson_annual_pledge_button_classes() as $class ) {
		$css .= '.' . $class . ' .wp-block-button__link{' . $fill[ emerson_annual_pledge_button_style() ] . '}';
	}
	foreach ( emerson_paypal_fees_button_classes() as $class ) {
		$css .= '.' . $class . ' .wp-block-button__link{' . $fill['blue'] . '}';
	}
	foreach ( emerson_custom_modals() as $modal ) {
		$style = emerson_cta_normalize_style( (string) ( $modal['style'] ?? 'blue' ) );
		foreach ( emerson_custom_modal_button_classes( (string) ( $modal['id'] ?? '' ) ) as $class ) {
			$css .= '.' . $class . ' .wp-block-button__link{' . $fill[ $style ] . '}';
		}
	}
	wp_add_inline_style( 'emerson-chapel-editor-cta', $css );
}
add_action( 'enqueue_block_editor_assets', 'emerson_custom_modal_editor_cta_styles' );

/**
 * Former CTA-row injector — kept as a no-op so old hooks/docs do not fatally error.
 * Front buttons must match Gutenberg (class emerson-modal-{id} on a Button).
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_custom_modals_inject_into_buttons( string $content, array $block ): string {
	unset( $block );
	return $content;
}
add_filter( 'render_block', 'emerson_custom_modals_inject_into_buttons', 11, 2 );

function emerson_custom_modals_dialogs(): void {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;

	$ids = emerson_custom_modals_used_on_page();
	if ( $ids === array() ) {
		return;
	}
	$seen = array();
	foreach ( $ids as $id ) {
		if ( isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$modal = emerson_custom_modal( $id );
		if ( $modal === null ) {
			continue;
		}
		$dialog_id = 'emerson-modal-' . $modal['id'];
		$src       = ( 'cff' === $modal['source'] ) ? '' : emerson_custom_modal_embed_url( $modal );
		$apple_url = ( $src !== '' && emerson_is_apple_touch_ua() ) ? ' data-emerson-form-url="' . esc_url( $src ) . '"' : '';
		echo '<dialog id="' . esc_attr( $dialog_id ) . '" class="emerson-recommit emerson-modal" data-emerson-modal-id="' . esc_attr( $modal['id'] ) . '"' . $apple_url . '>';
		echo '<div class="emerson-recommit__bar">';
		if ( $modal['heading'] !== '' ) {
			echo '<h2>' . esc_html( $modal['heading'] ) . '</h2>';
		}
		echo emerson_modal_close_button( 'emerson-recommit__close' );
		echo '</div>';
		if ( 'cff' === $modal['source'] ) {
			echo '<div class="emerson-modal__form">';
			echo do_shortcode( '[CP_CALCULATED_FIELDS id="' . (int) $modal['cff_id'] . '"]' );
			echo '</div>';
		} else {
			echo emerson_modal_external_form_markup( $src, $modal['label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML.
		}
		echo '</dialog>';
	}
}
add_action( 'wp_footer', 'emerson_custom_modals_dialogs', 6 );

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( emerson_custom_modals_used_on_page() === array() ) {
			return;
		}
		if ( wp_script_is( 'emerson-recommit', 'enqueued' ) ) {
			return;
		}
		$path = emerson_chapel_path( 'assets/js/recommitment.js' );
		wp_enqueue_script(
			'emerson-recommit',
			emerson_chapel_url( 'assets/js/recommitment.js' ),
			array(),
			(string) filemtime( $path ),
			true
		);
	},
	25
);

function emerson_pledge_label(): string {
	$stored = sanitize_text_field( trim( (string) get_option( EMERSON_PLEDGE_LABEL_OPTION, '' ) ) );
	return $stored !== '' ? $stored : EMERSON_PLEDGE_LABEL_DEFAULT;
}

function emerson_pledge_form_url(): string {
	$stored = trim( (string) get_option( EMERSON_PLEDGE_URL_OPTION, '' ) );
	return $stored !== '' ? $stored : EMERSON_RECOMMIT_FORM_DEFAULT;
}

function emerson_pledge_is_form_url( string $url ): bool {
	if ( function_exists( 'emerson_recommit_is_form_url' ) && emerson_recommit_is_form_url( $url ) ) {
		return true;
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || empty( $parts['host'] ) ) {
		return false;
	}
	$host = strtolower( (string) $parts['host'] );
	$path = (string) ( $parts['path'] ?? '' );
	if ( 'docs.google.com' === $host && str_starts_with( $path, '/forms/' ) ) {
		return true;
	}
	return 'forms.gle' === $host && $path !== '' && $path !== '/';
}

function emerson_pledge_embed_url(): string {
	return emerson_external_form_embed_src( emerson_pledge_form_url() );
}

/**
 * Whether content marks the recommitment opener (configured class or legacy aliases).
 */
function emerson_recommitment_button_marked( string $content ): bool {
	foreach ( emerson_recommitment_button_classes() as $class ) {
		if ( emerson_content_has_css_class( $content, $class ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Whether content marks the PayPal fees opener.
 */
function emerson_paypal_fees_button_marked( string $content ): bool {
	foreach ( emerson_paypal_fees_button_classes() as $class ) {
		if ( emerson_content_has_css_class( $content, $class ) ) {
			return true;
		}
	}
	return str_contains( $content, 'data-emerson-paypal-fees' );
}

/**
 * The page can still hold an old link. The words and the dialog come from Modals.
 * Gutenberg class: recommitment-form (legacy: pledge-button).
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_pledge_render_button( string $content, array $block ): string {
	if ( ! emerson_modal_buttons_may_rewrite() || ( $block['blockName'] ?? '' ) !== 'core/button' ) {
		return $content;
	}
	if ( emerson_custom_modal_ids_from_classes( $content ) !== array() ) {
		return $content;
	}
	if ( ! emerson_recommitment_button_marked( $content ) ) {
		return $content;
	}
	$label   = esc_html( emerson_pledge_label() );
	$button  = '<button type="button" class="wp-block-button__link wp-element-button" data-emerson-pledge>' . $label . '</button>';
	$updated = preg_replace(
		'#<(a|button)\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>.*?</\1>#s',
		$button,
		$content,
		1
	);
	if ( ! is_string( $updated ) ) {
		return $content;
	}
	$style = emerson_pledge_button_style();
	foreach ( emerson_recommitment_button_classes() as $marker ) {
		if ( emerson_content_has_css_class( $updated, $marker ) ) {
			return emerson_cta_apply_style_to_wrapper( $updated, $marker, $style );
		}
	}
	return $updated;
}
add_filter( 'render_block', 'emerson_pledge_render_button', 10, 2 );

function emerson_pledge_page_has_button(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post instanceof WP_Post && emerson_recommitment_button_marked( (string) $post->post_content );
}

function emerson_pledge_dialog_heading(): string {
	return sanitize_text_field( trim( (string) get_option( EMERSON_PLEDGE_HEADING_OPTION, '' ) ) );
}

function emerson_pledge_button_style(): string {
	$stored = sanitize_text_field( trim( (string) get_option( EMERSON_PLEDGE_STYLE_OPTION, '' ) ) );
	return emerson_cta_normalize_style( $stored !== '' ? $stored : EMERSON_PLEDGE_STYLE_DEFAULT );
}

function emerson_pledge_dialog(): void {
	if ( ! emerson_pledge_page_has_button() ) {
		return;
	}
	$src       = emerson_pledge_embed_url();
	$label     = emerson_pledge_label();
	$heading   = emerson_pledge_dialog_heading();
	$iframe_title = $heading !== '' ? $heading : $label;
	$apple_url = ( $src !== '' && emerson_is_apple_touch_ua() ) ? ' data-emerson-form-url="' . esc_url( $src ) . '"' : '';
	?>
	<dialog id="emerson-pledge" class="emerson-recommit"<?php echo $apple_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
		<div class="emerson-recommit__bar">
			<?php if ( $heading !== '' ) : ?>
				<h2><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<?php echo emerson_modal_close_button( 'emerson-recommit__close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
		</div>
		<?php
		if ( $src !== '' ) {
			echo emerson_modal_external_form_markup( $src, $iframe_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML.
		} else {
			echo '<p>The recommitment form is not available right now.</p>';
		}
		?>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'emerson_pledge_dialog', 5 );

function emerson_annual_pledge_label(): string {
	$stored = sanitize_text_field( trim( (string) get_option( EMERSON_ANNUAL_PLEDGE_LABEL_OPTION, '' ) ) );
	return $stored !== '' ? $stored : EMERSON_ANNUAL_PLEDGE_LABEL_DEFAULT;
}

function emerson_annual_pledge_dialog_heading(): string {
	return sanitize_text_field( trim( (string) get_option( EMERSON_ANNUAL_PLEDGE_HEADING_OPTION, '' ) ) );
}

function emerson_annual_pledge_button_style(): string {
	$stored = sanitize_text_field( trim( (string) get_option( EMERSON_ANNUAL_PLEDGE_STYLE_OPTION, '' ) ) );
	return emerson_cta_normalize_style( $stored !== '' ? $stored : EMERSON_ANNUAL_PLEDGE_STYLE_DEFAULT );
}

function emerson_annual_pledge_form_url(): string {
	$stored = trim( (string) get_option( EMERSON_ANNUAL_PLEDGE_URL_OPTION, '' ) );
	if ( $stored === '' ) {
		$stored = EMERSON_ANNUAL_PLEDGE_URL_DEFAULT;
	}
	return emerson_google_forms_public_url( $stored );
}

function emerson_annual_pledge_embed_url(): string {
	return emerson_external_form_embed_src( emerson_annual_pledge_form_url() );
}

/**
 * Gutenberg class: pledge-card → annual Google pledge dialog (legacy: pledge-form).
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_annual_pledge_render_button( string $content, array $block ): string {
	if ( ! emerson_modal_buttons_may_rewrite() || ( $block['blockName'] ?? '' ) !== 'core/button' ) {
		return $content;
	}
	/* Custom emerson-modal-{id} Buttons must not be claimed by the built-in pledge class. */
	if ( emerson_custom_modal_ids_from_classes( $content ) !== array() ) {
		return $content;
	}
	if ( ! emerson_annual_pledge_button_marked( $content ) ) {
		return $content;
	}
	$label  = esc_html( emerson_annual_pledge_label() );
	$button = '<button type="button" class="wp-block-button__link wp-element-button" data-emerson-pledge-form>'
		. $label
		. '</button>';
	$updated = preg_replace(
		'#<(a|button)\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>.*?</\1>#s',
		$button,
		$content,
		1
	);
	if ( ! is_string( $updated ) ) {
		return $content;
	}
	$style = emerson_annual_pledge_button_style();
	foreach ( emerson_annual_pledge_button_classes() as $marker ) {
		if ( emerson_content_has_css_class( $updated, $marker ) ) {
			return emerson_cta_apply_style_to_wrapper( $updated, $marker, $style );
		}
	}
	return $updated;
}
add_filter( 'render_block', 'emerson_annual_pledge_render_button', 10, 2 );

function emerson_annual_pledge_page_has_button(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post instanceof WP_Post && emerson_annual_pledge_button_marked( (string) $post->post_content );
}

function emerson_annual_pledge_dialog(): void {
	if ( ! emerson_annual_pledge_page_has_button() ) {
		return;
	}
	$src          = emerson_annual_pledge_embed_url();
	$label        = emerson_annual_pledge_label();
	$heading      = emerson_annual_pledge_dialog_heading();
	$iframe_title = $heading !== '' ? $heading : $label;
	$apple_url    = ( $src !== '' && emerson_is_apple_touch_ua() ) ? ' data-emerson-form-url="' . esc_url( $src ) . '"' : '';
	?>
	<dialog id="emerson-pledge-form" class="emerson-recommit"<?php echo $apple_url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
		<div class="emerson-recommit__bar">
			<?php if ( $heading !== '' ) : ?>
				<h2><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<?php echo emerson_modal_close_button( 'emerson-recommit__close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
		</div>
		<?php
		if ( $src !== '' ) {
			echo emerson_modal_external_form_markup( $src, $iframe_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML.
		} else {
			echo '<p>The pledge form is not available right now.</p>';
		}
		?>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'emerson_annual_pledge_dialog', 5 );

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( wp_script_is( 'emerson-recommit', 'enqueued' ) ) {
			return;
		}
		if ( ! emerson_pledge_page_has_button() && ! emerson_annual_pledge_page_has_button() ) {
			return;
		}
		$path = emerson_chapel_path( 'assets/js/recommitment.js' );
		wp_enqueue_script(
			'emerson-recommit',
			emerson_chapel_url( 'assets/js/recommitment.js' ),
			array(),
			(string) filemtime( $path ),
			true
		);
	},
	20
);

function emerson_pledge_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_pledge_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_pledge_save' );
	$raw   = isset( $_POST['pledge_form_url'] ) ? wp_unslash( (string) $_POST['pledge_form_url'] ) : '';
	$url   = function_exists( 'emerson_recommit_clean_form_url' ) ? emerson_recommit_clean_form_url( $raw ) : esc_url_raw( trim( $raw ) );
	$url   = emerson_google_forms_public_url( $url );
	$label = isset( $_POST['pledge_button_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['pledge_button_label'] ) ) : '';
	$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	if ( $label === '' ) {
		return 'Type the words that should appear on the recommitment button.';
	}
	if ( mb_strlen( $label ) > 80 ) {
		return 'Keep the recommitment button text to 80 characters.';
	}
	if ( ! emerson_pledge_is_form_url( $url ) ) {
		return 'Paste a Microsoft Forms or Google Forms address.';
	}
	$heading = isset( $_POST['pledge_dialog_heading'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['pledge_dialog_heading'] ) ) : '';
	$heading = trim( preg_replace( '/\s+/', ' ', $heading ) ?? '' );
	if ( mb_strlen( $heading ) > 80 ) {
		return 'Keep the dialog heading to 80 characters.';
	}
	$style = emerson_cta_normalize_style( (string) ( wp_unslash( $_POST['pledge_button_style'] ?? EMERSON_PLEDGE_STYLE_DEFAULT ) ) );
	$class = emerson_sanitize_cta_class( (string) ( wp_unslash( $_POST['pledge_button_class'] ?? EMERSON_PLEDGE_CLASS_DEFAULT ) ) );
	if ( $class === '' ) {
		return 'Enter a CSS class for the recommitment button (letters, numbers, single hyphens).';
	}
	$old_class = emerson_recommitment_button_class();
	update_option( EMERSON_PLEDGE_LABEL_OPTION, $label, false );
	update_option( EMERSON_PLEDGE_URL_OPTION, $url, false );
	update_option( EMERSON_PLEDGE_HEADING_OPTION, $heading, false );
	update_option( EMERSON_PLEDGE_STYLE_OPTION, $style, false );
	update_option( EMERSON_PLEDGE_CLASS_OPTION, $class, false );
	$notes = array();
	if ( $old_class !== $class ) {
		$renamed = emerson_rewrite_css_class_in_posts( $old_class, $class );
		$notes[] = $renamed > 0
			? 'Renamed the CSS class on ' . $renamed . ' page(s).'
			: 'CSS class updated (no pages still used the old class).';
	}
	$synced = 0;
	foreach ( emerson_recommitment_button_classes() as $sync_class ) {
		$synced += emerson_sync_button_label_for_class( $sync_class, $label );
	}
	if ( $synced > 0 ) {
		$notes[] = 'Updated the button text on ' . $synced . ' page(s).';
	}
	return 'Saved. The recommitment button (class ' . $class . ') will use this wording, style, and form.'
		. ( $notes !== array() ? ' ' . implode( ' ', $notes ) : '' );
}

function emerson_annual_pledge_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_annual_pledge_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_annual_pledge_save' );
	$raw   = isset( $_POST['annual_pledge_form_url'] ) ? wp_unslash( (string) $_POST['annual_pledge_form_url'] ) : '';
	$url   = function_exists( 'emerson_recommit_clean_form_url' ) ? emerson_recommit_clean_form_url( $raw ) : esc_url_raw( trim( $raw ) );
	$url   = emerson_google_forms_public_url( $url );
	$label = isset( $_POST['annual_pledge_button_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['annual_pledge_button_label'] ) ) : '';
	$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	$heading = isset( $_POST['annual_pledge_dialog_heading'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['annual_pledge_dialog_heading'] ) ) : '';
	$heading = trim( preg_replace( '/\s+/', ' ', $heading ) ?? '' );
	if ( $label === '' ) {
		return 'Type the words that should appear on the pledge form button.';
	}
	if ( mb_strlen( $label ) > 80 || mb_strlen( $heading ) > 80 ) {
		return 'Keep the button text and dialog heading to 80 characters.';
	}
	if ( ! emerson_pledge_is_form_url( $url ) ) {
		return 'Paste a Google Forms or Microsoft Forms address.';
	}
	$style = emerson_cta_normalize_style( (string) ( wp_unslash( $_POST['annual_pledge_button_style'] ?? EMERSON_ANNUAL_PLEDGE_STYLE_DEFAULT ) ) );
	$class = emerson_sanitize_cta_class( (string) ( wp_unslash( $_POST['annual_pledge_button_class'] ?? EMERSON_ANNUAL_PLEDGE_CLASS_DEFAULT ) ) );
	if ( $class === '' ) {
		return 'Enter a CSS class for the pledge button (letters, numbers, single hyphens — no double hyphen).';
	}
	$old_class = emerson_annual_pledge_button_class();
	update_option( EMERSON_ANNUAL_PLEDGE_LABEL_OPTION, $label, false );
	update_option( EMERSON_ANNUAL_PLEDGE_URL_OPTION, $url, false );
	update_option( EMERSON_ANNUAL_PLEDGE_HEADING_OPTION, $heading, false );
	update_option( EMERSON_ANNUAL_PLEDGE_STYLE_OPTION, $style, false );
	update_option( EMERSON_ANNUAL_PLEDGE_CLASS_OPTION, $class, false );
	$notes = array();
	if ( $old_class !== $class ) {
		$renamed = emerson_rewrite_css_class_in_posts( $old_class, $class );
		$notes[] = $renamed > 0
			? 'Renamed the CSS class on ' . $renamed . ' page(s).'
			: 'CSS class updated (no pages still used the old class).';
	}
	$synced = 0;
	foreach ( emerson_annual_pledge_button_classes() as $sync_class ) {
		$synced += emerson_sync_button_label_for_class( $sync_class, $label );
	}
	if ( $synced > 0 ) {
		$notes[] = 'Updated the button text on ' . $synced . ' page(s).';
	}
	return 'Saved. The pledge button (class ' . $class . ') will use this wording, style, and form.'
		. ( $notes !== array() ? ' ' . implode( ' ', $notes ) : '' );
}

function emerson_paypal_button_label(): string {
	$stored = sanitize_text_field( trim( (string) get_option( EMERSON_PAYPAL_LABEL_OPTION, '' ) ) );
	return $stored !== '' ? $stored : EMERSON_PAYPAL_LABEL_DEFAULT;
}

function emerson_paypal_dialog_heading(): string {
	$stored = sanitize_text_field( trim( (string) get_option( EMERSON_PAYPAL_HEADING_OPTION, '' ) ) );
	return $stored !== '' ? $stored : EMERSON_PAYPAL_LABEL_DEFAULT;
}

/**
 * Front-end only: turn a normal Gutenberg Button (usually an <a>) that has
 * class paypal-fees-button into the modal opener. Do not put data-emerson-paypal-fees
 * in the editor — core “repairs” <button> markup back to a link.
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_paypal_render_button( string $content, array $block ): string {
	if ( ! emerson_modal_buttons_may_rewrite() || ( $block['blockName'] ?? '' ) !== 'core/button' ) {
		return $content;
	}
	/* Custom emerson-modal-{id} Buttons must not be claimed by PayPal. */
	if ( emerson_custom_modal_ids_from_classes( $content ) !== array() ) {
		return $content;
	}
	if ( ! emerson_paypal_fees_button_marked( $content ) ) {
		return $content;
	}
	$label  = esc_html( emerson_paypal_button_label() );
	$button = '<button type="button" class="wp-block-button__link wp-element-button" data-emerson-paypal-fees>'
		. $label
		. '</button>';
	$updated = preg_replace(
		'#<(a|button)\b[^>]*class="[^"]*wp-block-button__link[^"]*"[^>]*>.*?</\1>#s',
		$button,
		$content,
		1
	);
	if ( ! is_string( $updated ) ) {
		return $content;
	}
	foreach ( emerson_paypal_fees_button_classes() as $marker ) {
		if ( emerson_content_has_css_class( $updated, $marker ) ) {
			return emerson_cta_apply_style_to_wrapper( $updated, $marker, 'blue' );
		}
	}
	return $updated;
}
add_filter( 'render_block', 'emerson_paypal_render_button', 10, 2 );

function emerson_paypal_page_has_button(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post instanceof WP_Post && emerson_paypal_fees_button_marked( (string) $post->post_content );
}

/**
 * Lightweight PayPal fee math (same as CFF form 8): 1.99% + $0.49.
 * Used on iPhone/iPad so the homepage does not load Calculated Fields’ all.js
 * (and its 7837px mobile min-height), which hung Safari’s load spinner.
 */
function emerson_paypal_lite_markup(): string {
	ob_start();
	?>
	<div class="emerson-paypal-lite">
		<p class="emerson-paypal-lite__note">Charitable PayPal rate is about 1.99% plus $0.49 per transaction. This is only a guideline.</p>
		<p>
			<label for="emerson-paypal-lite-amount">Donation to go to Emerson</label>
			<input id="emerson-paypal-lite-amount" class="emerson-paypal-lite__amount" type="number" inputmode="decimal" min="0" step="0.01" value="50">
		</p>
		<p>PayPal fees: <strong class="emerson-paypal-lite__fees">$1.49</strong></p>
		<p>Total donation: <strong class="emerson-paypal-lite__total">$51.49</strong></p>
	</div>
	<?php
	return (string) ob_get_clean();
}

function emerson_paypal_dialog(): void {
	if ( ! emerson_paypal_page_has_button() ) {
		return;
	}
	$heading = emerson_paypal_dialog_heading();
	$lite    = emerson_is_apple_touch_ua();
	?>
	<dialog id="emerson-paypal-fees" class="emerson-paypal-fees<?php echo $lite ? ' emerson-paypal-fees--lite' : ''; ?>">
		<div class="emerson-recommit__bar">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<?php echo emerson_modal_close_button( 'emerson-paypal-fees__close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
		</div>
		<?php
		if ( $lite ) {
			echo emerson_paypal_lite_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML.
		} else {
			echo do_shortcode( '[CP_CALCULATED_FIELDS id="' . EMERSON_PAYPAL_FORM_ID . '"]' );
		}
		?>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'emerson_paypal_dialog', 5 );

/**
 * PayPal fees is live math only (no submit / email). CFF’s math captcha is a
 * Form Settings toggle, not a field in the builder — keep it off.
 */
add_filter(
	'cpcff_get_option',
	static function ( $value, $option, $form_id ) {
		if ( 'cv_enable_captcha' === $option && (int) $form_id === EMERSON_PAYPAL_FORM_ID ) {
			return 'false';
		}
		return $value;
	},
	10,
	3
);

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Modals',
			'Modals',
			'edit_pages',
			'emerson-modals',
			'emerson_modals_admin_page',
			'dashicons-feedback',
			60
		);
	}
);

function emerson_paypal_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_paypal_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_paypal_save' );
	$label   = isset( $_POST['paypal_button_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['paypal_button_label'] ) ) : '';
	$heading = isset( $_POST['paypal_heading'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['paypal_heading'] ) ) : '';
	$label   = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	$heading = trim( preg_replace( '/\s+/', ' ', $heading ) ?? '' );
	if ( $label === '' ) {
		return 'Type the words that should appear on the PayPal fees button.';
	}
	if ( $heading === '' ) {
		return 'Type the heading inside the PayPal fees dialog.';
	}
	if ( mb_strlen( $label ) > 80 || mb_strlen( $heading ) > 80 ) {
		return 'Keep the button and the heading to 80 characters.';
	}
	$class = emerson_sanitize_cta_class( (string) ( wp_unslash( $_POST['paypal_button_class'] ?? EMERSON_PAYPAL_CLASS_DEFAULT ) ) );
	if ( $class === '' ) {
		return 'Enter a CSS class for the PayPal fees button (letters, numbers, single hyphens).';
	}
	$old_class = emerson_paypal_fees_button_class();
	update_option( EMERSON_PAYPAL_LABEL_OPTION, $label, false );
	update_option( EMERSON_PAYPAL_HEADING_OPTION, $heading, false );
	update_option( EMERSON_PAYPAL_CLASS_OPTION, $class, false );
	$notes = array();
	if ( $old_class !== $class ) {
		$renamed = emerson_rewrite_css_class_in_posts( $old_class, $class );
		$notes[] = $renamed > 0
			? 'Renamed the CSS class on ' . $renamed . ' page(s).'
			: 'CSS class updated (no pages still used the old class).';
	}
	$synced = 0;
	foreach ( emerson_paypal_fees_button_classes() as $sync_class ) {
		$synced += emerson_sync_button_label_for_class( $sync_class, $label );
	}
	if ( $synced > 0 ) {
		$notes[] = 'Updated the button text on ' . $synced . ' page(s).';
	}
	return 'Saved. The PayPal fees button (class ' . $class . ') and heading will use this wording.'
		. ( $notes !== array() ? ' ' . implode( ' ', $notes ) : '' );
}

/**
 * Calculated Fields form editor screen for a form id, or empty when unavailable.
 */
function emerson_modal_cff_editor_url( int $form_id ): string {
	if ( $form_id <= 0 ) {
		return '';
	}
	return admin_url(
		'admin.php?page=cp_calculated_fields_form&cal=' . $form_id
		. '&_cpcff_nonce=' . wp_create_nonce( 'cff-form-settings' )
	);
}

function emerson_paypal_editor_url(): string {
	return emerson_modal_cff_editor_url( EMERSON_PAYPAL_FORM_ID );
}

/**
 * @return string|null
 */
function emerson_custom_modals_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return null;
	}
	if ( ! empty( $_POST['emerson_custom_modal_delete'] ) ) {
		check_admin_referer( 'emerson_custom_modal_delete' );
		$delete_id = sanitize_key( (string) ( wp_unslash( $_POST['modal_id'] ?? '' ) ) );
		$next      = array();
		foreach ( emerson_custom_modals() as $modal ) {
			if ( $modal['id'] !== $delete_id ) {
				$next[] = $modal;
			}
		}
		update_option( EMERSON_CUSTOM_MODALS_OPTION, $next, false );
		return 'Removed that dialog. Delete its shortcode from any pages that still use it.';
	}
	if ( empty( $_POST['emerson_custom_modal_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_custom_modal_save' );

	$editing = sanitize_key( (string) ( wp_unslash( $_POST['modal_id'] ?? '' ) ) );
	$label   = sanitize_text_field( (string) ( wp_unslash( $_POST['modal_label'] ?? '' ) ) );
	$label   = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	$heading = sanitize_text_field( (string) ( wp_unslash( $_POST['modal_heading'] ?? '' ) ) );
	$heading = trim( preg_replace( '/\s+/', ' ', $heading ) ?? '' );
	$source  = sanitize_key( (string) ( wp_unslash( $_POST['modal_source'] ?? 'url' ) ) );
	if ( ! in_array( $source, array( 'url', 'cff' ), true ) ) {
		$source = 'url';
	}
	if ( $label === '' ) {
		return 'Type the words that should appear on the button.';
	}
	if ( mb_strlen( $label ) > 80 || mb_strlen( $heading ) > 80 ) {
		return 'Keep the button text and heading to 80 characters.';
	}

	/* CSS class slug (without emerson-modal-). Editable when renaming. */
	$slug_raw = sanitize_title( (string) ( wp_unslash( $_POST['modal_css_slug'] ?? '' ) ) );
	$slug     = sanitize_key( $slug_raw );
	if ( $slug !== '' && strlen( $slug ) > 40 ) {
		$slug = substr( $slug, 0, 40 );
	}

	if ( $editing === '' ) {
		$new_id = $slug !== '' ? emerson_custom_modal_unique_id( $slug ) : emerson_custom_modal_unique_id( $label );
	} else {
		$new_id = $slug !== '' ? $slug : $editing;
		if ( $new_id !== $editing ) {
			foreach ( emerson_custom_modals() as $existing ) {
				if ( $existing['id'] === $new_id ) {
					return 'That CSS class is already used by another dialog. Choose a different slug.';
				}
			}
		}
	}

	$row = array(
		'id'      => $new_id,
		'label'   => $label,
		'heading' => $heading,
		'source'  => $source,
		'url'     => (string) ( wp_unslash( $_POST['modal_url'] ?? '' ) ),
		'cff_id'  => absint( $_POST['modal_cff_id'] ?? 0 ),
		'style'   => emerson_cta_normalize_style( (string) ( wp_unslash( $_POST['modal_style'] ?? 'blue' ) ) ),
	);
	$modal = emerson_custom_modal_normalize( $row );
	if ( $modal === null ) {
		if ( 'cff' === $source ) {
			return 'Choose a Calculated Fields form.';
		}
		return 'Paste a Microsoft Forms or Google Forms address.';
	}

	$next     = emerson_custom_modals();
	$replaced = false;
	foreach ( $next as $i => $existing ) {
		/* Match by the id we are editing (may rename). */
		if ( $editing !== '' && $existing['id'] === $editing ) {
			$next[ $i ] = $modal;
			$replaced   = true;
			break;
		}
		if ( $editing === '' && $existing['id'] === $modal['id'] ) {
			$next[ $i ] = $modal;
			$replaced   = true;
			break;
		}
	}
	if ( ! $replaced ) {
		$next[] = $modal;
	}
	update_option( EMERSON_CUSTOM_MODALS_OPTION, $next, false );

	$notes = array();
	if ( $editing !== '' && $editing !== $modal['id'] ) {
		$renamed = emerson_rewrite_custom_modal_id_in_posts( $editing, $modal['id'] );
		$notes[] = $renamed > 0
			? 'Renamed the CSS class on ' . $renamed . ' page(s).'
			: 'CSS class renamed in Modals (no pages still used the old class).';
	}
	$healed = emerson_heal_custom_modal_button_classes( $modal['id'], $label );
	$synced = 0;
	foreach ( emerson_custom_modal_button_classes( $modal['id'] ) as $sync_class ) {
		$synced += emerson_sync_button_label_for_class( $sync_class, $label );
	}
	if ( $healed > 0 ) {
		$notes[] = 'Fixed ' . $healed . ' page(s) where this button still had a PayPal/pledge CSS class.';
	}
	$notes[] = $synced > 0
		? 'Updated the button text on ' . $synced . ' page(s).'
		: ( $healed > 0
			? 'Button class is now <code>' . esc_html( emerson_custom_modal_button_class( $modal['id'] ) ) . '</code>.'
			: 'Add a Button with class <code>' . esc_html( emerson_custom_modal_button_class( $modal['id'] ) ) . '</code> on any page.' );

	return 'Saved. ' . implode( ' ', $notes );
}

function emerson_modals_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit the dialogs.' ) );
	}
	$notice = emerson_modal_display_admin_save()
		?? emerson_custom_modals_admin_save()
		?? emerson_pledge_admin_save()
		?? emerson_annual_pledge_admin_save()
		?? emerson_recommit_admin_save()
		?? emerson_paypal_admin_save();
	$custom  = emerson_custom_modals();
	$cff     = emerson_modal_cff_forms();
	$editing = isset( $_GET['edit'] ) ? sanitize_key( (string) wp_unslash( $_GET['edit'] ) ) : '';
	$edit_row = $editing !== '' ? emerson_custom_modal( $editing ) : null;

	echo '<div class="wrap"><h1>Modals</h1>';
	if ( $notice ) {
		$bad = ! str_starts_with( $notice, 'Saved.' ) && ! str_starts_with( $notice, 'Removed' );
		echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>Built-in dialogs keep their own settings below. <strong>Custom dialogs</strong> are added here: choose a Calculated Fields form or paste a Microsoft/Google form link, then place a normal Gutenberg Button with CSS class <code>emerson-modal-your-id</code> (one hyphen after <code>modal</code> — a double hyphen breaks the editor). Do not paste the shortcode inside a Button block.</p>';

	echo '<h2>Display</h2>';
	echo '<p>Shared cues for every Emerson dialog that wraps a Calculated Fields form (survey, PayPal fees, and custom form-maker dialogs).</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_modal_display_save' );
	echo '<table class="form-table" role="presentation"><tr><th scope="row">Overflow hint</th><td>';
	echo '<label for="emerson-modal-overflow-arrow">';
	echo '<input name="modal_overflow_arrow" id="emerson-modal-overflow-arrow" type="checkbox" value="1"'
		. checked( emerson_modal_overflow_arrow_enabled(), true, false ) . '> ';
	echo 'Show an animated scroll arrow when the form is taller than the dialog</label>';
	echo '<p class="description">When more of the form is below, the arrow sits over the bottom, points down, bounces three times, then disappears — or as soon as someone scrolls. It does not show if the form already fits, or if it is already scrolled to the end. Turned off when the visitor prefers reduced motion.</p>';
	echo '</td></tr></table>';
	echo '<p class="submit"><button type="submit" name="emerson_modal_display_save" value="1" class="button button-primary">Save display</button></p>';
	echo '</form>';

	echo '<style>
		.emerson-modals-custom{margin:1.25rem 0 2rem;padding:1rem 1rem 0;background:#fff;border:1px solid #c3c4c7;max-width:52rem;overflow-x:auto;}
		.emerson-modals-list{margin:1rem 0;border-collapse:collapse;width:100%;table-layout:fixed;}
		.emerson-modals-list th,.emerson-modals-list td{padding:0.5rem 0.6rem;border-bottom:1px solid #dcdcde;text-align:left;vertical-align:top;}
		.emerson-modals-list th:nth-child(1),.emerson-modals-list td:nth-child(1){width:18%;}
		.emerson-modals-list th:nth-child(2),.emerson-modals-list td:nth-child(2){width:10%;}
		.emerson-modals-list th:nth-child(3),.emerson-modals-list td:nth-child(3){width:26%;}
		.emerson-modals-list th:nth-child(4),.emerson-modals-list td:nth-child(4){width:34%;}
		.emerson-modals-list th:nth-child(5),.emerson-modals-list td:nth-child(5){width:12%;}
		.emerson-modals-list .emerson-modals-form{display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
		.emerson-modals-list code{user-select:all;word-break:break-all;}
		.emerson-modals-source-url,.emerson-modals-source-cff{margin-top:0.5rem;}
		.emerson-modals-actions{display:flex;flex-direction:column;align-items:flex-start;gap:0.4rem;}
		.emerson-modals-actions .button{margin:0;}
		.emerson-modals-custom .emerson-modals-actions .emerson-modals-remove.button{
			background:#b32d2e !important;
			border-color:#8a2424 !important;
			color:#fff !important;
			text-shadow:none !important;
			box-shadow:none !important;
		}
		.emerson-modals-custom .emerson-modals-actions .emerson-modals-remove.button:hover,
		.emerson-modals-custom .emerson-modals-actions .emerson-modals-remove.button:focus{
			background:#8a2424 !important;
			border-color:#6d1c1c !important;
			color:#fff !important;
		}
	</style>';

	echo '<div class="emerson-modals-custom">';
	echo '<h2>' . ( $edit_row ? 'Edit dialog' : 'Add a dialog' ) . '</h2>';
	echo '<p>After saving, edit the page → add a <strong>Button</strong> → select it → right sidebar <strong>Advanced</strong> → <strong>Additional CSS class(es)</strong>: <code>emerson-modal-your-id</code> (must be on the Button block, not typed into the link text). Link URL can be <code>#</code>. Never paste <code>[emerson_modal …]</code> inside a Button block.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_custom_modal_save' );
	if ( $edit_row ) {
		echo '<input type="hidden" name="modal_id" value="' . esc_attr( $edit_row['id'] ) . '">';
	}
	$label_val   = $edit_row['label'] ?? '';
	$heading_val = $edit_row['heading'] ?? '';
	$source_val  = $edit_row['source'] ?? 'url';
	$url_val     = $edit_row['url'] ?? '';
	$cff_val     = (int) ( $edit_row['cff_id'] ?? 0 );
	$style_val   = emerson_cta_normalize_style( (string) ( $edit_row['style'] ?? 'blue' ) );
	$slug_val    = $edit_row['id'] ?? '';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="emerson-modal-label">Button text</label></th><td>';
	echo '<input name="modal_label" id="emerson-modal-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( $label_val ) . '" required>';
	echo '<p class="description">Saved into Modals and synced onto any page Button that uses this dialog’s CSS class.</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-modal-css-slug">CSS class slug</label></th><td>';
	echo '<code>emerson-modal-</code><input name="modal_css_slug" id="emerson-modal-css-slug" type="text" class="regular-text" maxlength="40" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="' . esc_attr( $slug_val ) . '" ' . ( $edit_row ? '' : 'placeholder="auto-from-button-text"' ) . '>';
	echo '<p class="description">Full class on the Button: <code>emerson-modal-'
		. esc_html( $slug_val !== '' ? $slug_val : 'your-slug' )
		. '</code> (single hyphen — Gutenberg rejects <code>--</code> in Additional CSS classes). Change the slug to rename; pages using the old class update automatically.</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-modal-heading">Dialog heading</label></th><td>';
	echo '<input name="modal_heading" id="emerson-modal-heading" type="text" class="regular-text" maxlength="80" value="' . esc_attr( $heading_val ) . '">';
	echo '<p class="description">Optional. Leave blank when the form already shows its own title.</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-modal-style">Button style</label></th><td>';
	echo '<select name="modal_style" id="emerson-modal-style">';
	$style_labels = array(
		'green'   => 'Green fill (like Commitment Form)',
		'blue'    => 'Blue fill (like PayPal fees)',
		'outline' => 'Blue outline (like Community Survey)',
	);
	foreach ( $style_labels as $style_key => $style_label ) {
		echo '<option value="' . esc_attr( $style_key ) . '"' . selected( $style_val, $style_key, false ) . '>'
			. esc_html( $style_label ) . '</option>';
	}
	echo '</select>';
	echo '<p class="description">Matches the three button looks already used on the site. No extra CSS needed.</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row">Form</th><td>';
	echo '<label><input type="radio" name="modal_source" value="url"' . checked( $source_val, 'url', false ) . '> Microsoft or Google form link</label><br>';
	echo '<label><input type="radio" name="modal_source" value="cff"' . checked( $source_val, 'cff', false ) . '> Calculated Fields form</label>';
	echo '<div class="emerson-modals-source-url">';
	echo '<p><label for="emerson-modal-url">Form URL</label><br>';
	echo '<input name="modal_url" id="emerson-modal-url" type="url" class="large-text" value="' . esc_attr( $url_val ) . '">';
	echo '<p class="description">Microsoft Forms (<code>https://forms.cloud.microsoft/r/&hellip;</code>) or Google Forms. Prefer Google’s full <code>docs.google.com/forms/…/viewform</code> address (Send → &lt;&gt;) over a <code>forms.gle</code> short link — short links add a redirect inside the iframe.</p>';
	echo '</div>';
	echo '<div class="emerson-modals-source-cff">';
	echo '<p><label for="emerson-modal-cff">Calculated Fields form</label><br>';
	echo '<select name="modal_cff_id" id="emerson-modal-cff">';
	echo '<option value="0">— Choose a form —</option>';
	foreach ( $cff as $fid => $fname ) {
		echo '<option value="' . esc_attr( (string) $fid ) . '"' . selected( $cff_val, $fid, false ) . '>'
			. esc_html( $fname . ' (#' . $fid . ')' ) . '</option>';
	}
	echo '</select></p>';
	if ( $cff === array() ) {
		echo '<p class="description">No Calculated Fields forms found. Install or activate that plugin to choose one.</p>';
	}
	echo '</div>';
	echo '</td></tr></tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_custom_modal_save" value="1" class="button button-primary">'
		. ( $edit_row ? 'Save dialog' : 'Add dialog' ) . '</button>';
	if ( $edit_row ) {
		echo ' <a class="button" href="' . esc_url( admin_url( 'admin.php?page=emerson-modals' ) ) . '">Cancel</a>';
		if ( 'cff' === $source_val && $cff_val > 0 ) {
			$cff_edit = emerson_modal_cff_editor_url( $cff_val );
			if ( $cff_edit !== '' ) {
				echo ' <a class="button" href="' . esc_url( $cff_edit ) . '">Edit form</a>';
			}
		}
	}
	echo '</p></form>';

	if ( $custom ) {
		echo '<h2>Custom dialogs</h2>';
		echo '<table class="emerson-modals-list"><thead><tr><th>Button</th><th>Style</th><th>Form</th><th>CSS class</th><th></th></tr></thead><tbody>';
		foreach ( $custom as $modal ) {
			$form_label = 'cff' === $modal['source']
				? ( $cff[ $modal['cff_id'] ] ?? ( 'Form #' . $modal['cff_id'] ) )
				: $modal['url'];
			$style_label = array(
				'green'   => 'Green',
				'blue'    => 'Blue',
				'outline' => 'Outline',
			)[ $modal['style'] ] ?? 'Blue';
			echo '<tr>';
			echo '<td><strong>' . esc_html( $modal['label'] ) . '</strong></td>';
			echo '<td>' . esc_html( $style_label ) . '</td>';
			echo '<td><span class="emerson-modals-form" title="' . esc_attr( $form_label ) . '">' . esc_html( $form_label ) . '</span></td>';
			echo '<td><code>' . esc_html( emerson_custom_modal_button_class( $modal['id'] ) ) . '</code></td>';
			echo '<td><div class="emerson-modals-actions">';
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=emerson-modals&edit=' . rawurlencode( $modal['id'] ) ) ) . '">Edit</a>';
			if ( 'cff' === $modal['source'] ) {
				$cff_edit = emerson_modal_cff_editor_url( (int) $modal['cff_id'] );
				if ( $cff_edit !== '' ) {
					echo '<a class="button" href="' . esc_url( $cff_edit ) . '">Edit form</a>';
				}
			}
			echo '<form method="post" onsubmit="return confirm(\'Remove this dialog?\');">';
			wp_nonce_field( 'emerson_custom_modal_delete' );
			echo '<input type="hidden" name="modal_id" value="' . esc_attr( $modal['id'] ) . '">';
			echo '<button type="submit" name="emerson_custom_modal_delete" value="1" class="button emerson-modals-remove">Remove</button>';
			echo '</form></div></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
	echo '<script>
		(function () {
			var form = document.querySelector(".emerson-modals-custom form");
			if (!form) return;
			function sync() {
				var source = (form.querySelector("input[name=modal_source]:checked") || {}).value || "url";
				form.querySelector(".emerson-modals-source-url").style.display = source === "url" ? "block" : "none";
				form.querySelector(".emerson-modals-source-cff").style.display = source === "cff" ? "block" : "none";
			}
			form.addEventListener("change", function (event) {
				if (event.target && event.target.name === "modal_source") sync();
			});
			sync();
		})();
	</script>';

	echo '<h2>Recommitment Form</h2>';
	echo '<p>Opens from a Gutenberg Button with the <strong>CSS class</strong> below (legacy <code>pledge-button</code> still works). Change the class here to rename it on pages, or rename it in Gutenberg — saving the page teaches Modals the new class when it sits next to another CTA button.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_pledge_save' );
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="emerson-pledge-label">Button text</label></th><td>';
	echo '<input name="pledge_button_label" id="emerson-pledge-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_pledge_label() ) . '">';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-pledge-class">CSS class</label></th><td>';
	echo '<input name="pledge_button_class" id="emerson-pledge-class" type="text" class="regular-text" maxlength="40" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="' . esc_attr( emerson_recommitment_button_class() ) . '" required>';
	echo '<p class="description">Additional CSS class on the Button. No double hyphen (<code>--</code>). Saving a new class rewrites matching Buttons on pages.</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-pledge-heading">Dialog heading</label></th><td>';
	echo '<input name="pledge_dialog_heading" id="emerson-pledge-heading" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_pledge_dialog_heading() ) . '">';
	echo '<p class="description">Optional. Blank = no title above the form (only the ×).</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-pledge-style">Button style</label></th><td>';
	echo '<select name="pledge_button_style" id="emerson-pledge-style">';
	foreach ( emerson_cta_style_labels() as $style_key => $style_label ) {
		echo '<option value="' . esc_attr( $style_key ) . '"' . selected( emerson_pledge_button_style(), $style_key, false ) . '>'
			. esc_html( $style_label ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-pledge-url">Form URL</label></th><td>';
	echo '<input name="pledge_form_url" id="emerson-pledge-url" type="url" class="large-text" value="' . esc_attr( emerson_pledge_form_url() ) . '">';
	echo '<p class="description">Paste a Microsoft Forms address (<code>https://forms.cloud.microsoft/r/&hellip;</code>) or a Google Forms address (<code>https://docs.google.com/forms/&hellip;</code>).</p>';
	echo '</td></tr></table>';
	echo '<p class="submit"><button type="submit" name="emerson_pledge_save" value="1" class="button button-primary">Save recommitment form</button></p>';
	echo '</form>';

	echo '<h2>Pledge Card</h2>';
	echo '<p>Annual Google pledge card. Same idea as recommitment: set the <strong>CSS class</strong> here, or rename it on the page next to another CTA and Modals will pick it up on save.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_annual_pledge_save' );
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="emerson-annual-pledge-label">Button text</label></th><td>';
	echo '<input name="annual_pledge_button_label" id="emerson-annual-pledge-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_annual_pledge_label() ) . '">';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-annual-pledge-class">CSS class</label></th><td>';
	echo '<input name="annual_pledge_button_class" id="emerson-annual-pledge-class" type="text" class="regular-text" maxlength="40" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="' . esc_attr( emerson_annual_pledge_button_class() ) . '" required>';
	echo '<p class="description">Default <code>pledge-card</code>. Legacy <code>pledge-form</code> still opens this dialog.</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-annual-pledge-heading">Dialog heading</label></th><td>';
	echo '<input name="annual_pledge_dialog_heading" id="emerson-annual-pledge-heading" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_annual_pledge_dialog_heading() ) . '">';
	echo '<p class="description">Optional. Blank = no title above the Google form (only the ×). The form’s own title still shows inside the embed.</p>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-annual-pledge-style">Button style</label></th><td>';
	echo '<select name="annual_pledge_button_style" id="emerson-annual-pledge-style">';
	foreach ( emerson_cta_style_labels() as $style_key => $style_label ) {
		echo '<option value="' . esc_attr( $style_key ) . '"' . selected( emerson_annual_pledge_button_style(), $style_key, false ) . '>'
			. esc_html( $style_label ) . '</option>';
	}
	echo '</select></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-annual-pledge-url">Form URL</label></th><td>';
	echo '<input name="annual_pledge_form_url" id="emerson-annual-pledge-url" type="url" class="large-text" value="' . esc_attr( emerson_annual_pledge_form_url() ) . '">';
	echo '<p class="description">Paste the Google Form link. An <code>/edit</code> address is converted to <code>/viewform</code> for visitors.</p>';
	echo '</td></tr></table>';
	echo '<p class="submit"><button type="submit" name="emerson_annual_pledge_save" value="1" class="button button-primary">Save pledge form</button></p>';
	echo '</form>';

	echo '<h2>Emerson Community Survey</h2>';
	echo '<p>Calculated Fields survey emailed to the office. <strong>Button text</strong> is the button and the dialog heading. Place with a Shortcode block: <code>[' . esc_html( EMERSON_COMMUNITY_SURVEY_SHORTCODE ) . ']</code></p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_recommit_save' );
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="emerson-recommit-label">Button text</label></th><td>';
	echo '<input name="button_label" id="emerson-recommit-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_recommit_label() ) . '">';
	echo '</td></tr>';
	echo '<tr><th scope="row">Signature</th><td><label for="emerson-survey-signature">';
	echo '<input name="survey_signature" id="emerson-survey-signature" type="checkbox" value="1"' . checked( function_exists( 'emerson_survey_signature_enabled' ) && emerson_survey_signature_enabled(), true, false ) . '> ';
	echo 'Include the signature box</label>';
	echo '<p class="description">Adds the drawing area after the email field, with <strong>Clear signature</strong> and the date beside it.</p>';
	echo '</td></tr></table>';
	$survey_editor = function_exists( 'emerson_survey_editor_url' ) ? emerson_survey_editor_url() : '';
	if ( $survey_editor !== '' ) {
		echo '<p><a class="button" href="' . esc_url( $survey_editor ) . '">Edit form</a></p>';
	}
	echo '<p class="submit"><button type="submit" name="emerson_recommit_save" value="1" class="button button-primary">Save survey button</button></p>';
	echo '</form>';

	echo '<h2>Approximate PayPal Fees</h2>';
	echo '<p>The same dialog opens from the homepage and from Make a Contribution. Button text, CSS class, and heading are edited here. The fee fields and the math are edited in Calculated Fields.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_paypal_save' );
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="emerson-paypal-label">Button text</label></th><td>';
	echo '<input name="paypal_button_label" id="emerson-paypal-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_paypal_button_label() ) . '">';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-paypal-class">CSS class</label></th><td>';
	echo '<input name="paypal_button_class" id="emerson-paypal-class" type="text" class="regular-text" maxlength="40" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="' . esc_attr( emerson_paypal_fees_button_class() ) . '" required>';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-paypal-heading">Dialog heading</label></th><td>';
	echo '<input name="paypal_heading" id="emerson-paypal-heading" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_paypal_dialog_heading() ) . '">';
	echo '</td></tr></table>';
	echo '<p><a class="button" href="' . esc_url( emerson_paypal_editor_url() ) . '">Edit form</a></p>';
	echo '<p class="submit"><button type="submit" name="emerson_paypal_save" value="1" class="button button-primary">Save PayPal fees</button></p>';
	echo '</form></div>';
}
