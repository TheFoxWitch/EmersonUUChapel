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

const EMERSON_SUNDAY_OPTION         = 'emerson_sunday_exceptions';
const EMERSON_SUNDAY_DEFAULT_TIME   = '10:00:00';
const EMERSON_SUNDAY_DEFAULT_VENUE  = 'St. Charles YMCA';
const EMERSON_SUNDAY_DEFAULT_ADDRESS = '3900 Shady Springs Ln, St Peters, MO 63376';
const EMERSON_SUNDAY_DEFAULT_PLACE  = 'St. Charles YMCA, 3900 Shady Springs Ln, St Peters, MO 63376';
const EMERSON_SUNDAY_VISIT_PATH     = '/visit-us/';

function emerson_sunday_exceptions(): array {
	$stored = get_option( EMERSON_SUNDAY_OPTION, array() );
	return is_array( $stored ) ? $stored : array();
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

function emerson_sunday_display( array $base ): array {
	$date   = $base['date'];
	$ex     = emerson_sunday_exception( $date );
	$status = $ex['status'] ?? 'regular';
	$time   = ( $ex && ! empty( $ex['time'] ) ) ? $ex['time'] : $base['time'];
	$parts  = emerson_sunday_split_place( $base['place'] ?? EMERSON_SUNDAY_DEFAULT_PLACE );
	$venue  = $base['venue'] ?? $parts['venue'];
	$address = $base['address'] ?? $parts['address'];
	if ( $ex ) {
		if ( ! empty( $ex['venue'] ) ) {
			$venue = (string) $ex['venue'];
		}
		if ( ! empty( $ex['address'] ) ) {
			$address = (string) $ex['address'];
		} elseif ( ! empty( $ex['place'] ) ) {
			$parts   = emerson_sunday_split_place( (string) $ex['place'] );
			$venue   = $ex['venue'] ?? $parts['venue'];
			$address = $parts['address'];
		}
	}
	$note = ( $ex && ! empty( $ex['note'] ) ) ? (string) $ex['note'] : '';
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
		return '<p class="emerson-sunday-location">No upcoming Sunday service is on the calendar yet.</p>';
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
	return $html . '</div>';
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
		if ( 'regular' === $status ) {
			continue;
		}
		$time = sanitize_text_field( (string) ( $row['time'] ?? '' ) );
		if ( $time && preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
			$time .= ':00';
		}
		if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			$time = $base['time'];
		}
		$venue   = sanitize_text_field( (string) ( $row['venue'] ?? '' ) ) ?: ( $base['venue'] ?? EMERSON_SUNDAY_DEFAULT_VENUE );
		$address = sanitize_text_field( (string) ( $row['address'] ?? '' ) ) ?: ( $base['address'] ?? EMERSON_SUNDAY_DEFAULT_ADDRESS );
		$saved   = array(
			'status'  => $status,
			'time'    => $time,
			'venue'   => $venue,
			'address' => $address,
			'place'   => $venue . ', ' . $address,
			'note'    => sanitize_textarea_field( (string) ( $row['note'] ?? '' ) ),
		);
		$prev     = is_array( $old[ $date ] ?? null ) ? $old[ $date ] : array();
		$prev_key = ( $prev['status'] ?? '' ) . '|' . ( $prev['time'] ?? '' ) . '|' . ( $prev['venue'] ?? '' ) . '|' . ( $prev['address'] ?? $prev['place'] ?? '' );
		$new_key  = $saved['status'] . '|' . $saved['time'] . '|' . $saved['venue'] . '|' . $saved['address'];
		$already  = ( $prev['notified'] ?? '' ) === $new_key;
		if ( $prev_key !== $new_key && ! $already ) {
			$display = emerson_sunday_display( array_merge( $base, array() ) );
			$display['time']    = $saved['time'];
			$display['venue']   = $saved['venue'];
			$display['address'] = $saved['address'];
			$display['place']   = $saved['place'];
			$display['note']    = $saved['note'];
			$display['status']  = $saved['status'];
			$count = emerson_sunday_notify( $date, $status, $display );
			$saved['notified'] = $new_key;
			$notices[] = sprintf(
				'%1$s is %2$s. Emailed %3$d %4$s who were signed up to serve (and the office).',
				emerson_sunday_format_date( $date ),
				$status,
				$count,
				1 === $count ? 'person' : 'people'
			);
		} else {
			$saved['notified'] = $prev['notified'] ?? $new_key;
		}
		$next[ $date ] = $saved;
	}

	update_option( EMERSON_SUNDAY_OPTION, $next, false );
	if ( $notices ) {
		return implode( ' ', $notices );
	}
	return 'Sunday Location saved.';
}

function emerson_sunday_admin_page(): void {
	if ( ! emerson_sunday_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to edit Sunday Location.' ) );
	}
	$notice = emerson_sunday_admin_save();
	echo '<div class="wrap emerson-sunday-admin"><h1>Sunday Location</h1>';
	echo '<style>.emerson-sunday-admin table input.large-text{min-width:16rem}.emerson-sunday-admin select{min-width:8rem}</style>';
	if ( $notice ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>The homepage says <strong>This week, meet us at [time], at the [place] located at [address]</strong>. Leave a date as <strong>Regular</strong> unless that service is cancelled or moved. Saving Cancelled or Moved emails anyone assigned that day or who ticked they can serve, plus the office.</p>';
	echo '<form method="post">';
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
