<?php
/**
 * Board meeting location, edited like Sunday Location.
 *
 * A regular meeting is at a rotating location. Moved names a place for that
 * Monday. The calendar, its sidebar, and the stored Church Admin row follow.
 */

declare( strict_types=1 );

const EMERSON_BOARD_OPTION          = 'emerson_board_exceptions';
const EMERSON_BOARD_DEFAULT_TIME    = '18:30:00';
const EMERSON_BOARD_DEFAULT_VENUE   = 'Rotating';
const EMERSON_BOARD_DEFAULT_ADDRESS = '';
const EMERSON_BOARD_LOCATION_LINE   = 'Rotating';

function emerson_board_exceptions(): array {
	$stored = get_option( EMERSON_BOARD_OPTION, array() );
	return is_array( $stored ) ? $stored : array();
}

/**
 * Next third Mondays, oldest first. Skip today once the meeting hour has passed.
 *
 * @return list<array{date:string,time:string,venue:string,address:string}>
 */
function emerson_board_upcoming( int $limit = 12 ): array {
	$out = array();
	$day = new DateTime( current_time( 'Y-m-01' ) );
	$today = current_time( 'Y-m-d' );
	$now   = current_time( 'H:i:s' );
	for ( $guard = 0; count( $out ) < $limit && $guard < 36; $guard++ ) {
		$ymd = emerson_nth_weekday( (int) $day->format( 'Y' ), (int) $day->format( 'n' ), 3, 1 );
		$day->modify( '+1 month' );
		if ( $ymd === '' || $ymd < $today ) {
			continue;
		}
		if ( $ymd === $today && $now > '20:00:00' ) {
			continue;
		}
		$out[] = array(
			'date'    => $ymd,
			'time'    => EMERSON_BOARD_DEFAULT_TIME,
			'venue'   => EMERSON_BOARD_DEFAULT_VENUE,
			'address' => EMERSON_BOARD_DEFAULT_ADDRESS,
		);
	}
	return $out;
}

function emerson_board_format_date( string $ymd ): string {
	$stamp = strtotime( $ymd . ' 12:00:00' );
	return $stamp ? wp_date( 'l, F j, Y', $stamp ) : $ymd;
}

/**
 * @param array{date:string,time:string,venue:string,address:string} $base
 * @return array{status:string,time:string,venue:string,address:string,place:string,note:string}
 */
function emerson_board_display( array $base ): array {
	$saved  = emerson_board_exceptions()[ $base['date'] ] ?? array();
	$saved  = is_array( $saved ) ? $saved : array();
	$status = (string) ( $saved['status'] ?? 'regular' );
	if ( ! in_array( $status, array( 'regular', 'cancelled', 'moved' ), true ) ) {
		$status = 'regular';
	}
	$time = (string) ( $saved['time'] ?? $base['time'] );
	if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
		$time = $base['time'];
	}
	$venue   = trim( (string) ( $saved['venue'] ?? '' ) );
	$address = trim( (string) ( $saved['address'] ?? '' ) );
	if ( $venue === '' ) {
		$venue = EMERSON_BOARD_DEFAULT_VENUE;
	}
	$place = $address !== '' ? $venue . ', ' . $address : $venue;
	return array(
		'status'  => $status,
		'time'    => $time,
		'venue'   => $venue,
		'address' => $address,
		'place'   => $place,
		'note'    => trim( (string) ( $saved['note'] ?? '' ) ),
	);
}

function emerson_board_location_line( string $ymd ): string {
	$base = array(
		'date'    => $ymd,
		'time'    => EMERSON_BOARD_DEFAULT_TIME,
		'venue'   => EMERSON_BOARD_DEFAULT_VENUE,
		'address' => EMERSON_BOARD_DEFAULT_ADDRESS,
	);
	$shown = emerson_board_display( $base );
	if ( 'cancelled' === $shown['status'] ) {
		return 'Cancelled';
	}
	$venue   = trim( $shown['venue'] );
	$address = trim( $shown['address'] );
	$rotates = $venue === '' || strcasecmp( $venue, EMERSON_BOARD_DEFAULT_VENUE ) === 0;
	if ( $address !== '' && ! $rotates ) {
		return $venue . ', ' . $address;
	}
	if ( $address !== '' ) {
		return $address;
	}
	if ( ! $rotates ) {
		return $venue;
	}
	return EMERSON_BOARD_LOCATION_LINE;
}

function emerson_board_is_meeting( string $title ): bool {
	return strcasecmp( trim( $title ), 'Board Meeting' ) === 0;
}

/**
 * @param array<string, list<array{title:string,time:string,location?:string}>> $events
 * @return array<string, list<array{title:string,time:string,location?:string}>>
 */
function emerson_board_apply_locations( array $events ): array {
	foreach ( $events as $ymd => $items ) {
		foreach ( $items as $index => $item ) {
			if ( ! emerson_board_is_meeting( (string) ( $item['title'] ?? '' ) ) ) {
				continue;
			}
			$shown = emerson_board_display(
				array(
					'date'    => $ymd,
					'time'    => (string) ( $item['time'] ?? EMERSON_BOARD_DEFAULT_TIME ),
					'venue'   => EMERSON_BOARD_DEFAULT_VENUE,
					'address' => EMERSON_BOARD_DEFAULT_ADDRESS,
				)
			);
			$events[ $ymd ][ $index ]['location'] = emerson_board_location_line( $ymd );
			$events[ $ymd ][ $index ]['time']     = $shown['time'];
		}
	}
	return $events;
}

function emerson_board_sync_calendar_row( string $date, string $location, string $time ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'church_admin_calendar_date';
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return;
	}
	$wpdb->update(
		$table,
		array(
			'location'   => $location,
			'start_time' => $time,
		),
		array(
			'title'      => 'Board Meeting',
			'start_date' => $date,
		)
	);
}

function emerson_board_seed_rotating_locations(): void {
	if ( get_option( 'emerson_board_rotating_seeded' ) ) {
		return;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'church_admin_calendar_date';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET location = %s WHERE title = %s AND location LIKE %s",
				EMERSON_BOARD_LOCATION_LINE,
				'Board Meeting',
				'%Chapel%'
			)
		);
	}
	update_option( 'emerson_board_rotating_seeded', 1, false );
}
add_action( 'init', 'emerson_board_seed_rotating_locations' );

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Board Location',
			'Board Location',
			'edit_pages',
			'emerson-board-location',
			'emerson_board_admin_page',
			'dashicons-location',
			58.2
		);
	},
	20
);

function emerson_board_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_board_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_board_save' );
	$posted = isset( $_POST['board'] ) && is_array( $_POST['board'] ) ? wp_unslash( $_POST['board'] ) : array();
	$next   = emerson_board_exceptions();
	foreach ( emerson_board_upcoming( 12 ) as $base ) {
		$date   = $base['date'];
		$row    = is_array( $posted[ $date ] ?? null ) ? $posted[ $date ] : array();
		$status = sanitize_key( (string) ( $row['status'] ?? 'regular' ) );
		if ( ! in_array( $status, array( 'regular', 'cancelled', 'moved' ), true ) ) {
			$status = 'regular';
		}
		$time = sanitize_text_field( (string) ( $row['time'] ?? '' ) );
		if ( $time !== '' && preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
			$time .= ':00';
		}
		if ( ! preg_match( '/^\d{2}:\d{2}:\d{2}$/', $time ) ) {
			$time = $base['time'];
		}
		$venue   = sanitize_text_field( (string) ( $row['venue'] ?? '' ) );
		$address = sanitize_text_field( (string) ( $row['address'] ?? '' ) );
		$note    = sanitize_text_field( (string) ( $row['note'] ?? '' ) );
		$rotates = $venue === '' || strcasecmp( $venue, EMERSON_BOARD_DEFAULT_VENUE ) === 0;
		if ( 'regular' === $status && $time === EMERSON_BOARD_DEFAULT_TIME && $rotates && $address === '' && $note === '' ) {
			unset( $next[ $date ] );
			emerson_board_sync_calendar_row( $date, EMERSON_BOARD_LOCATION_LINE, EMERSON_BOARD_DEFAULT_TIME );
			continue;
		}
		if ( $venue === '' ) {
			$venue = EMERSON_BOARD_DEFAULT_VENUE;
		}
		$next[ $date ] = array(
			'status'  => $status,
			'time'    => $time,
			'venue'   => $venue,
			'address' => $address,
			'note'    => $note,
		);
		$line = 'cancelled' === $status ? 'Cancelled' : ( $address !== '' && ! $rotates ? $venue . ', ' . $address : ( $address !== '' ? $address : ( $rotates ? EMERSON_BOARD_LOCATION_LINE : $venue ) ) );
		emerson_board_sync_calendar_row( $date, $line, $time );
	}
	update_option( EMERSON_BOARD_OPTION, $next, false );
	return 'Board Location saved.';
}

function emerson_board_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit Board Location.' ) );
	}
	$notice = emerson_board_admin_save();
	echo '<div class="wrap emerson-sunday-admin"><h1>Board Location</h1>';
	echo '<style>.emerson-sunday-admin table input.large-text{min-width:16rem}.emerson-sunday-admin select{min-width:8rem}</style>';
	if ( $notice ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>Board meetings are on the third Monday at 6:30 pm. Type this month’s place in <strong>Located at</strong> and save. Leave that box empty and the calendar says <strong>Rotating</strong>. Choose <strong>Cancelled</strong> when there is no meeting.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_board_save' );
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Monday</th><th>Status</th><th>Time</th><th>At the</th><th>Located at</th><th>Note</th>';
	echo '</tr></thead><tbody>';
	foreach ( emerson_board_upcoming( 12 ) as $base ) {
		$shown  = emerson_board_display( $base );
		$date   = $base['date'];
		$time   = substr( $shown['time'], 0, 5 );
		$status = $shown['status'];
		echo '<tr>';
		echo '<td><strong>' . esc_html( emerson_board_format_date( $date ) ) . '</strong></td>';
		echo '<td><select name="board[' . esc_attr( $date ) . '][status]">';
		foreach ( array( 'regular' => 'Regular', 'cancelled' => 'Cancelled', 'moved' => 'Moved' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="time" name="board[' . esc_attr( $date ) . '][time]" value="' . esc_attr( $time ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="board[' . esc_attr( $date ) . '][venue]" value="' . esc_attr( $shown['venue'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="board[' . esc_attr( $date ) . '][address]" value="' . esc_attr( $shown['address'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="board[' . esc_attr( $date ) . '][note]" value="' . esc_attr( $shown['note'] ) . '" placeholder="Optional"></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_board_save" value="1" class="button button-primary">Save board meetings</button></p>';
	echo '</form></div>';
}
