<?php
/**
 * Emerson Community Survey (chapel commitment form in a dialog).
 *
 * Place the button with [emerson_community_survey] (Shortcode block), same idea as
 * custom Modals. Settings stay under Emerson Chapel → Modals.
 */

declare( strict_types=1 );

const EMERSON_RECOMMIT_OFFICE       = 'office@emersonuuchapel.org';
const EMERSON_RECOMMIT_MAX_PER_HOUR = 8;
const EMERSON_RECOMMIT_FORM_OPTION   = 'emerson_recommit_form_url';
const EMERSON_RECOMMIT_FORM_DEFAULT  = 'https://forms.cloud.microsoft/r/59mVXw4vPP';
const EMERSON_RECOMMIT_LABEL_OPTION  = 'emerson_recommit_label';
const EMERSON_RECOMMIT_LABEL_DEFAULT = 'Emerson Community Survey';
const EMERSON_COMMUNITY_SURVEY_SHORTCODE = 'emerson_community_survey';

/**
 * Words on the homepage button and the dialog heading.
 */
function emerson_recommit_label(): string {
	$stored = sanitize_text_field( trim( (string) get_option( EMERSON_RECOMMIT_LABEL_OPTION, '' ) ) );
	return $stored !== '' ? $stored : EMERSON_RECOMMIT_LABEL_DEFAULT;
}

/**
 * Share link saved in the dashboard, or this year's form when nothing is saved yet.
 */
function emerson_recommit_form_url(): string {
	$stored = trim( (string) get_option( EMERSON_RECOMMIT_FORM_OPTION, '' ) );
	return $stored !== '' ? $stored : EMERSON_RECOMMIT_FORM_DEFAULT;
}

/**
 * A Microsoft Forms share link or response page. Other sites are refused.
 */
function emerson_recommit_is_form_url( string $url ): bool {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || empty( $parts['host'] ) ) {
		return false;
	}
	$host = strtolower( (string) $parts['host'] );
	if ( ! in_array( $host, array( 'forms.cloud.microsoft', 'forms.office.com', 'forms.microsoft.com' ), true ) ) {
		return false;
	}
	$path = (string) ( $parts['path'] ?? '' );
	return (bool) preg_match( '#^/(r|e|pages/responsepage\.aspx)(/|$)#i', $path );
}

/**
 * Pull a URL out of a pasted address or out of Microsoft's iframe embed code.
 */
function emerson_recommit_clean_form_url( string $raw ): string {
	$raw = trim( $raw );
	if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $raw, $match ) ) {
		$raw = html_entity_decode( $match[1], ENT_QUOTES );
	}
	return esc_url_raw( $raw );
}

/**
 * Address the dialog loads. embed=true lets Microsoft show the form inside the page.
 */
function emerson_recommit_embed_url(): string {
	$url = emerson_recommit_form_url();
	if ( ! emerson_recommit_is_form_url( $url ) ) {
		return '';
	}
	if ( preg_match( '/(?:^|[?&])embed=true(?:&|$)/i', $url ) ) {
		return $url;
	}
	return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . 'embed=true';
}

/**
 * Whether this request’s post content asks for the community survey button.
 */
function emerson_recommit_page_has_button(): bool {
	if ( ! empty( $GLOBALS['emerson_community_survey_used'] ) ) {
		return true;
	}
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) ) {
		return false;
	}
	$content = (string) $post->post_content;
	return str_contains( $content, 'data-emerson-recommit' )
		|| has_shortcode( $content, EMERSON_COMMUNITY_SURVEY_SHORTCODE );
}

/**
 * Home CTA row (pledge / PayPal) can host the survey so all openers share one flex gap.
 */
function emerson_recommit_will_inject_into_buttons(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) ) {
		return false;
	}
	$content = (string) $post->post_content;
	if ( ! has_shortcode( $content, EMERSON_COMMUNITY_SURVEY_SHORTCODE ) ) {
		return false;
	}
	if ( function_exists( 'emerson_page_has_cta_buttons_row' ) ) {
		return emerson_page_has_cta_buttons_row();
	}
	return str_contains( $content, 'pledge-button' )
		|| str_contains( $content, 'paypal-fees-button' );
}

/**
 * Markup for the survey opener (inner button only — sits inside .wp-block-buttons).
 */
function emerson_community_survey_button_html(): string {
	return '<div class="wp-block-button recommit-button emerson-cta emerson-cta--outline"><button type="button" class="wp-block-button__link wp-element-button" data-emerson-recommit>'
		. esc_html( emerson_recommit_label() ) . '</button></div>';
}

/**
 * Button for the Emerson Community Survey dialog.
 *
 * Usage: [emerson_community_survey]
 * On the home CTA row the button is injected into that buttons block for even spacing;
 * the shortcode block itself is suppressed on the front in that case.
 */
function emerson_community_survey_shortcode(): string {
	$GLOBALS['emerson_community_survey_used'] = true;
	if ( emerson_recommit_will_inject_into_buttons() && ! is_admin()
		&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return '';
	}
	return '<div class="emerson-community-survey-button">' . emerson_community_survey_button_html() . '</div>';
}
add_shortcode( EMERSON_COMMUNITY_SURVEY_SHORTCODE, 'emerson_community_survey_shortcode' );

/**
 * Append the survey button inside the pledge/PayPal buttons group (same flex gap).
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_recommit_inject_into_buttons( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/buttons' ) {
		return $content;
	}
	if ( str_contains( $content, 'data-emerson-recommit' ) ) {
		return $content;
	}
	if ( ! str_contains( $content, 'pledge-button' ) && ! str_contains( $content, 'paypal-fees-button' ) ) {
		return $content;
	}
	if ( ! emerson_recommit_will_inject_into_buttons() ) {
		return $content;
	}
	$GLOBALS['emerson_community_survey_used'] = true;
	$button = emerson_community_survey_button_html();
	$pos    = strrpos( $content, '</div>' );
	if ( false === $pos ) {
		return $content . $button;
	}
	return substr( $content, 0, $pos ) . $button . substr( $content, $pos );
}
add_filter( 'render_block', 'emerson_recommit_inject_into_buttons', 10, 2 );

/**
 * Drop the standalone shortcode block on the front when the button lives in the CTA row.
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_community_survey_suppress_shortcode_block( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/shortcode' ) {
		return $content;
	}
	$inner = (string) ( $block['innerHTML'] ?? '' );
	if ( ! str_contains( $inner, '[' . EMERSON_COMMUNITY_SURVEY_SHORTCODE ) ) {
		return $content;
	}
	if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}
	if ( ! emerson_recommit_will_inject_into_buttons() ) {
		return $content;
	}
	$GLOBALS['emerson_community_survey_used'] = true;
	return '';
}
add_filter( 'render_block', 'emerson_community_survey_suppress_shortcode_block', 5, 2 );

function emerson_recommit_enqueue(): void {
	if ( ! emerson_recommit_page_has_button() ) {
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
	wp_localize_script(
		'emerson-recommit',
		'emersonRecommit',
		array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'emerson_recommit' ),
			'signature' => emerson_survey_signature_enabled(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'emerson_recommit_enqueue' );

function emerson_survey_form_id(): int {
	$stored = (int) get_option( 'emerson_survey_cff_id', 0 );
	if ( $stored <= 0 || ! defined( 'CP_CALCULATEDFIELDSF_FORMS_TABLE' ) ) {
		return 0;
	}
	global $wpdb;
	$found = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT id FROM ' . $wpdb->prefix . CP_CALCULATEDFIELDSF_FORMS_TABLE . ' WHERE id = %d',
			$stored
		)
	);
	return $found > 0 ? $found : 0;
}

function emerson_survey_ensure_form(): void {
	if ( ! class_exists( 'CPCFF_FORM' ) || emerson_survey_form_id() > 0 ) {
		return;
	}
	$form = CPCFF_FORM::create_default( 'Emerson Community Survey' );
	if ( empty( $form ) ) {
		return;
	}
	$settings = json_decode( CP_CALCULATEDFIELDSF_DEFAULT_form_structure_blank, true );
	if ( ! is_array( $settings ) ) {
		return;
	}
	$field = static function ( array $row, int $index ): array {
		$row['form_identifier'] = '';
		$row['index']           = $index;
		$row['shortlabel']      = '';
		$row['userhelp']        = $row['userhelp'] ?? '';
		$row['csslayout']       = '';
		$row['advanced']        = array( 'css' => new stdClass() );
		return $row;
	};
	$settings[0] = array(
		$field(
			array(
				'name'   => 'fieldname8',
				'ftype'  => 'fCommentArea',
				'title'  => 'I commit to my pledge for the fiscal year 2025-2026. This is to ensure the financial security and commitment Emerson UU Chapel is intending to take on this fiscal year.',
			),
			0
		),
		$field(
			array(
				'name'     => 'fieldname1',
				'ftype'    => 'ftext',
				'title'    => 'Name',
				'required' => true,
				'size'     => 'medium',
			),
			1
		),
		$field(
			array(
				'name'             => 'fieldname2',
				'ftype'            => 'fcurrency',
				'title'            => 'Pledge',
				'required'         => true,
				'size'             => 'medium',
				'currencySymbol'   => '$',
				'currencyText'     => '',
				'thousandSeparator'=> ',',
				'centSeparator'    => '.',
				'twoDecimals'      => true,
				'noCents'          => false,
			),
			2
		),
		$field(
			array(
				'name'              => 'fieldname3',
				'ftype'             => 'fdropdown',
				'title'             => 'I will pay this',
				'required'          => true,
				'size'              => 'medium',
				'choices'           => array( 'monthly', 'quarterly', 'annually' ),
				'choicesVal'        => array( 'monthly', 'quarterly', 'annually' ),
				'first_choice'      => true,
				'first_choice_text' => 'Choose',
			),
			3
		),
		$field(
			array(
				'name'     => 'fieldname4',
				'ftype'    => 'fdate',
				'title'    => 'Date payments continue',
				'required' => true,
				'size'     => 'medium',
				'dformat'  => 'mm/dd/yyyy',
			),
			4
		),
		$field(
			array(
				'name'            => 'fieldname5',
				'ftype'           => 'ftext',
				'title'           => 'Phone',
				'required'        => true,
				'size'            => 'medium',
				'predefined'      => '',
				'predefinedClick' => false,
				'userhelp'        => '10-digit number, for example (636)757-3633',
				'csslayout'       => 'emerson-survey-phone',
			),
			5
		),
		$field(
			array(
				'name'     => 'fieldname6',
				'ftype'    => 'femail',
				'title'    => 'Email',
				'required' => true,
				'size'     => 'medium',
			),
			6
		),
		$field(
			array(
				'name'     => 'fieldname7',
				'ftype'    => 'fdate',
				'title'    => 'Date signed',
				'required' => true,
				'size'     => 'medium',
				'dformat'  => 'mm/dd/yyyy',
			),
			7
		),
	);
	if ( isset( $settings[1][0] ) && is_array( $settings[1][0] ) ) {
		$settings[1][0]['title']       = '';
		$settings[1][0]['description'] = '';
	}
	$form->save_settings(
		array(
			'form_structure'         => wp_json_encode( $settings ),
			'fp_from_email'          => '',
			'fp_destination_emails'  => EMERSON_RECOMMIT_OFFICE,
			'fp_subject'             => 'Commitment form',
			'fp_message'             => "A commitment form was submitted.\n\n<%INFO%>\n",
			'fp_emailformat'         => 'text',
			'fp_inc_additional_info' => 'false',
			'fp_reply_to_emails'     => '<%fieldname6%>',
			'fp_ajax'                => 1,
			'fp_ajax_reset_form'     => 0,
			'fp_thanks_mssg'         => 'Sent. Thank you for your commitment.',
			'enable_submit'          => '',
			'vs_text_submitbtn'      => 'Submit',
			'enable_paypal'          => 0,
			'cv_enable_captcha'      => 'false',
		)
	);
	update_option( 'emerson_survey_cff_id', $form->get_id(), false );
}
add_action( 'init', 'emerson_survey_ensure_form', 20 );

/**
 * Do not wipe the form after AJAX success — that emptied required fields and showed “This field is required.”
 */
add_filter(
	'cpcff_get_option',
	static function ( $value, $option, $form_id ) {
		if ( 'fp_ajax_reset_form' === $option && (int) $form_id === emerson_survey_form_id() ) {
			return 0;
		}
		return $value;
	},
	10,
	3
);

function emerson_survey_editor_url(): string {
	$id = emerson_survey_form_id();
	if ( $id <= 0 ) {
		return '';
	}
	return admin_url( 'admin.php?page=cp_calculated_fields_form&cal=' . $id . '&_cpcff_nonce=' . wp_create_nonce( 'cff-form-settings' ) );
}

function emerson_recommit_dialog(): void {
	if ( ! emerson_recommit_page_has_button() ) {
		return;
	}
	$label   = emerson_recommit_label();
	$form_id = emerson_survey_form_id();
	?>
	<dialog id="emerson-recommit" class="emerson-recommit emerson-recommit--form">
		<div class="emerson-recommit__bar">
			<h2><?php echo esc_html( $label ); ?></h2>
			<?php echo emerson_modal_close_button( 'emerson-recommit__close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns escaped HTML. ?>
		</div>
		<?php if ( $form_id > 0 ) : ?>
			<?php echo do_shortcode( '[CP_CALCULATED_FIELDS id="' . $form_id . '"]' ); ?>
		<?php else : ?>
		<form class="emerson-recommit__form" novalidate>
			<p class="emerson-recommit__sentence">
				I
				<label class="screen-reader-text" for="emerson-recommit-name">Your name</label>
				<input class="emerson-recommit__name" id="emerson-recommit-name" name="name" type="text" maxlength="80" placeholder="Your Name" autocomplete="name" required>
				commit to my pledge of
				<label class="screen-reader-text" for="emerson-recommit-pledge">Your pledge</label>
				<span class="emerson-recommit__money">$<input class="emerson-recommit__pledge" id="emerson-recommit-pledge" name="pledge" type="text" inputmode="decimal" placeholder="Your Pledge" autocomplete="off" required></span>
				for the fiscal year 2025-2026 and will pay this
				<label class="screen-reader-text" for="emerson-recommit-frequency">How often you will pay</label>
				<select id="emerson-recommit-frequency" name="frequency" required>
					<option value="">Choose</option>
					<option value="monthly">monthly</option>
					<option value="quarterly">quarterly</option>
					<option value="annually">annually</option>
				</select>
				continued on
				<label class="screen-reader-text" for="emerson-recommit-continue">Date payments continue</label>
				<input id="emerson-recommit-continue" name="continue_on" type="date" required>.
				This is to ensure the financial security and commitment Emerson UU Chapel is intending to take on this fiscal year.
			</p>
			<div class="emerson-recommit__contact">
				<label for="emerson-recommit-phone">Phone
					<input id="emerson-recommit-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="30" placeholder="(636) 555-0100" required>
				</label>
				<label for="emerson-recommit-email">Email
					<input id="emerson-recommit-email" name="email" type="email" autocomplete="email" maxlength="100" placeholder="name@email.com" required>
				</label>
			</div>
			<p class="emerson-recommit__sign">
				Signed
				<label class="screen-reader-text" for="emerson-recommit-pad">Draw your signature</label>
				<canvas class="emerson-recommit__pad" id="emerson-recommit-pad"></canvas>
			</p>
			<div class="emerson-recommit__sign-row">
				<p class="emerson-recommit__sign-actions">
					<button type="button" class="emerson-recommit__clear">Clear signature</button>
					and date
					<label class="screen-reader-text" for="emerson-recommit-signed">Date signed</label>
					<input id="emerson-recommit-signed" name="signed_on" type="date" required>.
				</p>
				<button type="submit" class="emerson-recommit__submit">Submit</button>
			</div>
			<p class="emerson-recommit__hp" aria-hidden="true">
				<label>Company <input name="company" tabindex="-1" autocomplete="off"></label>
			</p>
			<p class="emerson-recommit__status" role="status"></p>
		</form>
		<?php endif; ?>
	</dialog>
	<?php
}
add_action( 'wp_footer', 'emerson_recommit_dialog' );

function emerson_recommit_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_recommit_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_recommit_save' );
	$label = isset( $_POST['button_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['button_label'] ) ) : '';
	$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	if ( $label === '' ) {
		return 'Type the words that should appear on the survey button.';
	}
	if ( mb_strlen( $label ) > 80 ) {
		return 'The button text is too long. Keep it to 80 characters.';
	}
	update_option( EMERSON_RECOMMIT_LABEL_OPTION, $label, false );
	update_option( 'emerson_survey_signature', empty( $_POST['survey_signature'] ) ? '0' : '1', false );
	return 'Saved. Place the button with [' . EMERSON_COMMUNITY_SURVEY_SHORTCODE . '].';
}

function emerson_survey_signature_enabled(): bool {
	return get_option( 'emerson_survey_signature', '0' ) === '1';
}

/**
 * PNG bytes from the signature field, or an empty string when it is missing or blank.
 */
function emerson_survey_signature_png(): string {
	$signature = isset( $_POST['emerson_signature'] ) ? (string) wp_unslash( $_POST['emerson_signature'] ) : '';
	if ( ! preg_match( '#^data:image/png;base64,#', $signature ) ) {
		return '';
	}
	$png = base64_decode( substr( $signature, strlen( 'data:image/png;base64,' ) ), true );
	if ( ! is_string( $png ) || strlen( $png ) < 80 || strlen( $png ) > 400000 || ! str_starts_with( $png, "\x89PNG\r\n\x1a\n" ) || ! emerson_recommit_has_ink( $png ) ) {
		return '';
	}
	return $png;
}

function emerson_survey_require_signature(): void {
	if ( ! emerson_survey_signature_enabled() ) {
		return;
	}
	$form_id = isset( $_POST['cp_calculatedfieldsf_id'] ) ? (int) $_POST['cp_calculatedfieldsf_id'] : 0;
	if ( $form_id <= 0 || $form_id !== emerson_survey_form_id() ) {
		return;
	}
	$png = emerson_survey_signature_png();
	// Drop the base64 from $_POST before Calculated Fields runs — it bloated the save and made
	// the AJAX iframe hang past CFF’s 3s timeout, which showed a fake “thanks” with no email.
	unset( $_POST['emerson_signature'], $_REQUEST['emerson_signature'] );
	if ( $png === '' ) {
		echo 'Draw your signature.';
		exit;
	}
	$file = get_temp_dir() . 'emerson-recommit-' . wp_generate_password( 12, false ) . '.png';
	if ( false === file_put_contents( $file, $png ) ) {
		echo 'The form could not be sent. Please try again, or email the office.';
		exit;
	}
	$GLOBALS['emerson_survey_signature_file'] = $file;
}
add_action( 'init', 'emerson_survey_require_signature', 98 );

/**
 * Keep the huge signature string out of the submission record if it slipped through.
 *
 * @param array<string,mixed> $params Submission payload.
 * @param string              $buffer Summary text.
 */
function emerson_survey_strip_signature_from_params( &$params, &$buffer = null ): void {
	unset( $params['emerson_signature'] );
	if ( is_string( $buffer ) && str_contains( $buffer, 'emerson_signature' ) ) {
		$buffer = (string) preg_replace( '/^.*emerson_signature.*$/mi', '', $buffer );
	}
}
add_action( 'cpcff_free_process_data_before_insert', 'emerson_survey_strip_signature_from_params', 1, 2 );

function emerson_survey_attach_signature( $phpmailer ): void {
	$file = $GLOBALS['emerson_survey_signature_file'] ?? '';
	if ( ! is_string( $file ) || $file === '' || ! is_readable( $file ) ) {
		return;
	}
	$phpmailer->addAttachment( $file, 'signature.png' );
}
add_action( 'phpmailer_init', 'emerson_survey_attach_signature' );

/**
 * Calculated Fields Form registers an Akismet subject filter typed as (string, array),
 * but passes the submission id (int). PHP 8 throws; CFF catches it and skips wp_mail.
 */
function emerson_survey_fix_cff_akismet_mail_filter(): void {
	if ( ! class_exists( 'CPCFF_Akismet' ) ) {
		return;
	}
	remove_filter( 'cpcff_notification_email_subject', array( CPCFF_Akismet::get_instance(), 'mark_email_as_spam' ), 10 );
}
add_action( 'init', 'emerson_survey_fix_cff_akismet_mail_filter', 5 );

function emerson_survey_delete_signature_file(): void {
	$file = $GLOBALS['emerson_survey_signature_file'] ?? '';
	if ( is_string( $file ) && $file !== '' && file_exists( $file ) ) {
		wp_delete_file( $file );
	}
}
add_action( 'shutdown', 'emerson_survey_delete_signature_file' );

/**
 * @return string Normalized dollars.cents, or an empty string when the amount is not usable.
 */
function emerson_recommit_amount( string $raw ): string {
	$raw = str_replace( array( '$', ',', ' ' ), '', trim( $raw ) );
	if ( ! preg_match( '/^\d{1,6}(\.\d{1,2})?$/', $raw ) ) {
		return '';
	}
	return number_format( (float) $raw, 2, '.', '' );
}

/**
 * @return string Site-formatted m/d/yy, or an empty string when the date is not usable.
 */
function emerson_recommit_date( string $raw ): string {
	$raw = trim( $raw );
	$dt  = DateTimeImmutable::createFromFormat( '!Y-m-d', $raw, wp_timezone() );
	if ( ! $dt || $dt->format( 'Y-m-d' ) !== $raw ) {
		return '';
	}
	$year = (int) $dt->format( 'Y' );
	if ( $year < 2000 || $year > 2100 ) {
		return '';
	}
	return wp_date( 'n/j/y', $dt->getTimestamp() );
}

function emerson_recommit_limited( string $bucket = '' ): bool {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	$key = 'emerson_recommit_' . md5( $bucket !== '' ? $bucket : $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= EMERSON_RECOMMIT_MAX_PER_HOUR ) {
		return true;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	return false;
}

/** Same-site Origin or Referer (blocks cross-site use of a scraped homepage nonce). */
function emerson_recommit_from_this_site(): bool {
	$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	if ( ! is_string( $home ) || $home === '' ) {
		return false;
	}
	foreach ( array( 'HTTP_ORIGIN', 'HTTP_REFERER' ) as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) {
			continue;
		}
		$host = wp_parse_url( (string) wp_unslash( $_SERVER[ $header ] ), PHP_URL_HOST );
		if ( is_string( $host ) && strcasecmp( $host, $home ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * @param string $png Raw PNG bytes.
 */
function emerson_recommit_has_ink( string $png ): bool {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return strlen( $png ) > 80;
	}
	$image = imagecreatefromstring( $png );
	if ( false === $image ) {
		return false;
	}
	$width  = imagesx( $image );
	$height = imagesy( $image );
	$ink    = false;
	for ( $y = 0; $y < $height && ! $ink; $y += 3 ) {
		for ( $x = 0; $x < $width; $x += 3 ) {
			$rgb = imagecolorat( $image, $x, $y );
			$r   = ( $rgb >> 16 ) & 255;
			$g   = ( $rgb >> 8 ) & 255;
			$b   = $rgb & 255;
			if ( $r < 250 || $g < 250 || $b < 250 ) {
				$ink = true;
				break;
			}
		}
	}
	imagedestroy( $image );
	return $ink;
}

function emerson_recommit_submit(): void {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'emerson_recommit' ) ) {
		wp_send_json_error( array( 'message' => 'The form expired. Reload the page and try again.' ), 403 );
	}
	if ( ! emerson_recommit_from_this_site() ) {
		wp_send_json_error( array( 'message' => 'The form expired. Reload the page and try again.' ), 403 );
	}
	if ( emerson_recommit_limited() ) {
		wp_send_json_error( array( 'message' => 'Too many submissions from this connection. Please try again in an hour, or email the office.' ), 429 );
	}

	$company = isset( $_POST['company'] ) ? trim( (string) wp_unslash( $_POST['company'] ) ) : '';
	if ( '' !== $company ) {
		wp_send_json_success( array( 'message' => 'Sent. Thank you for your re-commitment.' ) );
	}

	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( '' === $name || strlen( $name ) > 80 ) {
		wp_send_json_error( array( 'message' => 'Enter your name.' ) );
	}

	$amount = emerson_recommit_amount( isset( $_POST['pledge'] ) ? (string) wp_unslash( $_POST['pledge'] ) : '' );
	if ( '' === $amount ) {
		wp_send_json_error( array( 'message' => 'Enter your pledge as a dollar amount with cents, like 7.77 or 5.00.' ) );
	}

	$frequency = isset( $_POST['frequency'] ) ? sanitize_key( wp_unslash( $_POST['frequency'] ) ) : '';
	if ( ! in_array( $frequency, array( 'monthly', 'quarterly', 'annually' ), true ) ) {
		wp_send_json_error( array( 'message' => 'Choose monthly, quarterly, or annually.' ) );
	}

	$continue = emerson_recommit_date( isset( $_POST['continue_on'] ) ? (string) wp_unslash( $_POST['continue_on'] ) : '' );
	if ( '' === $continue ) {
		wp_send_json_error( array( 'message' => 'Choose the date payments continue.' ) );
	}

	$phone_raw = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$digits    = preg_replace( '/\D/', '', $phone_raw );
	if ( strlen( $digits ) < 10 || strlen( $digits ) > 15 ) {
		wp_send_json_error( array( 'message' => 'Enter a valid phone number.' ) );
	}

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'Enter a valid email address.' ) );
	}
	if ( emerson_recommit_limited( 'email:' . strtolower( $email ) ) ) {
		wp_send_json_error( array( 'message' => 'Too many submissions for this email. Please try again in an hour, or email the office.' ), 429 );
	}

	$signed_on = emerson_recommit_date( isset( $_POST['signed_on'] ) ? (string) wp_unslash( $_POST['signed_on'] ) : '' );
	if ( '' === $signed_on ) {
		wp_send_json_error( array( 'message' => 'Choose the date you signed.' ) );
	}

	$signature = isset( $_POST['signature'] ) ? (string) wp_unslash( $_POST['signature'] ) : '';
	if ( ! preg_match( '#^data:image/png;base64,#', $signature ) ) {
		wp_send_json_error( array( 'message' => 'Draw your signature.' ) );
	}
	$png = base64_decode( substr( $signature, strlen( 'data:image/png;base64,' ) ), true );
	if ( ! is_string( $png ) || strlen( $png ) < 80 || strlen( $png ) > 400000 || ! str_starts_with( $png, "\x89PNG\r\n\x1a\n" ) || ! emerson_recommit_has_ink( $png ) ) {
		wp_send_json_error( array( 'message' => 'Draw your signature.' ) );
	}

	$file = get_temp_dir() . 'emerson-recommit-' . wp_generate_password( 12, false ) . '.png';
	if ( false === file_put_contents( $file, $png ) ) {
		wp_send_json_error( array( 'message' => 'The form could not be sent. Please try again, or email the office.' ) );
	}

	$body = sprintf(
		"I %1\$s commit to my pledge of $%2\$s for the fiscal year 2025-2026 and will pay this %3\$s continued on %4\$s.\n\nThis is to ensure the financial security and commitment Emerson UU Chapel is intending to take on this fiscal year.\n\nPhone: %5\$s\nEmail: %6\$s\n\nSigned: see the attached signature.\nDate signed: %7\$s\n",
		$name,
		$amount,
		$frequency,
		$continue,
		$phone_raw,
		$email,
		$signed_on
	);

	$sent = wp_mail(
		EMERSON_RECOMMIT_OFFICE,
		sprintf( 'Re-commitment: %s', $name ),
		$body,
		array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . $email,
		),
		array( $file )
	);
	wp_delete_file( $file );

	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => 'The form could not be sent. Please try again, or email the office.' ) );
	}
	wp_send_json_success( array( 'message' => 'Sent. Thank you for your re-commitment.' ) );
}
add_action( 'wp_ajax_emerson_recommit', 'emerson_recommit_submit' );
add_action( 'wp_ajax_nopriv_emerson_recommit', 'emerson_recommit_submit' );
