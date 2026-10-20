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
	$notice = emerson_recommit_admin_save() ?? emerson_paypal_admin_save();
	echo '<div class="wrap"><h1>Modals</h1>';
	if ( $notice ) {
		$bad = 0 !== strpos( $notice, 'Saved.' );
		echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>These dialogs open from buttons on the site. A form address is pasted here. The PayPal fee fields are edited in their own form builder.</p>';

	echo '<h2>Re-commitment</h2>';
	echo '<p>The homepage button opens a Microsoft Form. The words in <strong>Button text</strong> are the button and the heading inside the dialog. Answers stay on Microsoft&#8217;s form.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_recommit_save' );
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th scope="row"><label for="emerson-recommit-label">Button text</label></th><td>';
	echo '<input name="button_label" id="emerson-recommit-label" type="text" class="regular-text" maxlength="80" value="' . esc_attr( emerson_recommit_label() ) . '">';
	echo '</td></tr>';
	echo '<tr><th scope="row"><label for="emerson-recommit-url">Form URL</label></th><td>';
	echo '<input name="form_url" id="emerson-recommit-url" type="url" class="large-text" value="' . esc_attr( emerson_recommit_form_url() ) . '">';
	echo '<p class="description">Paste the address from the browser while the form is open. It looks like <code>https://forms.cloud.microsoft/r/&hellip;</code>.</p>';
	echo '</td></tr></table>';
	echo '<p class="submit"><button type="submit" name="emerson_recommit_save" value="1" class="button button-primary">Save re-commitment</button></p>';
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
