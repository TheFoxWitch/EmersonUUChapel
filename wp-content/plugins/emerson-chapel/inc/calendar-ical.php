<?php
/**
 * Single-event iCal download for Church Admin calendar links.
 *
 * CA’s `church_admin_premium_ical()` emits invalid DTSTART/DTEND
 * (`VALUE=DATE` with a time) and poorly folded DESCRIPTION lines. iPhone
 * Calendar often opens that as an untitled “New Event”. Emerson serves a
 * corrected .ics on the same `?church_admin_download=ical&date_id=` URL
 * (init priority 5, before CA) with SUMMARY = the calendar event title and
 * a matching filename.
 */

declare( strict_types=1 );

/**
 * Escape text for an iCalendar TEXT value (RFC 5545).
 */
function emerson_ical_escape_text( string $value ): string {
	$value = str_replace( array( "\\", ";", ",", "\r\n", "\n", "\r" ), array( "\\\\", "\\;", "\\,", '\\n', '\\n', '\\n' ), $value );
	return $value;
}

/**
 * Fold a long iCalendar content line (75 octets, CRLF + space).
 */
function emerson_ical_fold( string $line ): string {
	$out    = '';
	$remain = $line;
	while ( strlen( $remain ) > 75 ) {
		$out   .= substr( $remain, 0, 75 ) . "\r\n ";
		$remain = substr( $remain, 75 );
	}
	return $out . $remain;
}

/**
 * Safe download filename from an event title.
 */
function emerson_ical_filename( string $title ): string {
	$slug = sanitize_title( $title );
	if ( $slug === '' ) {
		$slug = 'emerson-event';
	}
	return $slug . '.ics';
}

/**
 * Build one VEVENT calendar for a Church Admin date_id.
 *
 * @return string|null ICS body, or null when the date is missing.
 */
function emerson_ical_body( int $date_id ): ?string {
	global $wpdb;

	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT a.date_id, a.title, a.start_date, a.start_time, a.end_time, a.location, a.description, a.link, b.category
			FROM ' . $wpdb->prefix . 'church_admin_calendar_date a
			LEFT JOIN ' . $wpdb->prefix . 'church_admin_calendar_category b ON a.cat_id = b.cat_id
			WHERE a.date_id = %d',
			$date_id
		)
	);
	if ( ! $row ) {
		return null;
	}

	$title = trim( (string) $row->title );
	if ( $title === '' ) {
		$title = __( 'Emerson UU Chapel event', 'emerson-chapel' );
	}

	$start_date = preg_replace( '/\D/', '', (string) $row->start_date );
	$start_time = preg_replace( '/\D/', '', (string) $row->start_time );
	$end_time   = preg_replace( '/\D/', '', (string) $row->end_time );
	if ( strlen( $start_date ) < 8 ) {
		return null;
	}
	$start_date = substr( $start_date, 0, 8 );
	$start_time = str_pad( substr( $start_time !== '' ? $start_time : '000000', 0, 6 ), 6, '0' );
	$end_time   = str_pad( substr( $end_time !== '' ? $end_time : $start_time, 0, 6 ), 6, '0' );

	$uid = sprintf(
		'emerson-ca-date-%d@%s',
		(int) $row->date_id,
		wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'emersonuuchapel.org'
	);

	$lines   = array();
	$lines[] = 'BEGIN:VCALENDAR';
	$lines[] = 'VERSION:2.0';
	$lines[] = 'PRODID:-//Emerson UU Chapel//Calendar//EN';
	$lines[] = 'CALSCALE:GREGORIAN';
	$lines[] = 'METHOD:PUBLISH';
	$lines[] = 'BEGIN:VEVENT';
	$lines[] = 'UID:' . $uid;
	$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
	/* Floating local time — do not use VALUE=DATE with a clock time (breaks iOS). */
	$lines[] = 'DTSTART:' . $start_date . 'T' . $start_time;
	$lines[] = 'DTEND:' . $start_date . 'T' . $end_time;
	$lines[] = 'SUMMARY:' . emerson_ical_escape_text( $title );
	if ( trim( (string) $row->location ) !== '' ) {
		$lines[] = 'LOCATION:' . emerson_ical_escape_text( (string) $row->location );
	}
	if ( trim( (string) $row->description ) !== '' ) {
		$lines[] = 'DESCRIPTION:' . emerson_ical_escape_text( (string) $row->description );
	}
	if ( ! empty( $row->category ) ) {
		$lines[] = 'CATEGORIES:' . emerson_ical_escape_text( (string) $row->category );
	}
	if ( ! empty( $row->link ) ) {
		$lines[] = 'URL:' . esc_url_raw( (string) $row->link );
	}
	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';

	$folded = array();
	foreach ( $lines as $line ) {
		$folded[] = emerson_ical_fold( $line );
	}
	return implode( "\r\n", $folded ) . "\r\n";
}

add_action(
	'init',
	static function (): void {
		$download = isset( $_GET['church_admin_download'] ) ? sanitize_key( wp_unslash( $_GET['church_admin_download'] ) ) : '';
		if ( $download !== 'ical' ) {
			return;
		}
		$date_id = isset( $_GET['date_id'] ) ? (int) $_GET['date_id'] : 0;
		if ( $date_id < 1 ) {
			return;
		}

		$body = emerson_ical_body( $date_id );
		if ( $body === null ) {
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'No calendar date found.', 'emerson-chapel' ), esc_html__( 'Not found' ), array( 'response' => 404 ) );
		}

		global $wpdb;
		$title = (string) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT title FROM ' . $wpdb->prefix . 'church_admin_calendar_date WHERE date_id = %d',
				$date_id
			)
		);
		$filename = emerson_ical_filename( $title !== '' ? $title : 'emerson-event' );

		nocache_headers();
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ICS text body.
		exit;
	},
	5
);
