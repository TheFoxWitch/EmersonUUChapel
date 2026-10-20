<?php
/**
 * One dashboard screen for the site dialogs.
 *
 * Re-commitment is a Microsoft Form address. PayPal fees is the chapel's own
 * calculator: the button and heading are edited here, and the fields are
 * edited in Calculated Fields.
 */

declare( strict_types=1 );

const EMERSON_PAYPAL_FORM_ID        = 8;
const EMERSON_PAYPAL_LABEL_OPTION   = 'emerson_paypal_button_label';
const EMERSON_PAYPAL_HEADING_OPTION = 'emerson_paypal_dialog_heading';
const EMERSON_PAYPAL_LABEL_DEFAULT  = 'Approximate PayPal Fees';
const EMERSON_PLEDGE_LABEL_OPTION   = 'emerson_pledge_label';
const EMERSON_PLEDGE_URL_OPTION     = 'emerson_pledge_form_url';
const EMERSON_PLEDGE_LABEL_DEFAULT  = '2026-27 Commitment Form';

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
			<button type="button" class="emerson-recommit__close">Close</button>
		</div>
		<?php if ( $src !== '' ) : ?>
		<div class="emerson-recommit__fit">
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
		$path = get_stylesheet_directory() . '/assets/js/recommitment.js';
		wp_enqueue_script(
			'emerson-recommit',
			get_stylesheet_directory_uri() . '/assets/js/recommitment.js',
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
		<button type="button" class="emerson-paypal-fees__close">Close</button>
		<h2><?php echo esc_html( $heading ); ?></h2>
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

function emerson_paypal_editor_url(): string {
	return admin_url( 'admin.php?page=cp_calculated_fields_form&cal=' . EMERSON_PAYPAL_FORM_ID . '&_cpcff_nonce=' . wp_create_nonce( 'cff-form-settings' ) );
}

function emerson_modals_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit the dialogs.' ) );
	}
	$notice = emerson_pledge_admin_save() ?? emerson_recommit_admin_save() ?? emerson_paypal_admin_save();
	echo '<div class="wrap"><h1>Modals</h1>';
	if ( $notice ) {
		$bad = 0 !== strpos( $notice, 'Saved.' );
		echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>These dialogs open from buttons on the site. A form address is pasted here. The PayPal fee fields are edited in their own form builder.</p>';

	echo '<h2>2026-27 Commitment Form</h2>';
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
		echo '<p><a class="button" href="' . esc_url( $survey_editor ) . '">Edit the commitment form</a></p>';
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
	echo '<p><a class="button" href="' . esc_url( emerson_paypal_editor_url() ) . '">Edit the fee form</a></p>';
	echo '<p class="submit"><button type="submit" name="emerson_paypal_save" value="1" class="button button-primary">Save PayPal fees</button></p>';
	echo '</form></div>';
}
