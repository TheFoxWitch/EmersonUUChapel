<?php
/**
 * Board meeting helpers kept for redirects and older call sites.
 *
 * Board Meeting is now a normal Event Places series (emerson_listed_series).
 * Per-date places live in emerson_group_exceptions under the board series id.
 */

declare( strict_types=1 );

const EMERSON_BOARD_OPTION          = 'emerson_board_exceptions';
const EMERSON_BOARD_DEFAULT_TIME    = '18:30:00';
/** Short label in the Board Location admin form when no place is set. */
const EMERSON_BOARD_DEFAULT_VENUE   = 'Rotating';
const EMERSON_BOARD_DEFAULT_ADDRESS = '';
/** Calendar / sidebar text when the meeting place still rotates. */
const EMERSON_BOARD_LOCATION_LINE   = 'Rotating location — check This Week at Emerson';

/**
 * Empty, legacy “Rotating”, or the public rotating line all mean “no fixed place yet”.
 */
function emerson_board_venue_rotates( string $venue ): bool {
	return emerson_group_venue_rotates( $venue );
}

/**
 * Legacy Board option, or the migrated group-exceptions bucket for Board Meeting.
 *
 * @return array<string, array{status:string,time:string,venue:string,address:string,note:string}>
 */
function emerson_board_exceptions(): array {
	$rows = emerson_group_exceptions( 'board' );
	if ( $rows !== array() ) {
		return $rows;
	}
	$stored = get_option( EMERSON_BOARD_OPTION, array() );
	return is_array( $stored ) ? $stored : array();
}

/**
 * Next Board Meeting dates from the listed series (falls back to 3rd Monday).
 *
 * @return list<array{date:string,time:string,venue:string,address:string,title?:string}>
 */
function emerson_board_upcoming( int $limit = 12 ): array {
	$rows = emerson_group_upcoming( 'Board Meeting', $limit );
	if ( $rows !== array() ) {
		return $rows;
	}
	$out   = array();
	$day   = new DateTime( current_time( 'Y-m-01' ) );
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
			'title'   => 'Board Meeting',
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
	return emerson_group_display( 'Board Meeting', $base );
}

function emerson_board_location_line( string $ymd ): string {
	return emerson_group_location_line( 'Board Meeting', $ymd );
}

function emerson_board_is_meeting( string $title ): bool {
	return strcasecmp( trim( $title ), 'Board Meeting' ) === 0;
}

/**
 * Board locations are applied via emerson_group_apply_locations(); kept as a no-op for older callers.
 *
 * @param array<string, list<array{title:string,time:string,location?:string}>> $events
 * @return array<string, list<array{title:string,time:string,location?:string}>>
 */
function emerson_board_apply_locations( array $events ): array {
	return $events;
}

/**
 * Write location and start time onto a Church Admin calendar row matched by title + date.
 */
function emerson_sync_calendar_row( string $title, string $date, string $location, string $time ): void {
	global $wpdb;
	$table = $wpdb->prefix . 'church_admin_calendar_date';
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return;
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return;
	}
	if ( $time !== '' && preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
		$time .= ':00';
	}
	$wpdb->update(
		$table,
		array(
			'location'   => $location,
			'start_time' => $time,
		),
		array(
			'title'      => $title,
			'start_date' => $date,
		)
	);
}

function emerson_board_sync_calendar_row( string $date, string $location, string $time ): void {
	emerson_sync_calendar_row( 'Board Meeting', $date, $location, $time );
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

/** One-time: short “Rotating” on Board Meeting rows → newsletter check line. */
function emerson_board_seed_twe_location_line(): void {
	if ( get_option( 'emerson_board_twe_location_seeded' ) ) {
		return;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'church_admin_calendar_date';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET location = %s WHERE title = %s AND location = %s",
				EMERSON_BOARD_LOCATION_LINE,
				'Board Meeting',
				'Rotating'
			)
		);
	}
	update_option( 'emerson_board_twe_location_seeded', 1, false );
}
add_action( 'init', 'emerson_board_seed_twe_location_line' );

/* No sidebar item — Recurring events is the editor. Old bookmarks still redirect. */
add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'options.php',
			'Board Location',
			'Board Location',
			'edit_pages',
			'emerson-board-location',
			'emerson_board_admin_page'
		);
	}
);

/** Old menu slug: send the office to Recurring events with Board selected. */
function emerson_board_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit Board Location.' ) );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=emerson-event-places&event=board' ) );
	exit;
}

/**
 * Legacy POST handler — Event Places saves Board through emerson_group_admin_save().
 */
function emerson_board_admin_save(): ?string {
	return null;
}
