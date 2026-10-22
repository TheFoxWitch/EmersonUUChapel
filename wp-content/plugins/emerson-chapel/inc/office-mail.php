<?php
/**
 * Copies of messages sent to the chapel office.
 *
 * When WordPress emails office@emersonuuchapel.org, the people saved on the
 * Office mail screen get a blind copy. The list lives in the database.
 * On the local copy the mail catcher still sends only to the test mailbox.
 */

declare( strict_types=1 );

const EMERSON_OFFICE_MAIL_ADDRESS = 'office@emersonuuchapel.org';
const EMERSON_OFFICE_MAIL_OPTION  = 'emerson_office_mail_recipients';

/**
 * @return array<int, array{name:string, email:string}>
 */
function emerson_office_mail_people(): array {
	$stored = get_option( EMERSON_OFFICE_MAIL_OPTION, array() );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$people = array();
	foreach ( $stored as $person ) {
		if ( ! is_array( $person ) ) {
			continue;
		}
		$email = sanitize_email( (string) ( $person['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			continue;
		}
		$people[] = array(
			'name'  => sanitize_text_field( (string) ( $person['name'] ?? '' ) ),
			'email' => $email,
		);
	}
	return $people;
}

/**
 * @return array{0:array<int, array{name:string, email:string}>, 1:string} People, or an error message.
 */
function emerson_office_mail_parse( string $raw ): array {
	$people = array();
	$seen   = array();
	$lines  = preg_split( "/\r\n|\n|\r/", $raw ) ?: array();
	foreach ( $lines as $number => $line ) {
		$line = trim( $line );
		if ( $line === '' ) {
			continue;
		}
		$name  = '';
		$email = $line;
		if ( preg_match( '/^(.+?)\s*<([^>]+)>$/', $line, $match ) ) {
			$name  = sanitize_text_field( trim( $match[1], " \t\"'" ) );
			$email = $match[2];
		}
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return array( array(), 'Line ' . ( $number + 1 ) . ' is not an email address. Use Name <email@example.com>, or just the address.' );
		}
		$key = strtolower( $email );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$people[]     = array(
			'name'  => $name,
			'email' => $email,
		);
	}
	return array( $people, '' );
}

/**
 * @param array<int, array{name:string, email:string}> $people Saved people.
 */
function emerson_office_mail_text( array $people ): string {
	$lines = array();
	foreach ( $people as $person ) {
		$name    = trim( str_replace( array( ',', '<', '>', '"' ), '', $person['name'] ) );
		$lines[] = $name !== '' ? $name . ' <' . $person['email'] . '>' : $person['email'];
	}
	return implode( "\n", $lines );
}

/**
 * Email addresses already on the message, ignoring Reply-To.
 *
 * @param array<string, mixed> $atts wp_mail arguments.
 * @return array<int, string>
 */
function emerson_office_mail_recipients( array $atts ): array {
	$chunks = array();
	$to     = $atts['to'] ?? '';
	if ( is_array( $to ) ) {
		foreach ( $to as $item ) {
			$chunks[] = (string) $item;
		}
	} else {
		$chunks[] = (string) $to;
	}
	$headers = $atts['headers'] ?? '';
	$lines   = is_array( $headers ) ? $headers : preg_split( "/\r\n|\n/", (string) $headers );
	foreach ( $lines as $line ) {
		if ( preg_match( '/^\s*(cc|bcc)\s*:/i', (string) $line ) ) {
			$chunks[] = (string) $line;
		}
	}
	$found = array();
	foreach ( $chunks as $chunk ) {
		if ( ! preg_match_all( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $chunk, $matches ) ) {
			continue;
		}
		foreach ( $matches[0] as $email ) {
			$email = strtolower( $email );
			if ( is_email( $email ) ) {
				$found[ $email ] = $email;
			}
		}
	}
	return array_values( $found );
}

/**
 * @param array<string, mixed> $atts wp_mail arguments.
 * @return array<string, mixed>
 */
function emerson_office_mail_filter( array $atts ): array {
	$people = emerson_office_mail_people();
	if ( $people === array() ) {
		return $atts;
	}
	$already = emerson_office_mail_recipients( $atts );
	if ( ! in_array( strtolower( EMERSON_OFFICE_MAIL_ADDRESS ), $already, true ) ) {
		return $atts;
	}
	$copies = array();
	foreach ( $people as $person ) {
		$email = strtolower( $person['email'] );
		if ( $email === strtolower( EMERSON_OFFICE_MAIL_ADDRESS ) || in_array( $email, $already, true ) ) {
			continue;
		}
		$name = trim( str_replace( array( ',', '<', '>', '"' ), '', $person['name'] ) );
		$copies[] = $name !== '' ? $name . ' <' . $person['email'] . '>' : $person['email'];
	}
	if ( $copies === array() ) {
		return $atts;
	}
	$line    = 'Bcc: ' . implode( ', ', $copies );
	$headers = $atts['headers'] ?? '';
	if ( is_array( $headers ) ) {
		$headers[] = $line;
	} else {
		$headers = trim( (string) $headers );
		$headers = $headers === '' ? $line : $headers . "\n" . $line;
	}
	$atts['headers'] = $headers;
	return $atts;
}
add_filter( 'wp_mail', 'emerson_office_mail_filter', 10 );

function emerson_office_mail_can_manage(): bool {
	return current_user_can( 'manage_options' );
}

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Office mail',
			'Office mail',
			'manage_options',
			'emerson-office-mail',
			'emerson_office_mail_admin_page',
			'dashicons-email-alt',
			61
		);
	}
);

function emerson_office_mail_admin_save(): ?string {
	if ( ! emerson_office_mail_can_manage() || empty( $_POST['emerson_office_mail_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_office_mail_save' );
	$raw = isset( $_POST['recipients'] ) ? wp_unslash( (string) $_POST['recipients'] ) : '';
	list( $people, $error ) = emerson_office_mail_parse( $raw );
	if ( $error !== '' ) {
		return $error;
	}
	update_option( EMERSON_OFFICE_MAIL_OPTION, $people, false );
	$count = count( $people );
	if ( $count === 0 ) {
		return 'Saved. Messages to the office are not copied to anyone else.';
	}
	return 'Saved. Messages to the office are also sent to ' . $count . ' ' . ( 1 === $count ? 'person' : 'people' ) . '.';
}

function emerson_office_mail_admin_page(): void {
	if ( ! emerson_office_mail_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to edit the office mail list.' ) );
	}
	$notice = emerson_office_mail_admin_save();
	echo '<div class="wrap"><h1>Office mail</h1>';
	if ( $notice ) {
		$bad = 0 !== strpos( $notice, 'Saved.' );
		echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>When the site emails <strong>' . esc_html( EMERSON_OFFICE_MAIL_ADDRESS ) . '</strong>, the people below receive a copy. The office address still receives it. A message sent only to a member is not copied.</p>';
	echo '<p>One person per line: <code>Name &lt;email@example.com&gt;</code>. An address on its own is fine. Only administrators can add or remove people, then click <strong>Save</strong>.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_office_mail_save' );
	echo '<textarea name="recipients" id="emerson-office-mail" class="large-text code" rows="12" cols="60">' . esc_textarea( emerson_office_mail_text( emerson_office_mail_people() ) ) . '</textarea>';
	echo '<p class="submit"><button type="submit" name="emerson_office_mail_save" value="1" class="button button-primary">Save</button></p>';
	echo '</form></div>';
}
