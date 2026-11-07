<?php
/**
 * Marketing mailbox SMTP for Emerson-controlled welcome / marketing sends.
 *
 * WP Mail SMTP stays on webmaster@ for ordinary office mail. When a send is
 * marked as marketing (helper below, or a From: marketing@… header), Emerson
 * switches PHPMailer to the marketing SMTP account and From address after
 * WP Mail SMTP has applied its settings. Password comes from env / wp-config
 * constants — never from this file.
 *
 * Incoming IMAP for mailbox clients (not used by WordPress send):
 * mail.emersonuuchapel.org — prefer IMAP, not POP.
 */

declare( strict_types=1 );

const EMERSON_MARKETING_MAIL_ADDRESS = 'marketing@emersonuuchapel.org';

/** @var bool */
$GLOBALS['emerson_marketing_mail_active'] = false;

/**
 * Read a marketing mail setting: wp-config constant first, then process env.
 */
function emerson_marketing_mail_setting( string $constant, string $env, string $default = '' ): string {
	if ( defined( $constant ) ) {
		$value = constant( $constant );
		if ( is_string( $value ) && $value !== '' ) {
			return $value;
		}
	}
	$from_env = getenv( $env );
	if ( is_string( $from_env ) && $from_env !== '' ) {
		return $from_env;
	}
	return $default;
}

/**
 * @return array{host:string,port:int,user:string,pass:string,from_email:string,from_name:string}|null
 */
function emerson_marketing_mail_config(): ?array {
	$user = emerson_marketing_mail_setting( 'EMERSON_MARKETING_SMTP_USER', 'EMERSON_MARKETING_SMTP_USER' );
	$pass = emerson_marketing_mail_setting( 'EMERSON_MARKETING_SMTP_PASS', 'EMERSON_MARKETING_SMTP_PASS' );
	if ( $user === '' || $pass === '' ) {
		return null;
	}
	$port = (int) emerson_marketing_mail_setting( 'EMERSON_MARKETING_SMTP_PORT', 'EMERSON_MARKETING_SMTP_PORT', '587' );
	if ( $port <= 0 ) {
		$port = 587;
	}
	$from = sanitize_email(
		emerson_marketing_mail_setting(
			'EMERSON_MARKETING_FROM_EMAIL',
			'EMERSON_MARKETING_FROM_EMAIL',
			EMERSON_MARKETING_MAIL_ADDRESS
		)
	);
	if ( ! is_email( $from ) ) {
		$from = EMERSON_MARKETING_MAIL_ADDRESS;
	}
	return array(
		// Default matches NetSol's cert (*.hostingplatform.com). Branded smtp.emersonuuchapel.org fails TLS verify in PHP.
		'host'       => emerson_marketing_mail_setting( 'EMERSON_MARKETING_SMTP_HOST', 'EMERSON_MARKETING_SMTP_HOST', 'netsol-smtp-oxcs.hostingplatform.com' ),
		'port'       => $port,
		'user'       => $user,
		'pass'       => $pass,
		'from_email' => $from,
		'from_name'  => emerson_marketing_mail_setting( 'EMERSON_MARKETING_FROM_NAME', 'EMERSON_MARKETING_FROM_NAME', 'Emerson UU Chapel' ),
	);
}

function emerson_marketing_mail_begin(): void {
	$GLOBALS['emerson_marketing_mail_active'] = true;
}

function emerson_marketing_mail_end(): void {
	$GLOBALS['emerson_marketing_mail_active'] = false;
}

function emerson_marketing_mail_is_active(): bool {
	return ! empty( $GLOBALS['emerson_marketing_mail_active'] );
}

/**
 * True when headers already ask for the marketing From address.
 *
 * @param array<string, mixed> $atts wp_mail arguments.
 */
function emerson_marketing_mail_requested_in_headers( array $atts ): bool {
	$headers = $atts['headers'] ?? '';
	$lines   = is_array( $headers ) ? $headers : preg_split( "/\r\n|\n/", (string) $headers );
	foreach ( $lines as $line ) {
		if ( preg_match( '/^\s*from\s*:/i', (string) $line )
			&& stripos( (string) $line, EMERSON_MARKETING_MAIL_ADDRESS ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Send with marketing SMTP + From. Local redirect catcher still applies.
 *
 * @param string|array<int, string> $to Recipient(s).
 * @param string|array<int, string> $headers Headers.
 * @param string|array<int, string> $attachments Attachments.
 */
function emerson_marketing_mail_send( $to, string $subject, string $message, $headers = '', $attachments = array() ): bool {
	emerson_marketing_mail_begin();
	try {
		$config = emerson_marketing_mail_config();
		if ( $config ) {
			$from_line = 'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>';
			if ( is_array( $headers ) ) {
				$headers = array_values(
					array_filter(
						$headers,
						static function ( $line ): bool {
							return ! preg_match( '/^\s*from\s*:/i', (string) $line );
						}
					)
				);
				$headers[] = $from_line;
			} else {
				$headers = trim( (string) $headers );
				$headers = preg_replace( '/^\s*from\s*:.*$/mi', '', $headers ) ?: '';
				$headers = trim( $headers );
				$headers = $headers === '' ? $from_line : $headers . "\n" . $from_line;
			}
		}
		return (bool) wp_mail( $to, $subject, $message, $headers, $attachments );
	} finally {
		emerson_marketing_mail_end();
	}
}

/**
 * @param array<string, mixed> $atts wp_mail arguments.
 * @return array<string, mixed>
 */
function emerson_marketing_mail_wp_mail_flag( array $atts ): array {
	if ( emerson_marketing_mail_requested_in_headers( $atts ) ) {
		emerson_marketing_mail_begin();
	}
	return $atts;
}
add_filter( 'wp_mail', 'emerson_marketing_mail_wp_mail_flag', 5 );

/**
 * After WP Mail SMTP (priority 10), re-point SMTP + From for marketing sends.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer.
 */
function emerson_marketing_mail_phpmailer( $phpmailer ): void {
	if ( ! emerson_marketing_mail_is_active() ) {
		return;
	}
	$config = emerson_marketing_mail_config();
	if ( ! $config ) {
		emerson_marketing_mail_end();
		return;
	}

	// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$phpmailer->Mailer     = 'smtp';
	$phpmailer->Host       = $config['host'];
	$phpmailer->Port       = $config['port'];
	$phpmailer->SMTPSecure = 'tls';
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = $config['user'];
	$phpmailer->Password   = $config['pass'];
	$phpmailer->setFrom( $config['from_email'], $config['from_name'], false );
	$phpmailer->Sender = $config['from_email'];
	// phpcs:enable

	emerson_marketing_mail_end();
}
add_action( 'phpmailer_init', 'emerson_marketing_mail_phpmailer', 20 );
