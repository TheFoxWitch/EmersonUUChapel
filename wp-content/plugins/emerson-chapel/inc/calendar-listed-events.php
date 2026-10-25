<?php
/**
 * Recurring groups the site already advertises (Engage), plus other Church Admin events.
 *
 * These are also written into Church Admin so a click on the month grid gets the same
 * sidebar as Sunday Service (time, location, iCal). Circle Suppers stay off the grid —
 * the host picks each date.
 */

declare( strict_types=1 );

const EMERSON_GROUP_LOCATION_ROTATES   = 'Rotating location — check This Week at Emerson';
const EMERSON_GROUP_EXCEPTIONS_OPTION  = 'emerson_group_exceptions';
const EMERSON_GROUP_DEFAULT_VENUE      = 'Rotating';

/**
 * @return list<array{title:string,time:string,end:string,nth:int,wday:int,recurring:string,location:string,description:string,link:string,link_title:string}>
 */
function emerson_listed_event_rules(): array {
	$engage = home_url( '/engage/' );
	return array(
		array(
			'title'       => "Womyn's Web",
			'time'        => '19:00:00',
			'end'         => '21:00:00',
			'nth'         => 1,
			'wday'        => 1,
			'recurring'   => 'n11',
			'location'    => EMERSON_GROUP_LOCATION_ROTATES,
			'description' => 'For all women of the chapel. First Monday of the month, 7:00 pm. Location rotates; the current host is in This Week at Emerson.',
			'link'        => $engage,
			'link_title'  => 'Engage',
		),
		array(
			'title'       => "Men's Night",
			'time'        => '18:00:00',
			'end'         => '20:00:00',
			'nth'         => 1,
			'wday'        => 5,
			'recurring'   => 'n15',
			'location'    => EMERSON_GROUP_LOCATION_ROTATES,
			'description' => 'For men and male-identifying members. First Friday of the month, 6:00–8:00 pm. Location rotates; the current host is in This Week at Emerson.',
			'link'        => $engage,
			'link_title'  => 'Engage',
		),
		array(
			'title'       => 'Board Meeting',
			'time'        => '18:30:00',
			'end'         => '20:00:00',
			'nth'         => 3,
			'wday'        => 1,
			'recurring'   => 'n31',
			'location'    => EMERSON_GROUP_LOCATION_ROTATES,
			'description' => 'Board of Trustees meeting. Third Monday of the month, 6:30 pm. Open to all members. Location rotates; the current place is in This Week at Emerson unless a place is set for that Monday.',
			'link'        => $engage,
			'link_title'  => 'Engage',
		),
	);
}

function emerson_calendar_is_sunday_service( string $title ): bool {
	return strcasecmp( trim( $title ), 'Sunday Service' ) === 0;
}

/**
 * Engage groups edited in Event Places (not Board — that keeps its own option).
 *
 * @return list<string>
 */
function emerson_group_editable_titles(): array {
	return array( "Womyn's Web", "Men's Night" );
}

function emerson_group_is_editable( string $title ): bool {
	foreach ( emerson_group_editable_titles() as $known ) {
		if ( strcasecmp( trim( $title ), $known ) === 0 ) {
			return true;
		}
	}
	return false;
}

/**
 * @return array<string, array<string, array{status:string,time:string,venue:string,address:string,note:string}>>
 */
function emerson_group_exceptions_all(): array {
	$stored = get_option( EMERSON_GROUP_EXCEPTIONS_OPTION, array() );
	return is_array( $stored ) ? $stored : array();
}

/**
 * @return array<string, array{status:string,time:string,venue:string,address:string,note:string}>
 */
function emerson_group_exceptions( string $title ): array {
	$all = emerson_group_exceptions_all();
	foreach ( $all as $key => $rows ) {
		if ( strcasecmp( (string) $key, $title ) === 0 && is_array( $rows ) ) {
			return $rows;
		}
	}
	return array();
}

/**
 * @return array{title:string,time:string,end:string,nth:int,wday:int,recurring:string,location:string,description:string,link:string,link_title:string}|null
 */
function emerson_group_rule_by_title( string $title ): ?array {
	foreach ( emerson_listed_event_rules() as $rule ) {
		if ( strcasecmp( $rule['title'], $title ) === 0 ) {
			return $rule;
		}
	}
	return null;
}

function emerson_group_venue_rotates( string $venue ): bool {
	$venue = trim( $venue );
	if ( $venue === '' ) {
		return true;
	}
	if ( strcasecmp( $venue, EMERSON_GROUP_DEFAULT_VENUE ) === 0 ) {
		return true;
	}
	if ( strcasecmp( $venue, EMERSON_GROUP_LOCATION_ROTATES ) === 0 ) {
		return true;
	}
	return false;
}

/**
 * Next nth-weekday dates for one Engage group, oldest first.
 *
 * @return list<array{date:string,time:string,venue:string,address:string,title:string}>
 */
function emerson_group_upcoming( string $title, int $limit = 12 ): array {
	$rule = emerson_group_rule_by_title( $title );
	if ( ! $rule || ! emerson_group_is_editable( $title ) ) {
		return array();
	}
	$out   = array();
	$day   = new DateTime( current_time( 'Y-m-01' ) );
	$today = current_time( 'Y-m-d' );
	$now   = current_time( 'H:i:s' );
	$end   = (string) ( $rule['end'] ?? '23:59:59' );
	for ( $guard = 0; count( $out ) < $limit && $guard < 36; $guard++ ) {
		$ymd = emerson_nth_weekday( (int) $day->format( 'Y' ), (int) $day->format( 'n' ), (int) $rule['nth'], (int) $rule['wday'] );
		$day->modify( '+1 month' );
		if ( $ymd === '' || $ymd < $today ) {
			continue;
		}
		if ( $ymd === $today && $now > $end ) {
			continue;
		}
		$out[] = array(
			'date'    => $ymd,
			'time'    => $rule['time'],
			'venue'   => EMERSON_GROUP_DEFAULT_VENUE,
			'address' => '',
			'title'   => $rule['title'],
		);
	}
	return $out;
}

/**
 * @param array{date:string,time:string,venue:string,address:string,title?:string} $base
 * @return array{status:string,time:string,venue:string,address:string,place:string,note:string}
 */
function emerson_group_display( string $title, array $base ): array {
	$saved  = emerson_group_exceptions( $title )[ $base['date'] ] ?? array();
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
		$venue = EMERSON_GROUP_DEFAULT_VENUE;
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

function emerson_group_location_line( string $title, string $ymd ): string {
	$rule = emerson_group_rule_by_title( $title );
	$base = array(
		'date'    => $ymd,
		'time'    => $rule['time'] ?? '19:00:00',
		'venue'   => EMERSON_GROUP_DEFAULT_VENUE,
		'address' => '',
		'title'   => $title,
	);
	$shown = emerson_group_display( $title, $base );
	if ( 'cancelled' === $shown['status'] ) {
		return 'Cancelled';
	}
	$venue   = trim( $shown['venue'] );
	$address = trim( $shown['address'] );
	$rotates = emerson_group_venue_rotates( $venue );
	if ( $address !== '' && ! $rotates ) {
		return $venue . ', ' . $address;
	}
	if ( $address !== '' ) {
		return $address;
	}
	if ( ! $rotates ) {
		return $venue;
	}
	return EMERSON_GROUP_LOCATION_ROTATES;
}

/**
 * @param array<string, list<array{title:string,time:string,location?:string}>> $events
 * @return array<string, list<array{title:string,time:string,location?:string}>>
 */
function emerson_group_apply_locations( array $events ): array {
	foreach ( $events as $ymd => $items ) {
		foreach ( $items as $index => $item ) {
			$title = (string) ( $item['title'] ?? '' );
			if ( ! emerson_group_is_editable( $title ) ) {
				continue;
			}
			$rule  = emerson_group_rule_by_title( $title );
			$shown = emerson_group_display(
				$title,
				array(
					'date'    => $ymd,
					'time'    => (string) ( $item['time'] ?? ( $rule['time'] ?? '19:00:00' ) ),
					'venue'   => EMERSON_GROUP_DEFAULT_VENUE,
					'address' => '',
					'title'   => $title,
				)
			);
			$events[ $ymd ][ $index ]['location'] = emerson_group_location_line( $title, $ymd );
			$events[ $ymd ][ $index ]['time']     = $shown['time'];
		}
	}
	return $events;
}

/**
 * Save Event Places rows for one Engage group. Expects POST field group[date][…].
 */
function emerson_group_admin_save( string $title ): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_group_save'] ) ) {
		return null;
	}
	if ( ! emerson_group_is_editable( $title ) ) {
		return null;
	}
	check_admin_referer( 'emerson_group_save' );
	$rule = emerson_group_rule_by_title( $title );
	if ( ! $rule ) {
		return null;
	}
	$posted = isset( $_POST['group'] ) && is_array( $_POST['group'] ) ? wp_unslash( $_POST['group'] ) : array();
	$all    = emerson_group_exceptions_all();
	$next   = emerson_group_exceptions( $title );
	$canon  = $rule['title'];
	$event  = strcasecmp( $canon, "Men's Night" ) === 0 ? 'mens-night' : 'womyns-web';
	$rows   = function_exists( 'emerson_event_places_rows' )
		? emerson_event_places_rows( $event )
		: emerson_group_upcoming( $canon, 12 );
	foreach ( $rows as $base ) {
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
		$rotates = emerson_group_venue_rotates( $venue );
		if ( 'regular' === $status && $time === $rule['time'] && $rotates && $address === '' && $note === '' ) {
			unset( $next[ $date ] );
			if ( function_exists( 'emerson_sync_calendar_row' ) ) {
				emerson_sync_calendar_row( $canon, $date, EMERSON_GROUP_LOCATION_ROTATES, $rule['time'] );
			}
			continue;
		}
		if ( $venue === '' ) {
			$venue = EMERSON_GROUP_DEFAULT_VENUE;
		}
		$next[ $date ] = array(
			'status'  => $status,
			'time'    => $time,
			'venue'   => $venue,
			'address' => $address,
			'note'    => $note,
		);
		$line = 'cancelled' === $status
			? 'Cancelled'
			: ( $address !== '' && ! $rotates
				? $venue . ', ' . $address
				: ( $address !== '' ? $address : ( $rotates ? EMERSON_GROUP_LOCATION_ROTATES : $venue ) ) );
		if ( function_exists( 'emerson_sync_calendar_row' ) ) {
			emerson_sync_calendar_row( $canon, $date, $line, $time );
		}
	}
	$all[ $canon ] = $next;
	update_option( EMERSON_GROUP_EXCEPTIONS_OPTION, $all, false );
	return $canon . ' places saved.';
}

function emerson_nth_weekday( int $year, int $month, int $nth, int $wday ): string {
	if ( $nth < 1 || $month < 1 || $month > 12 || $wday < 0 || $wday > 6 ) {
		return '';
	}
	$first = sprintf( '%d-%02d-01', $year, $month );
	$noon  = strtotime( $first . ' 12:00:00' );
	if ( ! $noon ) {
		return '';
	}
	$delta = ( $wday - (int) gmdate( 'w', $noon ) + 7 ) % 7;
	$day   = 1 + $delta + ( ( $nth - 1 ) * 7 );
	if ( $day > (int) gmdate( 't', $noon ) ) {
		return '';
	}
	return sprintf( '%d-%02d-%02d', $year, $month, $day );
}

function emerson_calendar_event_has_title( array $items, string $title ): bool {
	$want = strtolower( trim( $title ) );
	foreach ( $items as $item ) {
		if ( strtolower( trim( (string) ( $item['title'] ?? '' ) ) ) === $want ) {
			return true;
		}
	}
	return false;
}

function emerson_calendar_interest_cat_id(): int {
	global $wpdb;
	$table = $wpdb->prefix . 'church_admin_calendar_category';
	$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT cat_id FROM {$table} WHERE category = %s", 'Interest Group' ) );
	if ( $id > 0 ) {
		return $id;
	}
	$wpdb->insert(
		$table,
		array(
			'category'   => 'Interest Group',
			'bgcolor'    => '#c5cbe8',
			'text_color' => '#000000',
		)
	);
	return (int) $wpdb->insert_id;
}

/**
 * Write missing Engage-group dates into Church Admin so the sidebar can offer iCal.
 *
 * @return int Number of date rows added.
 */
function emerson_calendar_seed_listed_events( int $months = 36 ): int {
	global $wpdb;
	$table = $wpdb->prefix . 'church_admin_calendar_date';
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return 0;
	}
	$cat    = emerson_calendar_interest_cat_id();
	$added  = 0;
	$cursor = strtotime( current_time( 'Y-m-01' ) . ' 12:00:00' );
	if ( ! $cursor ) {
		return 0;
	}
	$next      = (int) $wpdb->get_var( "SELECT MAX(event_id) FROM {$table}" );
	$event_ids = array();
	foreach ( emerson_listed_event_rules() as $rule ) {
		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT event_id FROM {$table} WHERE title = %s AND start_date >= %s ORDER BY start_date ASC LIMIT 1",
				$rule['title'],
				gmdate( 'Y-m-d', $cursor )
			)
		);
		if ( $existing > 0 ) {
			$event_ids[ $rule['title'] ] = $existing;
			continue;
		}
		++$next;
		$event_ids[ $rule['title'] ] = $next;
	}

	for ( $i = 0; $i < $months; $i++ ) {
		$when   = strtotime( '+' . $i . ' month', $cursor );
		$year   = (int) gmdate( 'Y', $when );
		$month  = (int) gmdate( 'n', $when );
		foreach ( emerson_listed_event_rules() as $rule ) {
			$ymd = emerson_nth_weekday( $year, $month, $rule['nth'], $rule['wday'] );
			if ( $ymd === '' ) {
				continue;
			}
			$have = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT date_id FROM {$table} WHERE title = %s AND start_date = %s LIMIT 1",
					$rule['title'],
					$ymd
				)
			);
			if ( $have ) {
				continue;
			}
			$ok = $wpdb->insert(
				$table,
				array(
					'title'            => $rule['title'],
					'description'      => $rule['description'],
					'location'         => $rule['location'],
					'year_planner'     => 1,
					'start_date'       => $ymd,
					'start_time'       => $rule['time'],
					'end_time'         => $rule['end'],
					'event_id'         => $event_ids[ $rule['title'] ],
					'link'             => $rule['link'],
					'link_title'       => $rule['link_title'],
					'general_calendar' => 1,
					'how_many'         => $months,
					'cat_id'           => $cat,
					'recurring'        => $rule['recurring'],
					'event_type'       => 'calendar',
					'provisional'      => 0,
				)
			);
			if ( $ok ) {
				++$added;
			}
		}
	}
	return $added;
}

/**
 * @return array<string, list<array{title:string,time:string,location?:string}>>
 */
function emerson_calendar_listed_events( string $first, string $last ): array {
	$out   = array();
	$start = strtotime( $first . ' 12:00:00' );
	$end   = strtotime( $last . ' 12:00:00' );
	if ( ! $start || ! $end ) {
		return $out;
	}
	$cursor = strtotime( gmdate( 'Y-m-01', $start ) . ' 12:00:00' );
	while ( $cursor && $cursor <= $end ) {
		$year  = (int) gmdate( 'Y', $cursor );
		$month = (int) gmdate( 'n', $cursor );
		foreach ( emerson_listed_event_rules() as $rule ) {
			$ymd = emerson_nth_weekday( $year, $month, $rule['nth'], $rule['wday'] );
			if ( $ymd === '' || $ymd < $first || $ymd > $last ) {
				continue;
			}
			$out[ $ymd ][] = array(
				'title'    => $rule['title'],
				'time'     => $rule['time'],
				'location' => $rule['location'],
			);
		}
		$cursor = strtotime( '+1 month', $cursor );
	}
	return $out;
}

/**
 * Church Admin non-Sunday events first (so an edited location wins), then Engage fallbacks.
 *
 * @return array<string, list<array{title:string,time:string,location?:string}>>
 */
function emerson_calendar_other_events( string $first, string $last ): array {
	global $wpdb;
	$out = array();
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'church_admin_calendar_date' ) ) ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.start_date, a.start_time, a.title, a.location FROM {$wpdb->prefix}church_admin_calendar_date a
				JOIN {$wpdb->prefix}church_admin_calendar_category b ON a.cat_id = b.cat_id
				WHERE a.start_date BETWEEN %s AND %s ORDER BY a.start_date, a.start_time",
				$first,
				$last
			)
		);
		foreach ( $rows as $row ) {
			$title = trim( (string) $row->title );
			if ( $title === '' || emerson_calendar_is_sunday_service( $title ) ) {
				continue;
			}
			$ymd = substr( (string) $row->start_date, 0, 10 );
			if ( emerson_calendar_event_has_title( $out[ $ymd ] ?? array(), $title ) ) {
				continue;
			}
			$out[ $ymd ][] = array(
				'title'    => $title,
				'time'     => (string) $row->start_time,
				'location' => trim( (string) $row->location ),
			);
		}
	}
	foreach ( emerson_calendar_listed_events( $first, $last ) as $ymd => $items ) {
		foreach ( $items as $item ) {
			if ( emerson_calendar_event_has_title( $out[ $ymd ] ?? array(), $item['title'] ) ) {
				continue;
			}
			$out[ $ymd ][] = $item;
		}
	}
	ksort( $out );
	$out = emerson_group_apply_locations( $out );
	if ( function_exists( 'emerson_board_apply_locations' ) ) {
		$out = emerson_board_apply_locations( $out );
	}
	return $out;
}

function emerson_calendar_event_label( array $event ): string {
	$title = trim( (string) ( $event['title'] ?? '' ) );
	$time  = trim( (string) ( $event['time'] ?? '' ) );
	if ( $time !== '' && $time !== '00:00:00' ) {
		$time = mysql2date( get_option( 'time_format' ), $time );
	} else {
		$time = '';
	}
	return trim( $time . ' ' . $title );
}

/**
 * Church Admin's sidebar clips descriptions to 50 characters. Put the full text back.
 *
 * @param mixed $value
 * @return mixed
 */
function emerson_calendar_expand_sidebar_excerpts( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = emerson_calendar_expand_sidebar_excerpts( $item );
		}
		return $value;
	}
	if ( ! is_string( $value ) || strpos( $value, '...' ) === false ) {
		return $value;
	}
	foreach ( emerson_calendar_full_descriptions() as $full => $excerpt ) {
		if ( $excerpt !== '' && strpos( $value, $excerpt ) !== false ) {
			$value = str_replace( $excerpt, $full, $value );
		}
	}
	return $value;
}

/**
 * @return array<string, string> full description => 50-character Church Admin excerpt
 */
function emerson_calendar_full_descriptions(): array {
	static $map = null;
	if ( is_array( $map ) ) {
		return $map;
	}
	$map = array();
	global $wpdb;
	$table = $wpdb->prefix . 'church_admin_calendar_date';
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return $map;
	}
	$descriptions = $wpdb->get_col( "SELECT DISTINCT description FROM {$table} WHERE description IS NOT NULL AND description != ''" );
	foreach ( emerson_listed_event_rules() as $rule ) {
		if ( ! empty( $rule['description'] ) ) {
			$descriptions[] = $rule['description'];
		}
	}
	foreach ( array_unique( array_filter( array_map( 'strval', $descriptions ) ) ) as $full ) {
		$plain = wp_strip_all_tags( $full );
		if ( $plain === '' || strlen( $plain ) <= 50 ) {
			continue;
		}
		$excerpt = function_exists( 'church_admin_premium_excerpt' )
			? church_admin_premium_excerpt( $plain, 50, '...' )
			: ( substr( $plain, 0, strrpos( substr( $plain, 0, 50 ), ' ' ) ?: 50 ) . '...' );
		if ( is_string( $excerpt ) && $excerpt !== '' && $excerpt !== $plain ) {
			$map[ $plain ] = $excerpt;
		}
	}
	return $map;
}

add_action( 'wp_ajax_church_admin', 'emerson_calendar_buffer_sidebar_descriptions', 1 );
add_action( 'wp_ajax_nopriv_church_admin', 'emerson_calendar_buffer_sidebar_descriptions', 1 );

function emerson_calendar_buffer_sidebar_descriptions(): void {
	$method = isset( $_REQUEST['method'] ) ? sanitize_key( wp_unslash( $_REQUEST['method'] ) ) : '';
	if ( ! in_array( $method, array( 'calendar-day-render', 'calendar-render' ), true ) ) {
		return;
	}
	ob_start(
		static function ( string $json ): string {
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) {
				return $json;
			}
			return wp_json_encode( emerson_calendar_expand_sidebar_excerpts( $data ) ) ?: $json;
		}
	);
}
