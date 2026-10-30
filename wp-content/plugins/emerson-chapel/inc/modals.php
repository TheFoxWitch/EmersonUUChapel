<?php
/**
 * One dashboard screen for the site dialogs.
 *
 * Built-in dialogs (commitment form, community survey, PayPal fees) keep their
 * own settings. Custom dialogs are added below: each gets a shortcode button
 * that opens either a Microsoft/Google form or a Calculated Fields form.
 */

declare( strict_types=1 );

const EMERSON_PAYPAL_FORM_ID        = 8;
const EMERSON_PAYPAL_LABEL_OPTION   = 'emerson_paypal_button_label';
const EMERSON_PAYPAL_HEADING_OPTION = 'emerson_paypal_dialog_heading';
const EMERSON_PAYPAL_LABEL_DEFAULT  = 'Approximate PayPal Fees';
const EMERSON_PLEDGE_LABEL_OPTION   = 'emerson_pledge_label';
const EMERSON_PLEDGE_URL_OPTION     = 'emerson_pledge_form_url';
const EMERSON_PLEDGE_LABEL_DEFAULT  = '2026-27 Commitment Form';
const EMERSON_CUSTOM_MODALS_OPTION  = 'emerson_custom_modals';
const EMERSON_MODAL_OVERFLOW_ARROW_OPTION = 'emerson_modal_overflow_arrow';

/**
 * Whether overflowing form-maker dialogs show a brief bounce-down scroll hint.
 */
function emerson_modal_overflow_arrow_enabled(): bool {
	return get_option( EMERSON_MODAL_OVERFLOW_ARROW_OPTION, '1' ) === '1';
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
 * Whether this page has the home-style Buttons row (pledge and/or PayPal).
 */
function emerson_page_has_cta_buttons_row(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) ) {
		return false;
	}
	$content = (string) $post->post_content;
	return str_contains( $content, 'pledge-button' )
		|| str_contains( $content, 'paypal-fees-button' );
}

/**
 * Plugin openers join that Buttons row when the shortcode is on the page.
 */
function emerson_cta_will_inject_into_buttons(): bool {
	return emerson_page_has_cta_buttons_row()
		&& emerson_custom_modals_used_on_page() !== array();
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

function emerson_custom_modal_embed_url( array $modal ): string {
	if ( ( $modal['source'] ?? '' ) !== 'url' || ( $modal['url'] ?? '' ) === '' ) {
		return '';
	}
	$url = (string) $modal['url'];
	if ( ! function_exists( 'emerson_pledge_is_form_url' ) || ! emerson_pledge_is_form_url( $url ) ) {
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
	/* forms.gle short links: iframe follows the redirect to viewform. */
	return $url;
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
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'emerson_modal' )
			&& preg_match_all( '/\[emerson_modal\b([^\]]*)\]/', $post->post_content, $matches )
		) {
			foreach ( $matches[1] as $atts ) {
				if ( preg_match( '/\bid=["\']?([a-z0-9_-]+)/i', $atts, $m ) ) {
					$used[] = sanitize_key( $m[1] );
				}
			}
		}
	}
	$used = array_values( array_unique( array_filter( $used ) ) );
	return $used;
}

/**
 * Markup for one custom-modal opener (core button shape + shared CTA style class).
 *
 * @param array{id:string,label:string,style:string} $modal
 */
function emerson_custom_modal_button_html( array $modal ): string {
	$style = emerson_cta_normalize_style( (string) ( $modal['style'] ?? 'blue' ) );
	return '<div class="wp-block-button emerson-modal-button emerson-cta emerson-cta--' . esc_attr( $style ) . '">'
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
	if ( emerson_cta_will_inject_into_buttons() && ! is_admin()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return '';
	}
	return '<div class="emerson-cta-standalone">' . emerson_custom_modal_button_html( $modal ) . '</div>';
}
add_shortcode( 'emerson_modal', 'emerson_custom_modal_shortcode' );

/**
 * Append custom-modal buttons inside the pledge/PayPal Buttons group (same flex gap).
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_custom_modals_inject_into_buttons( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/buttons' ) {
		return $content;
	}
	if ( ! str_contains( $content, 'pledge-button' ) && ! str_contains( $content, 'paypal-fees-button' ) ) {
		return $content;
	}
	if ( ! emerson_cta_will_inject_into_buttons() ) {
		return $content;
	}
	$extra = '';
	foreach ( emerson_custom_modals_used_on_page() as $id ) {
		if ( str_contains( $content, 'data-emerson-modal="' . $id . '"' ) ) {
			continue;
		}
		$modal = emerson_custom_modal( $id );
		if ( $modal === null ) {
			continue;
		}
		$extra .= emerson_custom_modal_button_html( $modal );
	}
	if ( $extra === '' ) {
		return $content;
	}
	$pos = strrpos( $content, '</div>' );
	if ( false === $pos ) {
		return $content . $extra;
	}
	return substr( $content, 0, $pos ) . $extra . substr( $content, $pos );
}
add_filter( 'render_block', 'emerson_custom_modals_inject_into_buttons', 11, 2 );

/**
 * Drop standalone custom-modal shortcode blocks on the front when buttons join the CTA row.
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_custom_modals_suppress_shortcode_block( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/shortcode' ) {
		return $content;
	}
	$inner = (string) ( $block['innerHTML'] ?? '' );
	if ( ! str_contains( $inner, '[emerson_modal' ) ) {
		return $content;
	}
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}
	if ( ! emerson_cta_will_inject_into_buttons() ) {
		return $content;
	}
	return '';
}
add_filter( 'render_block', 'emerson_custom_modals_suppress_shortcode_block', 5, 2 );

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
		echo '<dialog id="' . esc_attr( $dialog_id ) . '" class="emerson-recommit emerson-modal" data-emerson-modal-id="' . esc_attr( $modal['id'] ) . '">';
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
			$src = emerson_custom_modal_embed_url( $modal );
			if ( $src !== '' ) {
				echo '<div class="emerson-recommit__fit" data-emerson-frame-fit>';
				echo '<p class="emerson-recommit__loading" hidden>Loading form…</p>';
				echo '<iframe class="emerson-recommit__frame" title="' . esc_attr( $modal['label'] ) . '" data-src="' . esc_url( $src ) . '"></iframe>';
				echo '</div>';
			} else {
				echo '<p>This form is not available right now.</p>';
			}
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
	$url = emerson_pledge_form_url();
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

/**
 * The page can still hold an old link. The words and the dialog come from Modals.
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_pledge_render_button( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/button' || ! str_contains( $content, 'pledge-button' ) ) {
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
	return is_string( $updated ) ? $updated : $content;
}
add_filter( 'render_block', 'emerson_pledge_render_button', 10, 2 );

function emerson_pledge_page_has_button(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post instanceof WP_Post && str_contains( $post->post_content, 'pledge-button' );
}

function emerson_pledge_dialog(): void {
	if ( ! emerson_pledge_page_has_button() ) {
		return;
	}
	$src   = emerson_pledge_embed_url();
	$label = emerson_pledge_label();
	?>
	<dialog id="emerson-pledge" class="emerson-recommit">
		<div class="emerson-recommit__bar">
			<h2><?php echo esc_html( $label ); ?></h2>
			<?php echo emerson_modal_close_button( 'emerson-recommit__close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
		</div>
		<?php if ( $src !== '' ) : ?>
		<div class="emerson-recommit__fit" data-emerson-frame-fit>
			<p class="emerson-recommit__loading" hidden>Loading form…</p>
			<iframe class="emerson-recommit__frame" title="<?php echo esc_attr( $label ); ?>" data-src="<?php echo esc_url( $src ); ?>"></iframe>
		</div>
		<?php else : ?>
		<p>The pledge form is not available right now.</p>
		<?php endif; ?>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'emerson_pledge_dialog', 5 );

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! emerson_pledge_page_has_button() || wp_script_is( 'emerson-recommit', 'enqueued' ) ) {
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
	$label = isset( $_POST['pledge_button_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['pledge_button_label'] ) ) : '';
	$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	if ( $label === '' ) {
		return 'Type the words that should appear on the pledge button.';
	}
	if ( mb_strlen( $label ) > 80 ) {
		return 'Keep the pledge button text to 80 characters.';
	}
	if ( ! emerson_pledge_is_form_url( $url ) ) {
		return 'Paste a Microsoft Forms or Google Forms address.';
	}
	update_option( EMERSON_PLEDGE_LABEL_OPTION, $label, false );
	update_option( EMERSON_PLEDGE_URL_OPTION, $url, false );
	return 'Saved. The pledge button and dialog will use this wording and form.';
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
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_paypal_render_button( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/button' || ! str_contains( $content, 'data-emerson-paypal-fees' ) ) {
		return $content;
	}
	$label   = esc_html( emerson_paypal_button_label() );
	$updated = preg_replace(
		'#(<button\b[^>]*\bdata-emerson-paypal-fees\b[^>]*>)(.*?)(</button>)#s',
		'$1' . $label . '$3',
		$content,
		1
	);
	return is_string( $updated ) ? $updated : $content;
}
add_filter( 'render_block', 'emerson_paypal_render_button', 10, 2 );

function emerson_paypal_page_has_button(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post instanceof WP_Post && str_contains( $post->post_content, 'data-emerson-paypal-fees' );
}

function emerson_paypal_dialog(): void {
	if ( ! emerson_paypal_page_has_button() ) {
		return;
	}
	$heading = emerson_paypal_dialog_heading();
	?>
	<dialog id="emerson-paypal-fees" class="emerson-paypal-fees">
		<div class="emerson-recommit__bar">
			<h2><?php echo esc_html( $heading ); ?></h2>
			<?php echo emerson_modal_close_button( 'emerson-paypal-fees__close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
		</div>
		<?php echo do_shortcode( '[CP_CALCULATED_FIELDS id="' . EMERSON_PAYPAL_FORM_ID . '"]' ); ?>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'emerson_paypal_dialog', 5 );

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
	update_option( EMERSON_PAYPAL_LABEL_OPTION, $label, false );
	update_option( EMERSON_PAYPAL_HEADING_OPTION, $heading, false );
	return 'Saved. The PayPal fees button and heading will use this wording.';
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

	$row = array(
		'id'      => $editing !== '' ? $editing : emerson_custom_modal_unique_id( $label ),
		'label'   => $label,
		'heading' => $heading,
		'source'  => $source,
		'url'     => (string) ( wp_unslash( $_POST['modal_url'] ?? '' ) ),
		'cff_id'  => absint( $_POST['modal_cff_id'] ?? 0 ),
		'style'   => emerson_cta_normalize_style( (string) ( wp_unslash( $_POST['modal_style'] ?? 'blue' ) ) ),
	);
	if ( $editing === '' ) {
		$row['id'] = emerson_custom_modal_unique_id( $label );
	} else {
		$row['id'] = $editing;
	}
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
		if ( $existing['id'] === $modal['id'] ) {
			$next[ $i ] = $modal;
			$replaced   = true;
			break;
		}
	}
	if ( ! $replaced ) {
		$next[] = $modal;
	}
	update_option( EMERSON_CUSTOM_MODALS_OPTION, $next, false );
	return 'Saved. Place the button with [emerson_modal id="' . $modal['id'] . '"].';
}

function emerson_modals_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit the dialogs.' ) );
	}
	$notice = emerson_modal_display_admin_save()
		?? emerson_custom_modals_admin_save()
		?? emerson_pledge_admin_save()
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
	echo '<p>Built-in dialogs keep their own settings below. <strong>Custom dialogs</strong> are added here: choose a Calculated Fields form or paste a Microsoft/Google form link, then place the button with a shortcode.</p>';

	echo '<h2>Display</h2>';
	echo '<p>Shared cues for every Emerson dialog that wraps a Calculated Fields form (survey, PayPal fees, and custom form-maker dialogs).</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_modal_display_save' );
	echo '<table class="form-table" role="presentation"><tr><th scope="row">Overflow hint</th><td>';
	echo '<label for="emerson-modal-overflow-arrow">';
	echo '<input name="modal_overflow_arrow" id="emerson-modal-overflow-arrow" type="checkbox" value="1"'
		. checked( emerson_modal_overflow_arrow_enabled(), true, false ) . '> ';
	echo 'Show an animated scroll arrow when the form is taller than the dialog</label>';
	echo '<p class="description">The arrow sits over the bottom of the dialog, bounces three times, then disappears. It also disappears as soon as someone scrolls. Turned off when the visitor prefers reduced motion.</p>';
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
	echo '<p>After saving, paste <code>[emerson_modal id="your-id"]</code> into a page (Shortcode block). On pages with the pledge/PayPal buttons row, the button joins that row automatically (same spacing). Elsewhere it centres on its own.</p>';
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
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="emerson-modal-label">Button text</label></th><td>';
	echo '<input name="modal_label" id="emerson-modal-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( $label_val ) . '" required>';
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
	echo '<p class="description">Microsoft Forms (<code>https://forms.cloud.microsoft/r/&hellip;</code>) or Google Forms.</p>';
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
		echo '<table class="emerson-modals-list"><thead><tr><th>Button</th><th>Style</th><th>Form</th><th>Shortcode</th><th></th></tr></thead><tbody>';
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
			echo '<td><code>[emerson_modal id="' . esc_attr( $modal['id'] ) . '"]</code></td>';
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

	echo '<h2>Commitment Form</h2>';
	echo '<p>The homepage commitment button opens this form in a dialog. <strong>Button text</strong> is the button. The form already shows its own title, so the dialog does not add another. Editing the button on the page does not change this dialog.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_pledge_save' );
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="emerson-pledge-label">Button text</label></th><td>';
	echo '<input name="pledge_button_label" id="emerson-pledge-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_pledge_label() ) . '">';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-pledge-url">Form URL</label></th><td>';
	echo '<input name="pledge_form_url" id="emerson-pledge-url" type="url" class="large-text" value="' . esc_attr( emerson_pledge_form_url() ) . '">';
	echo '<p class="description">Paste a Microsoft Forms address (<code>https://forms.cloud.microsoft/r/&hellip;</code>) or a Google Forms address (<code>https://docs.google.com/forms/&hellip;</code>).</p>';
	echo '</td></tr></table>';
	echo '<p class="submit"><button type="submit" name="emerson_pledge_save" value="1" class="button button-primary">Save commitment form</button></p>';
	echo '</form>';

	echo '<h2>Emerson Community Survey</h2>';
	echo '<p>This button opens the chapel commitment form. The answers are emailed to the office. <strong>Button text</strong> is the button and the heading inside the dialog. The fields, wording, and submit button are edited in Calculated Fields. The 2026-27 Commitment Form button is the separate Microsoft form.</p>';
	echo '<p>Place the button with a Shortcode block: <code>[' . esc_html( EMERSON_COMMUNITY_SURVEY_SHORTCODE ) . ']</code></p>';
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
	echo '<p>The same dialog opens from the homepage and from Make a Contribution. The button and the heading are edited here. The fee fields and the math are edited in Calculated Fields.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_paypal_save' );
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="emerson-paypal-label">Button text</label></th><td>';
	echo '<input name="paypal_button_label" id="emerson-paypal-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_paypal_button_label() ) . '">';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-paypal-heading">Dialog heading</label></th><td>';
	echo '<input name="paypal_heading" id="emerson-paypal-heading" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_paypal_dialog_heading() ) . '">';
	echo '</td></tr></table>';
	echo '<p><a class="button" href="' . esc_url( emerson_paypal_editor_url() ) . '">Edit form</a></p>';
	echo '<p class="submit"><button type="submit" name="emerson_paypal_save" value="1" class="button button-primary">Save PayPal fees</button></p>';
	echo '</form></div>';
}
