<?php
/**
 * Homepage re-commitment form.
 *
 * The button sits with the pledge and PayPal-fees buttons. It opens Microsoft's form
 * inside the dialog on this page.
 */

declare( strict_types=1 );

const EMERSON_RECOMMIT_OFFICE       = 'office@emersonuuchapel.org';
const EMERSON_RECOMMIT_MAX_PER_HOUR = 8;
const EMERSON_RECOMMIT_FORM_OPTION   = 'emerson_recommit_form_url';
const EMERSON_RECOMMIT_FORM_DEFAULT  = 'https://forms.cloud.microsoft/r/59mVXw4vPP';
const EMERSON_RECOMMIT_LABEL_OPTION  = 'emerson_recommit_label';
const EMERSON_RECOMMIT_LABEL_DEFAULT = 'Re-commitment Form';

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
 * Add the button to the homepage row that already holds the pledge form.
 *
 * @param string               $content Rendered block HTML.
 * @param array<string, mixed> $block   Block data.
 */
function emerson_recommit_render_button( string $content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/buttons' || ! is_front_page() ) {
		return $content;
	}
	if ( ! str_contains( $content, 'pledge-button' ) || str_contains( $content, 'data-emerson-recommit' ) ) {
		return $content;
	}
	$button = '<div class="wp-block-button recommit-button"><button type="button" class="wp-block-button__link wp-element-button" data-emerson-recommit>' . esc_html( emerson_recommit_label() ) . '</button></div>';
	$pos    = strrpos( $content, '</div>' );
	if ( false === $pos ) {
		return $content . $button;
	}
	return substr( $content, 0, $pos ) . $button . substr( $content, $pos );
}
add_filter( 'render_block', 'emerson_recommit_render_button', 10, 2 );

function emerson_recommit_enqueue(): void {
	if ( ! is_front_page() ) {
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
}
add_action( 'wp_enqueue_scripts', 'emerson_recommit_enqueue' );

function emerson_recommit_dialog(): void {
	if ( ! is_front_page() ) {
		return;
	}
	$src   = emerson_recommit_embed_url();
	$label = emerson_recommit_label();
	?>
	<dialog id="emerson-recommit" class="emerson-recommit">
		<div class="emerson-recommit__bar">
			<h2><?php echo esc_html( $label ); ?></h2>
			<button type="button" class="emerson-recommit__close">Close</button>
		</div>
		<?php if ( $src !== '' ) : ?>
		<div class="emerson-recommit__fit">
			<iframe class="emerson-recommit__frame" title="<?php echo esc_attr( $label ); ?>" data-src="<?php echo esc_url( $src ); ?>"></iframe>
		</div>
		<?php else : ?>
		<p>The re-commitment form is not available right now.</p>
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
	$raw   = isset( $_POST['form_url'] ) ? wp_unslash( (string) $_POST['form_url'] ) : '';
	$url   = emerson_recommit_clean_form_url( $raw );
	$label = isset( $_POST['button_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['button_label'] ) ) : '';
	$label = trim( preg_replace( '/\s+/', ' ', $label ) ?? '' );
	if ( $label === '' ) {
		return 'Type the words that should appear on the homepage button.';
	}
	if ( mb_strlen( $label ) > 80 ) {
		return 'The button text is too long. Keep it to 80 characters.';
	}
	if ( ! emerson_recommit_is_form_url( $url ) ) {
		return 'That does not look like a Microsoft Forms link. Paste the address from the browser while the form is open.';
	}
	update_option( EMERSON_RECOMMIT_FORM_OPTION, $url, false );
	update_option( EMERSON_RECOMMIT_LABEL_OPTION, $label, false );
	return 'Saved. The homepage button will use this wording and open this form.';
}

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

function emerson_recommit_limited(): bool {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
	$key = 'emerson_recommit_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= EMERSON_RECOMMIT_MAX_PER_HOUR ) {
		return true;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
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
