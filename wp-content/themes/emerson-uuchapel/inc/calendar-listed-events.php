<?php
/**
 * Recurring groups the site already advertises (Engage), plus other Church Admin events.
 *
 * Church Admin's future calendar is Sunday Service only. Womyn's Web, Men's Night, and
 * Board Meeting times come from the Engage page. Circle Suppers stay off the grid —
 * the host picks each date.
 */

declare( strict_types=1 );

/**
 * @return list<array{title:string,time:string,nth:int,wday:int}>
 */
function emerson_listed_event_rules(): array {
	return array(
		array(
			'title' => "Womyn's Web",
			'time'  => '19:00:00',
			'nth'   => 1,
			'wday'  => 1,
		),
		array(
			'title' => "Men's Night",
			'time'  => '18:00:00',
			'nth'   => 1,
			'wday'  => 5,
		),
		array(
			'title' => 'Board Meeting',
			'time'  => '18:30:00',
			'nth'   => 3,
			'wday'  => 1,
		),
	);
}

function emerson_calendar_is_sunday_service( string $title ): bool {
	return strcasecmp( trim( $title ), 'Sunday Service' ) === 0;
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

/**
 * @return array<string, list<array{title:string,time:string}>>
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
				'title' => $rule['title'],
				'time'  => $rule['time'],
			);
		}
		$cursor = strtotime( '+1 month', $cursor );
	}
	return $out;
}

/**
 * Non-Sunday Church Admin events plus the Engage groups, keyed by Y-m-d.
 *
 * @return array<string, list<array{title:string,time:string}>>
 */
function emerson_calendar_other_events( string $first, string $last ): array {
	global $wpdb;
	$out = emerson_calendar_listed_events( $first, $last );
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'church_admin_calendar_date' ) ) ) {
		return $out;
	}
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.start_date, a.start_time, a.title FROM {$wpdb->prefix}church_admin_calendar_date a
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
			'title' => $title,
			'time'  => (string) $row->start_time,
		);
	}
	ksort( $out );
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
