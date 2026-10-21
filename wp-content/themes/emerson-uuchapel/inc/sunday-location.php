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
 * Time and place for every Sunday left as Regular.
 *
 * @return array{time:string,venue:string,address:string,place:string}
 */
function emerson_sunday_defaults(): array {
	$stored = get_option( EMERSON_SUNDAY_DEFAULTS_OPTION, null );
	if ( ! is_array( $stored ) ) {
		return array(
			'time'    => EMERSON_SUNDAY_DEFAULT_TIME,
			'venue'   => EMERSON_SUNDAY_DEFAULT_VENUE,
			'address' => EMERSON_SUNDAY_DEFAULT_ADDRESS,
			'place'   => EMERSON_SUNDAY_DEFAULT_PLACE,
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
	return array(
		'time'    => $time,
		'venue'   => $venue,
		'address' => $address,
		'place'   => $venue . ', ' . $address,
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
	$note = ! empty( $stored['note'] ) ? (string) $stored['note'] : '';
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

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Sunday Location',
			'Sunday Location',
			'edit_pages',
			'emerson-sunday-location',
			'emerson_sunday_admin_page',
			'dashicons-calendar-alt',
			58
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
	$next   = array();
	$notices = array();

	foreach ( emerson_sunday_upcoming( 12 ) as $base ) {
		$date = $base['date'];
		$row  = is_array( $posted[ $date ] ?? null ) ? $posted[ $date ] : array();
		$status = sanitize_key( (string) ( $row['status'] ?? 'regular' ) );
		if ( ! in_array( $status, array( 'regular', 'cancelled', 'moved' ), true ) ) {
			$status = 'regular';
		}
		$time = sanitize_text_field( (string) ( $row['time'] ?? '' ) );
		if ( $time && preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
			$time .= ':00';
		}
		if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			$time = $base['time'];
		}
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
		$unchanged = 'regular' === $status
			&& $time === $defaults['time']
			&& $venue === $defaults['venue']
			&& $address === $defaults['address']
			&& $note === '';
		if ( $unchanged ) {
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

function emerson_sunday_defaults_save(): ?string {
	if ( ! emerson_sunday_can_manage() || empty( $_POST['emerson_sunday_defaults_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_sunday_defaults_save' );
	$time = sanitize_text_field( (string) ( $_POST['default_time'] ?? '' ) );
	if ( $time !== '' && preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
		$time .= ':00';
	}
	if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
		$time = EMERSON_SUNDAY_DEFAULT_TIME;
	}
	$venue   = sanitize_text_field( (string) ( wp_unslash( $_POST['default_venue'] ?? '' ) ) );
	$address = sanitize_text_field( (string) ( wp_unslash( $_POST['default_address'] ?? '' ) ) );
	if ( $venue === '' ) {
		$venue = EMERSON_SUNDAY_DEFAULT_VENUE;
	}
	if ( $address === '' ) {
		$address = EMERSON_SUNDAY_DEFAULT_ADDRESS;
	}
	update_option(
		EMERSON_SUNDAY_DEFAULTS_OPTION,
		array(
			'time'    => $time,
			'venue'   => $venue,
			'address' => $address,
		),
		false
	);
	$next = emerson_sunday_exceptions();
	foreach ( $next as $date => $row ) {
		if ( is_array( $row ) && ( $row['status'] ?? 'regular' ) === 'regular' ) {
			unset( $next[ $date ] );
		}
	}
	update_option( EMERSON_SUNDAY_OPTION, $next, false );
	return 'Regular Sundays now use this time and place. Moved and Cancelled Sundays were left as they are.';
}

function emerson_sunday_admin_page(): void {
	if ( ! emerson_sunday_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to edit Sunday Location.' ) );
	}
	$defaults_notice = emerson_sunday_defaults_save();
	$notice          = emerson_sunday_admin_save();
	$defaults        = emerson_sunday_defaults();
	echo '<div class="wrap emerson-sunday-admin"><h1>Sunday Location</h1>';
	echo '<style>.emerson-sunday-admin table input.large-text,.emerson-sunday-admin .emerson-sunday-default input.large-text{min-width:16rem}.emerson-sunday-admin select{min-width:8rem}.emerson-sunday-default{margin:1rem 0 1.5rem;padding:1rem 1rem 0;background:#fff;border:1px solid #c3c4c7}</style>';
	if ( $defaults_notice ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $defaults_notice ) . '</p></div>';
	}
	if ( $notice ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>The homepage says <strong>This week, meet us at [time], at the [place] located at [address]</strong>. A new street address in <strong>Located at</strong> is kept when you save, even if Status stays <strong>Regular</strong>. Saving <strong>Cancelled</strong> or <strong>Moved</strong> emails anyone assigned that day or who ticked they can serve, plus the office.</p>';
	echo '<form method="post" class="emerson-sunday-default">';
	wp_nonce_field( 'emerson_sunday_defaults_save' );
	echo '<h2>Regular Sunday default</h2>';
	echo '<p>This time and place is used for every Sunday left as <strong>Regular</strong>, including Sundays after the 12 listed below. Sundays marked <strong>Moved</strong> or <strong>Cancelled</strong> stay as they are. Updating this does not send email.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="emerson-default-time">Time</label></th><td><input type="time" id="emerson-default-time" name="default_time" value="' . esc_attr( substr( $defaults['time'], 0, 5 ) ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-default-venue">At the</label></th><td><input type="text" class="large-text" id="emerson-default-venue" name="default_venue" value="' . esc_attr( $defaults['venue'] ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-default-address">Located at</label></th><td><input type="text" class="large-text" id="emerson-default-address" name="default_address" value="' . esc_attr( $defaults['address'] ) . '"></td></tr>';
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_sunday_defaults_save" value="1" class="button button-primary">Update regular Sundays</button></p>';
	echo '</form>';
	echo '<form method="post" onsubmit="if (this.dataset.emersonSaved) { return false; } this.dataset.emersonSaved = \'1\';">';
	wp_nonce_field( 'emerson_sunday_save' );
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Sunday</th><th>Status</th><th>Time</th><th>At the</th><th>Located at</th><th>Note</th><th>Signed up</th>';
	echo '</tr></thead><tbody>';
	foreach ( emerson_sunday_upcoming( 12 ) as $base ) {
		$shown   = emerson_sunday_display( $base );
		$date    = $base['date'];
		$servers = emerson_sunday_servers( $date );
		$time    = substr( $shown['time'], 0, 5 );
		$note    = $shown['note'];
		$status  = $shown['status'];
		$signed  = array();
		foreach ( $servers as $person ) {
			$signed[] = $person['name'] . ( $person['jobs'] ? ' (' . implode( ', ', $person['jobs'] ) . ')' : '' );
		}
		echo '<tr>';
		echo '<td><strong>' . esc_html( emerson_sunday_format_date( $date ) ) . '</strong></td>';
		echo '<td><select name="sunday[' . esc_attr( $date ) . '][status]">';
		foreach ( array( 'regular' => 'Regular', 'cancelled' => 'Cancelled', 'moved' => 'Moved' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="time" name="sunday[' . esc_attr( $date ) . '][time]" value="' . esc_attr( $time ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="sunday[' . esc_attr( $date ) . '][venue]" value="' . esc_attr( $shown['venue'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="sunday[' . esc_attr( $date ) . '][address]" value="' . esc_attr( $shown['address'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="sunday[' . esc_attr( $date ) . '][note]" value="' . esc_attr( $note ) . '" placeholder="Optional"></td>';
		echo '<td>' . ( $signed ? esc_html( implode( ', ', $signed ) ) : 'Nobody yet' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_sunday_save" value="1" class="button button-primary">Save Sundays</button></p>';
	echo '</form></div>';
}
