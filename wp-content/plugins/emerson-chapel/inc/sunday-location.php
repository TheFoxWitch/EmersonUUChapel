<?php
/**
 * Next Sunday on the homepage, plus cancelled/moved exceptions.
 *
 * Regular Sundays come from Church Admin's "Sunday Service" calendar (same source as
 * the Service Schedule). Exceptions live in the emerson_sunday_exceptions option so
 * the office can mark a date Cancelled or Moved without editing the page each week.
 * Saving Cancelled or Moved emails anyone assigned on the rota or who ticked that
 * they can serve, and copies the office.
 */

declare( strict_types=1 );

const EMERSON_SUNDAY_OPTION          = 'emerson_sunday_exceptions';
const EMERSON_SUNDAY_DEFAULTS_OPTION = 'emerson_sunday_defaults';
const EMERSON_SUNDAY_DEFAULT_TIME   = '10:00:00';
const EMERSON_SUNDAY_DEFAULT_VENUE  = 'St. Charles YMCA';
const EMERSON_SUNDAY_DEFAULT_ADDRESS = '3900 Shady Springs Ln, St Peters, MO 63376';
const EMERSON_SUNDAY_DEFAULT_PLACE  = 'St. Charles YMCA, 3900 Shady Springs Ln, St Peters, MO 63376';
const EMERSON_SUNDAY_VISIT_PATH     = '/visit-us/';

function emerson_sunday_exceptions(): array {
	$stored = get_option( EMERSON_SUNDAY_OPTION, array() );
	return is_array( $stored ) ? $stored : array();
}

/**
 * Time and place for every Sunday left as Regular from the last Apply-from date onward.
 *
 * @return array{time:string,venue:string,address:string,place:string,note:string,from:string}
 */
function emerson_sunday_defaults(): array {
	$stored = get_option( EMERSON_SUNDAY_DEFAULTS_OPTION, null );
	if ( ! is_array( $stored ) ) {
		return array(
			'time'    => EMERSON_SUNDAY_DEFAULT_TIME,
			'venue'   => EMERSON_SUNDAY_DEFAULT_VENUE,
			'address' => EMERSON_SUNDAY_DEFAULT_ADDRESS,
			'place'   => EMERSON_SUNDAY_DEFAULT_PLACE,
			'note'    => '',
			'from'    => '',
		);
	}
	$time = (string) ( $stored['time'] ?? '' );
	if ( preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
		$time .= ':00';
	}
	if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
		$time = EMERSON_SUNDAY_DEFAULT_TIME;
	}
	$venue   = trim( (string) ( $stored['venue'] ?? '' ) );
	$address = trim( (string) ( $stored['address'] ?? '' ) );
	if ( $venue === '' ) {
		$venue = EMERSON_SUNDAY_DEFAULT_VENUE;
	}
	if ( $address === '' ) {
		$address = EMERSON_SUNDAY_DEFAULT_ADDRESS;
	}
	$from = (string) ( $stored['from'] ?? '' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
		$from = '';
	}
	return array(
		'time'    => $time,
		'venue'   => $venue,
		'address' => $address,
		'place'   => $venue . ', ' . $address,
		'note'    => sanitize_text_field( (string) ( $stored['note'] ?? '' ) ),
		'from'    => $from,
	);
}

function emerson_sunday_exception( string $date ): ?array {
	$row = emerson_sunday_exceptions()[ $date ] ?? null;
	if ( ! is_array( $row ) || empty( $row['status'] ) || 'regular' === $row['status'] ) {
		return null;
	}
	return $row;
}

/** Upcoming Sunday Service rows from the calendar, oldest first. */
function emerson_sunday_calendar_upcoming( int $limit = 16 ): array {
	global $wpdb;
	if ( ! function_exists( 'church_admin_premium_level_check' ) ) {
		return array();
	}
	$today = current_time( 'Y-m-d' );
	$now   = current_time( 'H:i:s' );
	$rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.start_date, a.start_time, a.end_time, a.title, a.location
			FROM {$wpdb->prefix}church_admin_calendar_date a
			JOIN {$wpdb->prefix}church_admin_calendar_category b ON a.cat_id = b.cat_id
			WHERE b.category = 'Sunday Service' AND a.start_date >= %s
			ORDER BY a.start_date, a.start_time",
			$today
		)
	);
	$out = array();
	foreach ( $rows as $row ) {
		$end = $row->end_time ?: '11:00:00';
		if ( $row->start_date === $today && $now > $end ) {
			continue;
		}
		$out[] = $row;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * The next Sundays the office will see, padded with generated dates if the calendar runs out.
 *
 * @return list<array{date:string,time:string,title:string,place:string}>
 */
function emerson_sunday_upcoming( int $limit = 12 ): array {
	$out = array();
	foreach ( emerson_sunday_calendar_upcoming( $limit ) as $row ) {
		$parts = emerson_sunday_split_place( $row->location ?: EMERSON_SUNDAY_DEFAULT_PLACE );
		$out[] = array(
			'date'    => $row->start_date,
			'time'    => $row->start_time ?: EMERSON_SUNDAY_DEFAULT_TIME,
			'title'   => $row->title ?: 'Sunday Service',
			'venue'   => $parts['venue'],
			'address' => $parts['address'],
			'place'   => $parts['place'],
		);
	}
	$day = new DateTime( current_time( 'Y-m-d' ) );
	while ( (int) $day->format( 'w' ) !== 0 ) {
		$day->modify( '+1 day' );
	}
	if ( $day->format( 'Y-m-d' ) === current_time( 'Y-m-d' ) && current_time( 'H:i:s' ) > '11:00:00' ) {
		$day->modify( '+7 days' );
	}
	$have = array_column( $out, 'date' );
	for ( ; count( $out ) < $limit; $day->modify( '+7 days' ) ) {
		$date = $day->format( 'Y-m-d' );
		if ( in_array( $date, $have, true ) ) {
			continue;
		}
		$out[]  = array(
			'date'    => $date,
			'time'    => EMERSON_SUNDAY_DEFAULT_TIME,
			'title'   => 'Sunday Service',
			'venue'   => EMERSON_SUNDAY_DEFAULT_VENUE,
			'address' => EMERSON_SUNDAY_DEFAULT_ADDRESS,
			'place'   => EMERSON_SUNDAY_DEFAULT_PLACE,
		);
		$have[] = $date;
	}
	usort( $out, static fn( array $a, array $b ): int => $a['date'] <=> $b['date'] );
	return array_slice( $out, 0, $limit );
}

function emerson_sunday_split_place( string $place ): array {
	$place = trim( $place );
	if ( '' === $place ) {
		return array(
			'venue'   => EMERSON_SUNDAY_DEFAULT_VENUE,
			'address' => EMERSON_SUNDAY_DEFAULT_ADDRESS,
			'place'   => EMERSON_SUNDAY_DEFAULT_PLACE,
		);
	}
	if ( str_contains( $place, ',' ) ) {
		[ $venue, $address ] = array_map( 'trim', explode( ',', $place, 2 ) );
	} else {
		$venue   = $place;
		$address = EMERSON_SUNDAY_DEFAULT_ADDRESS;
	}
	return array(
		'venue'   => $venue,
		'address' => $address,
		'place'   => $venue . ', ' . $address,
	);
}

/**
 * Sunday Service rows from Church Admin, keyed by date.
 *
 * @return array<string, array{date:string,time:string,title:string,venue:string,address:string,place:string}>
 */
function emerson_sunday_bases_between( string $from, string $until ): array {
	global $wpdb;
	if ( ! function_exists( 'church_admin_premium_level_check' ) ) {
		return array();
	}
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.start_date, a.start_time, a.title, a.location
			FROM {$wpdb->prefix}church_admin_calendar_date a
			JOIN {$wpdb->prefix}church_admin_calendar_category b ON a.cat_id = b.cat_id
			WHERE b.category = 'Sunday Service' AND a.start_date BETWEEN %s AND %s
			ORDER BY a.start_date, a.start_time",
			$from,
			$until
		)
	);
	$out = array();
	foreach ( $rows as $row ) {
		$date = substr( (string) $row->start_date, 0, 10 );
		if ( isset( $out[ $date ] ) ) {
			continue;
		}
		$raw   = trim( (string) $row->location );
		$parts = emerson_sunday_split_place( $raw !== '' ? $raw : EMERSON_SUNDAY_DEFAULT_PLACE );
		$out[ $date ] = array(
			'date'    => $date,
			'time'    => $row->start_time ?: EMERSON_SUNDAY_DEFAULT_TIME,
			'title'   => $row->title ?: 'Sunday Service',
			'venue'   => $parts['venue'],
			'address' => $parts['address'],
			'place'   => $parts['place'],
		);
	}
	return $out;
}

/**
 * The place line for one Sunday: the Church Admin location when it has one,
 * otherwise the usual YMCA, unless Sunday Location has moved or cancelled it.
 */
function emerson_sunday_place_line( string $date ): string {
	static $bases = null;
	if ( $bases === null ) {
		$today = current_time( 'Y-m-d' );
		$bases = emerson_sunday_bases_between(
			gmdate( 'Y-m-01', strtotime( $today . ' -1 month' ) ),
			gmdate( 'Y-m-t', strtotime( $today . ' +24 months' ) )
		);
	}
	$base  = $bases[ $date ] ?? array(
		'date'    => $date,
		'time'    => EMERSON_SUNDAY_DEFAULT_TIME,
		'title'   => 'Sunday Service',
		'venue'   => EMERSON_SUNDAY_DEFAULT_VENUE,
		'address' => EMERSON_SUNDAY_DEFAULT_ADDRESS,
		'place'   => EMERSON_SUNDAY_DEFAULT_PLACE,
	);
	$shown = emerson_sunday_display( $base );
	if ( ( $shown['status'] ?? '' ) === 'cancelled' ) {
		return '';
	}
	return trim( (string) ( $shown['place'] ?? '' ) );
}

function emerson_sunday_display( array $base ): array {
	$date   = $base['date'];
	$stored = emerson_sunday_exceptions()[ $date ] ?? null;
	$stored = is_array( $stored ) ? $stored : array();
	$status = (string) ( $stored['status'] ?? 'regular' );
	if ( ! in_array( $status, array( 'regular', 'cancelled', 'moved' ), true ) ) {
		$status = 'regular';
	}
	$defaults = emerson_sunday_defaults();
	$time     = ! empty( $stored['time'] ) ? (string) $stored['time'] : $defaults['time'];
	$venue    = $defaults['venue'];
	$address  = $defaults['address'];
	if ( ! empty( $stored['venue'] ) ) {
		$venue = (string) $stored['venue'];
	}
	if ( ! empty( $stored['address'] ) ) {
		$address = (string) $stored['address'];
	} elseif ( ! empty( $stored['place'] ) ) {
		$parts   = emerson_sunday_split_place( (string) $stored['place'] );
		$venue   = $stored['venue'] ?? $parts['venue'];
		$address = $parts['address'];
	}
	if ( array_key_exists( 'note', $stored ) ) {
		$note = (string) $stored['note'];
	} elseif ( 'regular' === $status ) {
		$note = (string) ( $defaults['note'] ?? '' );
	} else {
		$note = '';
	}
	return array(
		'date'    => $date,
		'time'    => $time,
		'venue'   => $venue,
		'address' => $address,
		'place'   => $venue . ', ' . $address,
		'title'   => $base['title'],
		'status'  => $status,
		'note'    => $note,
	);
}

function emerson_sunday_format_date( string $date ): string {
	return mysql2date( 'l, F j, Y', $date );
}

function emerson_sunday_format_time( string $time ): string {
	$shown = mysql2date( 'g:i a', $time );
	return str_replace( ':00', '', $shown );
}

/**
 * Time shown in Sunday Location admin fields (always 12-hour with AM/PM).
 */
function emerson_sunday_admin_time_value( string $time ): string {
	return mysql2date( 'g:i A', $time );
}

/**
 * Accept "7:00 PM", "7 pm", "19:00", or "19:00:00" and store as HH:MM:SS.
 */
function emerson_sunday_parse_time( string $raw, string $fallback ): string {
	$raw = trim( $raw );
	if ( $raw === '' ) {
		return $fallback;
	}
	if ( preg_match( '/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m ) ) {
		$h = (int) $m[1];
		$min = (int) $m[2];
		$s = isset( $m[3] ) ? (int) $m[3] : 0;
		if ( $h >= 0 && $h <= 23 && $min >= 0 && $min <= 59 && $s >= 0 && $s <= 59 ) {
			return sprintf( '%02d:%02d:%02d', $h, $min, $s );
		}
		return $fallback;
	}
	if ( preg_match( '/^(\d{1,2})(?::(\d{2}))?\s*([ap])\.?m\.?$/i', $raw, $m ) ) {
		$h   = (int) $m[1];
		$min = isset( $m[2] ) && $m[2] !== '' ? (int) $m[2] : 0;
		$pm  = ( strtolower( $m[3] ) === 'p' );
		if ( $h < 1 || $h > 12 || $min > 59 ) {
			return $fallback;
		}
		if ( ! $pm ) {
			$h = ( 12 === $h ) ? 0 : $h;
		} else {
			$h = ( 12 === $h ) ? 12 : $h + 12;
		}
		return sprintf( '%02d:%02d:00', $h, $min );
	}
	return $fallback;
}

function emerson_sunday_place_html( string $place ): string {
	$url = home_url( EMERSON_SUNDAY_VISIT_PATH );
	return '<a href="' . esc_url( $url ) . '">' . esc_html( $place ) . '</a>';
}

function emerson_sunday_meet_html( array $shown ): string {
	return 'This week, meet us at ' . esc_html( emerson_sunday_format_time( $shown['time'] ) )
		. ', at the ' . esc_html( $shown['venue'] ) . ' located at'
		. '<span class="emerson-sunday-location__address">' . emerson_sunday_place_html( $shown['address'] ) . '</span>';
}

function emerson_sunday_location_html(): string {
	$upcoming = emerson_sunday_upcoming( 8 );
	if ( ! $upcoming ) {
		return '<div class="emerson-sunday-location">'
			. '<p>No upcoming Sunday service is on the calendar yet.</p>'
			. emerson_sunday_zoom_button_html()
			. '</div>';
	}
	$next = emerson_sunday_display( $upcoming[0] );
	$html = '<div class="emerson-sunday-location">';
	if ( 'cancelled' === $next['status'] ) {
		$html .= '<p class="emerson-sunday-location__status emerson-sunday-location__cancelled"><strong>'
			. esc_html( emerson_sunday_format_date( $next['date'] ) ) . ' — Service cancelled</strong></p>';
		if ( $next['note'] ) {
			$html .= '<p class="emerson-sunday-location__note">' . esc_html( $next['note'] ) . '</p>';
		}
		$following = null;
		foreach ( array_slice( $upcoming, 1 ) as $row ) {
			$shown = emerson_sunday_display( $row );
			if ( 'cancelled' !== $shown['status'] ) {
				$following = $shown;
				break;
			}
		}
		if ( $following ) {
			$html .= '<p class="emerson-sunday-location__next">Next service: ' . emerson_sunday_meet_html( $following ) . '</p>';
		}
	} else {
		if ( 'moved' === $next['status'] ) {
			$html .= '<p class="emerson-sunday-location__status emerson-sunday-location__moved"><strong>Moved</strong></p>';
		}
		$html .= '<p class="emerson-sunday-location__when">' . emerson_sunday_meet_html( $next ) . '</p>';
		if ( $next['note'] ) {
			$html .= '<p class="emerson-sunday-location__note">' . esc_html( $next['note'] ) . '</p>';
		}
	}
	$html .= emerson_sunday_zoom_button_html();
	return $html . '</div>';
}

/**
 * Same public Zoom join URL as Worship → Zoom Link and the footer.
 */
function emerson_sunday_zoom_url(): string {
	return 'http://zoom.us/j/92538603507?pwd=K3NJWVE4ZnhvWkJWK1Nmbm8zOXhQUT09';
}

function emerson_sunday_zoom_button_html(): string {
	return '<p class="emerson-sunday-location__zoom">'
		. '<a class="emerson-sunday-location__zoom-btn" href="' . esc_url( emerson_sunday_zoom_url() ) . '">'
		. esc_html__( 'Join on Zoom', 'emerson-uuchapel' )
		. '</a></p>';
}

add_shortcode( 'emerson_sunday_location', 'emerson_sunday_location_html' );

/**
 * People who would need to know about this Sunday: rota jobs first, then anyone who ticked they can serve.
 *
 * @return array<int, array{name:string,email:string,jobs:array<int,string>,available:bool}>
 */
function emerson_sunday_servers( string $date ): array {
	global $wpdb;
	$people = array();

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'church_admin_new_rota' ) ) ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.people_id, p.first_name, p.last_name, p.email, p.user_id, s.rota_task
				FROM {$wpdb->prefix}church_admin_new_rota r
				JOIN {$wpdb->prefix}church_admin_people p ON p.people_id = r.people_id
				JOIN {$wpdb->prefix}church_admin_rota_settings s ON s.rota_id = r.rota_task_id
				WHERE DATE(r.rota_date) = %s
				ORDER BY s.rota_task",
				$date
			)
		);
		foreach ( $rows as $row ) {
			$id = (int) $row->people_id;
			if ( ! isset( $people[ $id ] ) ) {
				$people[ $id ] = emerson_sunday_person_row( $row );
			}
			$task = trim( (string) $row->rota_task );
			if ( $task && ! in_array( $task, $people[ $id ]['jobs'], true ) ) {
				$people[ $id ]['jobs'][] = $task;
			}
		}
	}

	if ( function_exists( 'emerson_serving_table' ) ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT p.people_id, p.first_name, p.last_name, p.email, p.user_id
				FROM ' . emerson_serving_table() . " a
				JOIN {$wpdb->prefix}church_admin_people p ON p.people_id = a.people_id
				WHERE a.serve_date = %s AND a.can_serve = 1",
				$date
			)
		);
		foreach ( $rows as $row ) {
			$id = (int) $row->people_id;
			if ( ! isset( $people[ $id ] ) ) {
				$people[ $id ] = emerson_sunday_person_row( $row );
			}
			$people[ $id ]['available'] = true;
		}
	}

	return $people;
}

function emerson_sunday_person_row( object $row ): array {
	$email = trim( (string) $row->email );
	if ( ! is_email( $email ) && ! empty( $row->user_id ) ) {
		$user = get_userdata( (int) $row->user_id );
		if ( $user && is_email( $user->user_email ) ) {
			$email = $user->user_email;
		}
	}
	$name = function_exists( 'emerson_account_name' ) ? emerson_account_name( null, $row ) : trim( $row->first_name . ' ' . $row->last_name );
	return array(
		'name'      => $name !== '' ? $name : 'Friend',
		'email'     => $email,
		'jobs'      => array(),
		'available' => false,
	);
}

function emerson_sunday_notify( string $date, string $status, array $display ): int {
	$people = emerson_sunday_servers( $date );
	$when   = emerson_sunday_format_date( $date );
	$sent   = 0;
	$named  = array();

	foreach ( $people as $person ) {
		if ( ! is_email( $person['email'] ) ) {
			continue;
		}
		if ( 'cancelled' === $status ) {
			$role = $person['jobs']
				? 'You were scheduled to serve as ' . implode( ' and ', $person['jobs'] ) . ' that day. You do not need to come.'
				: 'You had said you could serve that Sunday. You will not be needed that day.';
			$subject = 'Sunday service cancelled — ' . $when;
			$body    = "Hi {$person['name']},\n\nSunday service on {$when} has been cancelled.\n\n{$role}\n";
		} else {
			$role = $person['jobs']
				? 'You are still scheduled to serve as ' . implode( ' and ', $person['jobs'] ) . ' that day.'
				: 'You had said you could serve that Sunday.';
			$subject = 'Sunday service moved — ' . $when;
			$body    = "Hi {$person['name']},\n\nSunday service on {$when} has been moved.\n\nThis week, meet us at "
				. emerson_sunday_format_time( $display['time'] ) . ', at the '
				. ( $display['venue'] ?? EMERSON_SUNDAY_DEFAULT_VENUE ) . ' located at '
				. ( $display['address'] ?? $display['place'] ) . ".\n\n{$role}\n";
		}
		if ( $display['note'] ) {
			$body .= "\n" . $display['note'] . "\n";
		}
		$body .= "\nEmerson Unitarian Universalist Chapel\n" . home_url( '/' ) . "\n";
		if ( wp_mail( $person['email'], $subject, $body, array( 'Reply-To: office@emersonuuchapel.org' ) ) ) {
			++$sent;
			$named[] = $person['name'] . ( $person['jobs'] ? ' (' . implode( ', ', $person['jobs'] ) . ')' : '' );
		}
	}

	$office = "Sunday service on {$when} was marked {$status} in WordPress.\n\n";
	$office .= $named ? "These people were emailed:\n- " . implode( "\n- ", $named ) . "\n" : "No one is signed up to serve that Sunday, so no member emails were sent.\n";
	if ( $display['note'] ) {
		$office .= "\nNote: " . $display['note'] . "\n";
	}
	wp_mail( 'office@emersonuuchapel.org', 'Sunday service ' . $status . ' — ' . $when, $office );

	return $sent;
}

function emerson_sunday_can_manage(): bool {
	return current_user_can( 'edit_pages' );
}

/* No sidebar item — Event Places is the editor. Old bookmarks still redirect. */
add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'options.php',
			'Sunday Location',
			'Sunday Location',
			'edit_pages',
			'emerson-sunday-location',
			'emerson_sunday_admin_page'
		);
	}
);

function emerson_sunday_admin_save(): ?string {
	if ( ! emerson_sunday_can_manage() || empty( $_POST['emerson_sunday_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_sunday_save' );
	$posted = isset( $_POST['sunday'] ) && is_array( $_POST['sunday'] ) ? wp_unslash( $_POST['sunday'] ) : array();
	$old    = emerson_sunday_exceptions();
	/* Keep exceptions outside the current Event Places window. */
	$next    = $old;
	$notices = array();
	$rows    = function_exists( 'emerson_event_places_rows' )
		? emerson_event_places_rows( 'sunday' )
		: emerson_sunday_upcoming( 12 );

	foreach ( $rows as $base ) {
		$date = $base['date'];
		$row  = is_array( $posted[ $date ] ?? null ) ? $posted[ $date ] : array();
		$status = sanitize_key( (string) ( $row['status'] ?? 'regular' ) );
		if ( ! in_array( $status, array( 'regular', 'cancelled', 'moved' ), true ) ) {
			$status = 'regular';
		}
		$time = emerson_sunday_parse_time(
			sanitize_text_field( (string) ( $row['time'] ?? '' ) ),
			$base['time']
		);
		$defaults = emerson_sunday_defaults();
		$venue    = sanitize_text_field( (string) ( $row['venue'] ?? '' ) ) ?: $defaults['venue'];
		$address  = sanitize_text_field( (string) ( $row['address'] ?? '' ) ) ?: $defaults['address'];
		$note     = sanitize_textarea_field( (string) ( $row['note'] ?? '' ) );
		$saved    = array(
			'status'  => $status,
			'time'    => $time,
			'venue'   => $venue,
			'address' => $address,
			'place'   => $venue . ', ' . $address,
			'note'    => $note,
		);
		$default_note = (string) ( $defaults['note'] ?? '' );
		$unchanged    = 'regular' === $status
			&& $time === $defaults['time']
			&& $venue === $defaults['venue']
			&& $address === $defaults['address']
			&& ( $note === '' || $note === $default_note );
		if ( $unchanged ) {
			unset( $next[ $date ] );
			continue;
		}
		$prev     = is_array( $old[ $date ] ?? null ) ? $old[ $date ] : array();
		$prev_key = ( $prev['status'] ?? '' ) . '|' . ( $prev['time'] ?? '' ) . '|' . ( $prev['venue'] ?? '' ) . '|' . ( $prev['address'] ?? $prev['place'] ?? '' );
		$new_key  = $saved['status'] . '|' . $saved['time'] . '|' . $saved['venue'] . '|' . $saved['address'];
		$already  = ( $prev['notified'] ?? '' ) === $new_key;
		$saved['notified'] = $already ? $new_key : ( $prev['notified'] ?? '' );
		if ( 'regular' !== $status && $prev_key !== $new_key && ! $already ) {
			// Claim this exact change before any mail goes out. Mail is slow, and a
			// second click used to start another send before the first one recorded
			// that these people had already been told.
			$claim = 'emerson_sunday_mailed_' . md5( $date . '|' . $new_key );
			if ( add_option( $claim, time(), '', false ) ) {
				$saved['notified'] = $new_key;
				$pending[]         = array(
					'date'    => $date,
					'status'  => $status,
					'display' => array(
						'time'    => $saved['time'],
						'venue'   => $saved['venue'],
						'address' => $saved['address'],
						'place'   => $saved['place'],
						'note'    => $saved['note'],
						'status'  => $saved['status'],
					),
				);
			} else {
				$saved['notified'] = $new_key;
			}
		}
		$next[ $date ] = $saved;
	}

	update_option( EMERSON_SUNDAY_OPTION, $next, false );

	emerson_sunday_sync_calendar_rows( $rows );

	foreach ( $pending ?? array() as $item ) {
		$count     = emerson_sunday_notify( $item['date'], $item['status'], $item['display'] );
		$notices[] = sprintf(
			'%1$s is %2$s. Emailed %3$d %4$s who were signed up to serve (and the office).',
			emerson_sunday_format_date( $item['date'] ),
			$item['status'],
			$count,
			1 === $count ? 'person' : 'people'
		);
	}
	if ( $notices ) {
		return implode( ' ', $notices );
	}
	return 'Sunday Location saved.';
}

/**
 * Push Emerson Sunday places onto matching Church Admin “Sunday Service” rows.
 *
 * @param list<array{date:string}>|null $rows Bases to sync; defaults to the next 12 Sundays.
 */
function emerson_sunday_sync_calendar_rows( ?array $rows = null ): void {
	if ( ! function_exists( 'emerson_sync_calendar_row' ) ) {
		return;
	}
	if ( $rows === null ) {
		$rows = emerson_sunday_upcoming( 12 );
	}
	foreach ( $rows as $base ) {
		$shown = emerson_sunday_display( $base );
		$line  = ( $shown['status'] ?? '' ) === 'cancelled'
			? 'Cancelled'
			: trim( (string) ( $shown['place'] ?? '' ) );
		emerson_sync_calendar_row( 'Sunday Service', $base['date'], $line, (string) $shown['time'] );
	}
}

function emerson_sunday_sync_calendar_upcoming( int $limit = 12 ): void {
	emerson_sunday_sync_calendar_rows( emerson_sunday_upcoming( $limit ) );
}

/**
 * Snapshot of the previous Regular default, kept on Sundays before the Apply-from date.
 *
 * @param array{time:string,venue:string,address:string,place:string,note?:string} $old
 * @return array{status:string,time:string,venue:string,address:string,place:string,note:string}
 */
function emerson_sunday_regular_snapshot( array $old, string $note = '' ): array {
	return array(
		'status'  => 'regular',
		'time'    => $old['time'],
		'venue'   => $old['venue'],
		'address' => $old['address'],
		'place'   => $old['place'],
		'note'    => $note,
	);
}

function emerson_sunday_defaults_save(): ?string {
	if ( ! emerson_sunday_can_manage() || empty( $_POST['emerson_sunday_defaults_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_sunday_defaults_save' );

	$from = sanitize_text_field( (string) ( wp_unslash( $_POST['default_from'] ?? '' ) ) );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
		return 'Pick a start date. Regular Sundays before that date keep the previous place.';
	}

	$time = emerson_sunday_parse_time(
		sanitize_text_field( (string) ( wp_unslash( $_POST['default_time'] ?? '' ) ) ),
		EMERSON_SUNDAY_DEFAULT_TIME
	);
	$venue   = sanitize_text_field( (string) ( wp_unslash( $_POST['default_venue'] ?? '' ) ) );
	$address = sanitize_text_field( (string) ( wp_unslash( $_POST['default_address'] ?? '' ) ) );
	$note    = sanitize_text_field( (string) ( wp_unslash( $_POST['default_note'] ?? '' ) ) );
	if ( $venue === '' ) {
		$venue = EMERSON_SUNDAY_DEFAULT_VENUE;
	}
	if ( $address === '' ) {
		$address = EMERSON_SUNDAY_DEFAULT_ADDRESS;
	}

	$overwrite = ! empty( $_POST['default_overwrite'] );
	$old       = emerson_sunday_defaults();
	update_option(
		EMERSON_SUNDAY_DEFAULTS_OPTION,
		array(
			'time'    => $time,
			'venue'   => $venue,
			'address' => $address,
			'note'    => $note,
			'from'    => $from,
		),
		false
	);

	$next  = emerson_sunday_exceptions();
	$dates = array();
	foreach ( emerson_sunday_upcoming( 52 ) as $base ) {
		$dates[ $base['date'] ] = true;
	}
	foreach ( array_keys( $next ) as $date ) {
		$dates[ $date ] = true;
	}
	ksort( $dates );

	foreach ( array_keys( $dates ) as $date ) {
		$row    = is_array( $next[ $date ] ?? null ) ? $next[ $date ] : null;
		$status = is_array( $row ) ? (string) ( $row['status'] ?? 'regular' ) : 'regular';
		if ( in_array( $status, array( 'cancelled', 'moved' ), true ) ) {
			continue;
		}
		if ( $date < $from ) {
			if ( ! is_array( $row ) ) {
				$next[ $date ] = emerson_sunday_regular_snapshot( $old, (string) ( $old['note'] ?? '' ) );
				continue;
			}
			$row_venue   = (string) ( $row['venue'] ?? '' );
			$row_address = (string) ( $row['address'] ?? ( $row['place'] ?? '' ) );
			$row_time    = (string) ( $row['time'] ?? '' );
			$follows_old = ( $row_venue === '' || $row_venue === $old['venue'] )
				&& ( $row_address === '' || $row_address === $old['address'] || $row_address === $old['place'] )
				&& ( $row_time === '' || $row_time === $old['time'] );
			if ( $follows_old ) {
				$kept_note     = array_key_exists( 'note', $row )
					? (string) $row['note']
					: (string) ( $old['note'] ?? '' );
				$next[ $date ] = emerson_sunday_regular_snapshot( $old, $kept_note );
			}
			continue;
		}
		if ( $overwrite && is_array( $row ) && 'regular' === $status ) {
			unset( $next[ $date ] );
		}
	}
	update_option( EMERSON_SUNDAY_OPTION, $next, false );
	emerson_sunday_sync_calendar_upcoming( 52 );

	if ( $overwrite ) {
		return sprintf(
			'From %1$s onward, every Regular Sunday now uses this time and place (custom Regular rows were overwritten). Regular Sundays before that date kept the previous place. Moved and Cancelled Sundays were left as they are.',
			emerson_sunday_format_date( $from )
		);
	}
	return sprintf(
		'From %1$s onward, Regular Sundays that still followed the old default use this time and place. Custom Regular, Moved, and Cancelled Sundays were left as they are. Regular Sundays before that date kept the previous place.',
		emerson_sunday_format_date( $from )
	);
}

/** Old menu slug: send the office to Event Places with Sunday selected. */
function emerson_sunday_admin_page(): void {
	if ( ! emerson_sunday_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to edit Sunday Location.' ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=emerson-event-places&event=sunday' ) );
	exit;
}
