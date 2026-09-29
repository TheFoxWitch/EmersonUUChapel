<?php
/**
 * "This Week at Emerson" newsletter sign-up.
 *
 * - Homepage pop-up for logged-out visitors, and the [emerson_newsletter_form] shortcode for pages.
 * - Sign-ups are held for 7 days until the visitor clicks the emailed confirmation link.
 * - Confirmed subscribers are saved to Church Admin as member type "Mailing List" (hidden from the
 *   member directory) and the office is emailed so they can be added to Mailchimp.
 */

declare( strict_types=1 );

const EMERSON_NL_OFFICE_EMAIL   = 'office@emersonuuchapel.org';
const EMERSON_NL_MEMBER_TYPE_ID = 1;
const EMERSON_NL_PENDING_TTL    = 7 * DAY_IN_SECONDS;
const EMERSON_NL_RESEND_WAIT    = 10 * MINUTE_IN_SECONDS;
const EMERSON_NL_MAX_PER_HOUR   = 5;
const EMERSON_NL_MIN_SECONDS    = 2;
const EMERSON_NL_MAX_NAME       = 60;
const EMERSON_NL_MAX_EMAIL      = 100;

// WPForms "Newcomer Information Form" (post 351). Its fields are found by type and label, not by ID.
const EMERSON_NL_NEWCOMER_FORM_ID = 351;

function emerson_nl_message( string $code ): string {
	$messages = array(
		'sent'      => __( 'Almost done! Check your inbox for an email from us and click the confirmation link. It expires in 7 days.' ),
		'invalid'   => __( 'Please enter your first name and a valid email address.' ),
		'limit'     => __( 'Too many sign-up attempts from your connection. Please try again in an hour.' ),
		'confirmed' => __( 'Thank you! You\'re subscribed to This Week at Emerson.' ),
		'expired'   => __( 'That confirmation link has expired or was already used. If you already confirmed, you\'re all set. Otherwise, please sign up again.' ),
	);

	return $messages[ $code ] ?? '';
}

function emerson_nl_form_html( string $source ): string {
	static $instance = 0;
	++$instance;
	$id     = 'emerson-nl-' . $instance;
	$return = is_singular() ? get_permalink() : home_url( '/' );
	$code   = isset( $_GET['newsletter'] ) ? sanitize_key( wp_unslash( $_GET['newsletter'] ) ) : '';
	$notice = in_array( $code, array( 'sent', 'invalid', 'limit' ), true ) ? emerson_nl_message( $code ) : '';
	$status = 'sent' === $code ? 'success' : 'error';

	ob_start();
	?>
	<form class="emerson-nl-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" novalidate>
		<input type="hidden" name="action" value="emerson_newsletter_signup" />
		<input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>" />
		<input type="hidden" name="return" value="<?php echo esc_url( (string) $return ); ?>" />
		<input type="hidden" name="ts" value="<?php echo esc_attr( (string) time() ); ?>" />
		<div class="emerson-nl-form__row">
			<p class="emerson-nl-form__field">
				<label for="<?php echo esc_attr( $id ); ?>-first">First name <span aria-hidden="true">*</span></label>
				<input id="<?php echo esc_attr( $id ); ?>-first" type="text" name="first_name" autocomplete="given-name" maxlength="<?php echo (int) EMERSON_NL_MAX_NAME; ?>" required />
			</p>
			<p class="emerson-nl-form__field">
				<label for="<?php echo esc_attr( $id ); ?>-last">Last name</label>
				<input id="<?php echo esc_attr( $id ); ?>-last" type="text" name="last_name" autocomplete="family-name" maxlength="<?php echo (int) EMERSON_NL_MAX_NAME; ?>" />
			</p>
		</div>
		<p class="emerson-nl-form__field">
			<label for="<?php echo esc_attr( $id ); ?>-email">Email address <span aria-hidden="true">*</span></label>
			<input id="<?php echo esc_attr( $id ); ?>-email" type="email" name="email" autocomplete="email" maxlength="<?php echo (int) EMERSON_NL_MAX_EMAIL; ?>" required />
		</p>
		<p class="emerson-nl-hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $id ); ?>-website">Leave this field empty</label>
			<input id="<?php echo esc_attr( $id ); ?>-website" type="text" name="website" tabindex="-1" autocomplete="off" />
		</p>
		<p class="emerson-nl-form__actions">
			<button type="submit" class="emerson-nl-form__submit">Subscribe</button>
		</p>
		<p class="emerson-nl-form__message<?php echo $notice ? ' is-' . esc_attr( $status ) : ''; ?>" role="status" aria-live="polite"><?php echo esc_html( $notice ); ?></p>
	</form>
	<?php
	return (string) ob_get_clean();
}

add_shortcode(
	'emerson_newsletter_form',
	static function (): string {
		return '<div class="emerson-nl-inline" id="emerson-newsletter">' . emerson_nl_form_html( 'page' ) . '</div>';
	}
);

add_action(
	'wp_footer',
	static function (): void {
		if ( is_front_page() && ! is_user_logged_in() ) {
			?>
			<dialog id="emerson-newsletter-popup" class="emerson-nl-dialog" aria-labelledby="emerson-nl-popup-title">
				<div class="emerson-nl-dialog__inner">
					<button type="button" class="emerson-nl-dialog__close" data-emerson-nl-close aria-label="Close">&times;</button>
					<h2 id="emerson-nl-popup-title" class="emerson-nl-dialog__title">Stay connected</h2>
					<p class="emerson-nl-dialog__intro">Get <em>This Week at Emerson</em>, our weekly newsletter with worship details, upcoming events and news, in your inbox.</p>
					<?php echo emerson_nl_form_html( 'popup' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the function. ?>
					<p class="emerson-nl-dialog__fine">We only use your email for the newsletter. Unsubscribe any time. <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Privacy Policy</a></p>
					<p class="emerson-nl-dialog__decline"><button type="button" class="emerson-nl-dialog__no" data-emerson-nl-close>No thanks</button></p>
				</div>
			</dialog>
			<?php
		}

		$code = isset( $_GET['newsletter'] ) ? sanitize_key( wp_unslash( $_GET['newsletter'] ) ) : '';
		if ( in_array( $code, array( 'confirmed', 'expired' ), true ) ) {
			?>
			<div class="emerson-nl-toast is-<?php echo 'confirmed' === $code ? 'success' : 'error'; ?>" role="status" data-emerson-nl-result="<?php echo esc_attr( $code ); ?>">
				<p><?php echo esc_html( emerson_nl_message( $code ) ); ?></p>
				<button type="button" class="emerson-nl-toast__close" aria-label="Dismiss">&times;</button>
			</div>
			<?php
		}
	}
);

/**
 * Ends the response so the visitor isn't kept waiting, then lets PHP carry on (to send email).
 * NetSol's SMTP server takes about 10 seconds per message.
 *
 * Call after the status and headers are set. Covers PHP-FPM, LiteSpeed and Apache mod_php.
 */
function emerson_nl_release_visitor( string $body = '' ): void {
	ignore_user_abort( true );
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	// A compressed response has no known length, so mod_php would hold the connection open.
	if ( function_exists( 'apache_setenv' ) ) {
		apache_setenv( 'no-gzip', '1' );
	}
	ini_set( 'zlib.output_compression', '0' );

	header( 'Content-Length: ' . strlen( $body ) );
	header( 'Connection: close' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON or empty.
	flush();

	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	} elseif ( function_exists( 'litespeed_finish_request' ) ) {
		litespeed_finish_request();
	}
}

/**
 * @param array<string, mixed> $input Raw $_POST.
 * @return array{code: string, message: string, status: int, send?: callable}
 */
function emerson_nl_process_signup( array $input ): array {
	$input = wp_unslash( $input );
	$ok    = array(
		'code'    => 'sent',
		'message' => emerson_nl_message( 'sent' ),
		'status'  => 200,
	);

	// Bots: filled the hidden field or submitted faster than a person can type. Pretend it worked.
	$rendered = (int) ( $input['ts'] ?? 0 );
	if ( '' !== trim( (string) ( $input['website'] ?? '' ) ) || ( $rendered > 0 && time() - $rendered < EMERSON_NL_MIN_SECONDS ) ) {
		return $ok;
	}

	$ip_key   = 'emerson_nl_ip_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$attempts = (int) get_transient( $ip_key );
	if ( $attempts >= EMERSON_NL_MAX_PER_HOUR ) {
		return array(
			'code'    => 'limit',
			'message' => emerson_nl_message( 'limit' ),
			'status'  => 429,
		);
	}
	set_transient( $ip_key, $attempts + 1, HOUR_IN_SECONDS );

	$first  = sanitize_text_field( (string) ( $input['first_name'] ?? '' ) );
	$last   = sanitize_text_field( (string) ( $input['last_name'] ?? '' ) );
	$email  = sanitize_email( (string) ( $input['email'] ?? '' ) );
	$errors = array();
	if ( '' === $first ) {
		$errors[] = __( 'Please enter your first name.' );
	}
	if ( mb_strlen( $first ) > EMERSON_NL_MAX_NAME || mb_strlen( $last ) > EMERSON_NL_MAX_NAME ) {
		$errors[] = sprintf( __( 'Names must be %d characters or fewer.' ), EMERSON_NL_MAX_NAME );
	}
	if ( '' === $email || ! is_email( $email ) || strlen( $email ) > EMERSON_NL_MAX_EMAIL ) {
		$errors[] = __( 'Please enter a valid email address.' );
	}
	if ( $errors ) {
		return array(
			'code'    => 'invalid',
			'message' => implode( ' ', $errors ),
			'status'  => 400,
		);
	}

	$token = emerson_nl_start_pending( $first, $last, $email, 'popup' === ( $input['source'] ?? '' ) ? 'popup' : 'page' );
	if ( $token ) {
		$ok['send'] = static function () use ( $token ): void {
			emerson_nl_send_confirmation( $token );
		};
	}
	return $ok;
}

function emerson_nl_source_label( string $source ): string {
	$labels = array(
		'popup'    => 'homepage pop-up',
		'page'     => 'Ways to Connect page',
		'newcomer' => 'Newcomer Information form',
	);
	return $labels[ $source ] ?? $source;
}

/**
 * Holds a validated sign-up until the emailed link is clicked.
 *
 * @return string|null The confirmation token, or null if a confirmation email went to this address in the last 10 minutes.
 */
function emerson_nl_start_pending( string $first, string $last, string $email, string $source ): ?string {
	$email      = strtolower( $email );
	$resend_key = 'emerson_nl_sent_' . md5( $email );
	if ( get_transient( $resend_key ) ) {
		return null;
	}

	$token = wp_generate_password( 32, false, false );
	set_transient(
		'emerson_nl_pending_' . $token,
		array(
			'first_name' => $first,
			'last_name'  => $last,
			'email'      => $email,
			'source'     => $source,
			'created'    => time(),
		),
		EMERSON_NL_PENDING_TTL
	);

	// The resend wait starts now so a double-click can't send two emails while the first is still going.
	set_transient( $resend_key, 1, EMERSON_NL_RESEND_WAIT );
	return $token;
}

function emerson_nl_send_confirmation( string $token ): void {
	$data = get_transient( 'emerson_nl_pending_' . $token );
	if ( ! is_array( $data ) ) {
		return;
	}

	$link = add_query_arg( 'emerson_newsletter_confirm', $token, home_url( '/' ) );
	$body = sprintf(
		"Hi %1\$s,\n\nThanks for signing up for This Week at Emerson, Emerson Chapel's weekly email newsletter.\n\nPlease confirm your email address by clicking this link:\n\n%2\$s\n\nThe link expires in 7 days. If you didn't sign up, just ignore this email and you won't be added.\n\nEmerson Unitarian Universalist Chapel\n%3\$s\n",
		$data['first_name'],
		$link,
		home_url( '/' )
	);

	$sent = wp_mail(
		$data['email'],
		__( 'Please confirm your subscription to This Week at Emerson' ),
		$body,
		array( 'Reply-To: ' . EMERSON_NL_OFFICE_EMAIL )
	);
	if ( ! $sent ) {
		// Let them try again straight away; the link they were promised was never sent.
		delete_transient( 'emerson_nl_pending_' . $token );
		delete_transient( 'emerson_nl_sent_' . md5( $data['email'] ) );
		error_log( 'Emerson newsletter: the confirmation email could not be sent. The sign-up was discarded.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
add_action( 'emerson_nl_send_confirmation', 'emerson_nl_send_confirmation' );

/**
 * Finds the Newcomer form's first name field, first email field and the choice field whose label mentions "newsletter".
 *
 * @param array<int|string, array<string, mixed>> $fields Field definitions from the form.
 * @return array{name: int|string|null, email: int|string|null, answer: int|string|null}
 */
function emerson_nl_newcomer_field_ids( array $fields ): array {
	$ids = array(
		'name'   => null,
		'email'  => null,
		'answer' => null,
	);
	foreach ( $fields as $id => $field ) {
		$type = (string) ( $field['type'] ?? '' );
		if ( null === $ids['name'] && 'name' === $type ) {
			$ids['name'] = $id;
		} elseif ( null === $ids['email'] && 'email' === $type ) {
			$ids['email'] = $id;
		} elseif ( null === $ids['answer'] && in_array( $type, array( 'radio', 'select', 'checkbox' ), true ) && false !== stripos( (string) ( $field['label'] ?? '' ), 'newsletter' ) ) {
			$ids['answer'] = $id;
		}
	}
	return $ids;
}

/**
 * @return string[] What's missing from the Newcomer form for the newsletter hand-off, if anything.
 */
function emerson_nl_newcomer_form_problems(): array {
	$post = get_post( EMERSON_NL_NEWCOMER_FORM_ID );
	if ( ! $post || 'wpforms' !== $post->post_type || 'trash' === $post->post_status ) {
		return array( sprintf( 'the form (ID %d) no longer exists', EMERSON_NL_NEWCOMER_FORM_ID ) );
	}
	$data     = json_decode( $post->post_content, true );
	$ids      = emerson_nl_newcomer_field_ids( (array) ( $data['fields'] ?? array() ) );
	$problems = array();
	if ( null === $ids['name'] ) {
		$problems[] = 'there is no Name field';
	}
	if ( null === $ids['email'] ) {
		$problems[] = 'there is no Email field';
	}
	if ( null === $ids['answer'] ) {
		$problems[] = 'no multiple-choice question mentions "newsletter"';
	} elseif ( ! in_array( 'yes', array_map( static fn( $c ) => strtolower( trim( (string) ( $c['label'] ?? '' ) ) ), (array) ( $data['fields'][ $ids['answer'] ]['choices'] ?? array() ) ), true ) ) {
		$problems[] = 'the newsletter question has no "Yes" choice';
	}
	return $problems;
}

add_action(
	'admin_notices',
	static function (): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$problems = emerson_nl_newcomer_form_problems();
		if ( $problems ) {
			printf(
				'<div class="notice notice-warning"><p><strong>Newsletter sign-up from the Newcomer form is not working:</strong> %s. Answering "Yes" will not send the confirmation email until this is fixed (see <code>inc/newsletter.php</code> in the emerson-uuchapel theme).</p></div>',
				esc_html( implode( '; ', $problems ) )
			);
		}
	}
);

/*
 * Newcomer Information form (WPForms): "Yes" to the newsletter question starts the same email confirmation.
 * The email goes through Action Scheduler so the form's "Thanks" isn't held up by the slow SMTP server.
 */
add_action(
	'wpforms_process_complete',
	static function ( $fields, $entry, $form_data ): void {
		if ( EMERSON_NL_NEWCOMER_FORM_ID !== (int) ( $form_data['id'] ?? 0 ) ) {
			return;
		}
		$ids = emerson_nl_newcomer_field_ids( (array) ( $form_data['fields'] ?? array() ) );
		if ( null === $ids['name'] || null === $ids['email'] || null === $ids['answer'] ) {
			error_log( 'Emerson newsletter: the Newcomer form is missing its name, email or newsletter question, so a "Yes" could not be checked.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			return;
		}
		if ( 'yes' !== strtolower( trim( (string) ( $fields[ $ids['answer'] ]['value'] ?? '' ) ) ) ) {
			return;
		}

		$name  = $fields[ $ids['name'] ] ?? array();
		$first = mb_substr( sanitize_text_field( (string) ( $name['first'] ?? '' ) ), 0, EMERSON_NL_MAX_NAME );
		$last  = mb_substr( sanitize_text_field( (string) ( $name['last'] ?? '' ) ), 0, EMERSON_NL_MAX_NAME );
		$email = sanitize_email( (string) ( $fields[ $ids['email'] ]['value'] ?? '' ) );
		if ( '' === $first || ! is_email( $email ) || strlen( $email ) > EMERSON_NL_MAX_EMAIL ) {
			return;
		}

		$token = emerson_nl_start_pending( $first, $last, $email, 'newcomer' );
		if ( ! $token ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'emerson_nl_send_confirmation', array( $token ), 'emerson-newsletter' );
		} else {
			emerson_nl_send_confirmation( $token );
		}
	},
	10,
	3
);

function emerson_nl_handle_signup(): void {
	$result = emerson_nl_process_signup( $_POST );

	if ( ! empty( $_POST['ajax'] ) ) {
		status_header( $result['status'] );
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		nocache_headers();
		emerson_nl_release_visitor(
			(string) wp_json_encode(
				array(
					'ok'      => 200 === $result['status'],
					'message' => $result['message'],
				)
			)
		);
	} else {
		$return = wp_validate_redirect( esc_url_raw( wp_unslash( (string) ( $_POST['return'] ?? '' ) ) ), home_url( '/' ) ) ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( 'newsletter', $result['code'], $return ) . '#emerson-newsletter', 303 );
		emerson_nl_release_visitor();
	}

	if ( isset( $result['send'] ) ) {
		( $result['send'] )();
	}
	exit;
}
add_action( 'admin_post_nopriv_emerson_newsletter_signup', 'emerson_nl_handle_signup' );
add_action( 'admin_post_emerson_newsletter_signup', 'emerson_nl_handle_signup' );

/**
 * Saves a confirmed subscriber to Church Admin, unless their email is already there.
 *
 * @param array<string, mixed> $data Pending sign-up.
 * @return string One-line summary for the office email.
 */
function emerson_nl_save_to_church_admin( array $data ): string {
	global $wpdb;

	$people    = $wpdb->prefix . 'church_admin_people';
	$household = $wpdb->prefix . 'church_admin_household';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $people ) ) !== $people ) {
		return 'Church Admin is not active, so they were NOT saved to the directory.';
	}

	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT p.people_id, p.first_name, p.last_name, p.email_send, t.member_type
			FROM {$people} p LEFT JOIN {$wpdb->prefix}church_admin_member_types t ON t.member_type_id = p.member_type_id
			WHERE LOWER(p.email) = %s LIMIT 1",
			$data['email']
		)
	);
	if ( $existing ) {
		return sprintf(
			'Already in Church Admin as %s %s (member type: %s; church emails %s). No new record was created.',
			$existing->first_name,
			$existing->last_name,
			$existing->member_type ?: 'none',
			(int) $existing->email_send ? 'on' : 'OFF'
		);
	}

	$today = current_time( 'mysql' );
	$wpdb->insert(
		$household,
		array(
			'privacy'          => 1,
			'member_type_id'   => EMERSON_NL_MEMBER_TYPE_ID,
			'first_registered' => current_time( 'Y-m-d' ),
		),
		array( '%d', '%d', '%s' )
	);
	$household_id = (int) $wpdb->insert_id;

	$inserted = $household_id && $wpdb->insert(
		$people,
		array(
			'first_name'        => $data['first_name'],
			'last_name'         => $data['last_name'],
			'email'             => $data['email'],
			'member_type_id'    => EMERSON_NL_MEMBER_TYPE_ID,
			'people_type_id'    => 1,
			'head_of_household' => 1,
			'household_id'      => $household_id,
			'email_send'        => 1,
			'news_send'         => 1,
			'show_me'           => 0,
			'gdpr_reason'       => 'Confirmed newsletter sign-up (' . emerson_nl_source_label( (string) $data['source'] ) . ')',
			'first_registered'  => $today,
			'kidswork_override' => 0,
			'other_hope_team'   => '',
		),
		array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
	);

	if ( ! $inserted ) {
		if ( $household_id ) {
			$wpdb->delete( $household, array( 'household_id' => $household_id ), array( '%d' ) );
		}
		return 'Saving to Church Admin FAILED (database error), so please add them by hand.';
	}

	return sprintf( 'Added to Church Admin as member type "Mailing List" (household #%d, hidden from the member directory).', $household_id );
}

add_action(
	'template_redirect',
	static function (): void {
		if ( ! isset( $_GET['emerson_newsletter_confirm'] ) ) {
			return;
		}

		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['emerson_newsletter_confirm'] ) );
		$key   = 'emerson_nl_pending_' . substr( (string) $token, 0, 32 );
		$data  = 32 === strlen( (string) $token ) ? get_transient( $key ) : false;

		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( 'newsletter', 'expired', home_url( '/' ) ), 303 );
			exit;
		}
		delete_transient( $key );

		$church_admin = emerson_nl_save_to_church_admin( $data );
		$name         = trim( $data['first_name'] . ' ' . $data['last_name'] );

		wp_safe_redirect( add_query_arg( 'newsletter', 'confirmed', home_url( '/' ) ), 303 );
		emerson_nl_release_visitor();

		$sent = wp_mail(
			EMERSON_NL_OFFICE_EMAIL,
			sprintf( 'New newsletter subscriber: %s', $name ),
			sprintf(
				"%1\$s confirmed their subscription to This Week at Emerson.\n\nName: %1\$s\nEmail: %2\$s\nSigned up from: %3\$s\nConfirmed: %4\$s\n\nChurch Admin: %5\$s\n\nNext step: add them to the This Week at Emerson audience in Mailchimp.\n",
				$name,
				$data['email'],
				emerson_nl_source_label( (string) $data['source'] ),
				wp_date( 'F j, Y g:i a' ),
				$church_admin
			),
			array( 'Reply-To: ' . $data['email'] )
		);
		if ( ! $sent ) {
			error_log( 'Emerson newsletter: the office notification for a confirmed subscriber could not be sent. They are saved in Church Admin.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}

		do_action( 'emerson_newsletter_confirmed', $data );
		exit;
	}
);
