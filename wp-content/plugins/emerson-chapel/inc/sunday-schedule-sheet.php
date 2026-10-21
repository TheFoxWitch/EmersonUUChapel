<?php
/**
 * Sunday Morning Schedule Google Sheet: monthly theme + holiday / title / speaker on the calendar.
 *
 * The church already keeps one spreadsheet. Same-file edits show here after the cache
 * expires (or after Refresh on the Sunday Schedule admin screen). A new spreadsheet
 * needs its URL pasted there. Serving-role names stay off the public calendar.
 */

declare( strict_types=1 );

const EMERSON_SCHEDULE_SHEET_OPTION    = 'emerson_schedule_sheet_url';
const EMERSON_SCHEDULE_SHEET_CACHE     = 'emerson_schedule_sheet';
const EMERSON_SCHEDULE_SHEET_META      = 'emerson_schedule_sheet_meta';
const EMERSON_SCHEDULE_VIEWERS_OPTION  = 'emerson_schedule_viewers';
const EMERSON_SCHEDULE_SHEET_TTL       = 30 * MINUTE_IN_SECONDS;
const EMERSON_SCHEDULE_SHEET_DEFAULT   = 'https://docs.google.com/spreadsheets/d/1-yBUlBc74nS2GB7QwJ4iIGpnVS673e4mt_CTAl_PfyI/edit?gid=2058833792';

/**
 * Serving-roster columns. Shown only after a designated person clicks a day.
 *
 * @return array<string,string>
 */
function emerson_schedule_roster_labels(): array {
	return array(
		'liturgist'     => 'Liturgist',
		'tech'          => 'Tech',
		'greeter'       => 'Greeter',
		'hospitality'   => 'Hospitality',
		'board'         => 'Board person of the day',
		're_volunteers' => 'RE volunteers',
		're_topic'      => 'RE class topic',
	);
}

function emerson_schedule_sheet_url(): string {
	$stored = get_option( EMERSON_SCHEDULE_SHEET_OPTION, '' );
	return is_string( $stored ) && $stored !== '' ? $stored : EMERSON_SCHEDULE_SHEET_DEFAULT;
}

/**
 * @return array{id:string,gid:string}|null
 */
function emerson_schedule_sheet_parse_url( string $url ): ?array {
	if ( ! preg_match( '#^https://docs\.google\.com/spreadsheets/#i', $url ) ) {
		return null;
	}
	if ( ! preg_match( '#/spreadsheets/d/([a-zA-Z0-9_-]+)#', $url, $id ) ) {
		return null;
	}
	$gid = '0';
	if ( preg_match( '/[?&#]gid=(\d+)/', $url, $found ) ) {
		$gid = $found[1];
	}
	return array(
		'id'  => $id[1],
		'gid' => $gid,
	);
}

function emerson_schedule_sheet_export_url( string $url = '' ): string {
	$url = $url !== '' ? $url : emerson_schedule_sheet_url();
	if ( preg_match( '/[?&]format=csv\b/', $url ) || preg_match( '/[?&]output=csv\b/', $url ) ) {
		return $url;
	}
	$parsed = emerson_schedule_sheet_parse_url( $url );
	if ( ! $parsed ) {
		return '';
	}
	return 'https://docs.google.com/spreadsheets/d/' . $parsed['id'] . '/export?format=csv&gid=' . $parsed['gid'];
}

function emerson_schedule_sheet_clear_cache(): void {
	delete_transient( EMERSON_SCHEDULE_SHEET_CACHE );
}

/**
 * @return array{themes:array<string,string>,sundays:array<string,array{holiday:string,title:string,speaker:string}>,error?:string}
 */
function emerson_schedule_sheet_data( bool $refresh = false ): array {
	if ( $refresh ) {
		emerson_schedule_sheet_clear_cache();
	}
	$cached = get_transient( EMERSON_SCHEDULE_SHEET_CACHE );
	if ( is_array( $cached ) && isset( $cached['themes'], $cached['sundays'] ) ) {
		$sample = reset( $cached['sundays'] );
		if ( is_array( $sample ) && array_key_exists( 'roster', $sample ) ) {
			return $cached;
		}
	}

	$empty = array(
		'themes'  => array(),
		'sundays' => array(),
	);
	$export = emerson_schedule_sheet_export_url();
	if ( $export === '' ) {
		$empty['error'] = 'The spreadsheet URL is not a Google Sheet link.';
		emerson_schedule_sheet_store_meta( $empty['error'] );
		return $empty;
	}

	$response = wp_remote_get(
		$export,
		array(
			'timeout'    => 15,
			'user-agent' => 'EmersonUUChapel/1.0 (WordPress calendar)',
			'redirection' => 5,
		)
	);
	if ( is_wp_error( $response ) ) {
		$empty['error'] = $response->get_error_message();
		emerson_schedule_sheet_store_meta( $empty['error'] );
		return $empty;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = (string) wp_remote_retrieve_body( $response );
	if ( $code < 200 || $code >= 300 || $body === '' ) {
		$empty['error'] = 'Could not download the spreadsheet (HTTP ' . $code . '). Check that the sheet is shared so anyone with the link can view it.';
		emerson_schedule_sheet_store_meta( $empty['error'] );
		return $empty;
	}
	if ( 0 === strncmp( $body, '<', 1 ) ) {
		$empty['error'] = 'Google returned a page instead of the spreadsheet. The file may be private, or this may be a new URL that needs Anyone-with-the-link access.';
		emerson_schedule_sheet_store_meta( $empty['error'] );
		return $empty;
	}

	$parsed = emerson_schedule_sheet_parse_csv( $body );
	set_transient( EMERSON_SCHEDULE_SHEET_CACHE, $parsed, EMERSON_SCHEDULE_SHEET_TTL );
	emerson_schedule_sheet_store_meta( '', count( $parsed['sundays'] ), count( $parsed['themes'] ) );
	return $parsed;
}

function emerson_schedule_sheet_store_meta( string $error, int $sundays = 0, int $themes = 0 ): void {
	update_option(
		EMERSON_SCHEDULE_SHEET_META,
		array(
			'fetched' => current_time( 'mysql' ),
			'url'     => emerson_schedule_sheet_url(),
			'error'   => $error,
			'sundays' => $sundays,
			'themes'  => $themes,
		),
		false
	);
}

/**
 * @return array{themes:array<string,string>,sundays:array<string,array{holiday:string,title:string,speaker:string}>}
 */
function emerson_schedule_sheet_parse_csv( string $csv ): array {
	$out = array(
		'themes'  => array(),
		'sundays' => array(),
	);
	$handle = fopen( 'php://temp', 'r+' );
	if ( ! $handle ) {
		return $out;
	}
	fwrite( $handle, $csv );
	rewind( $handle );

	$year      = (int) current_time( 'Y' );
	$end_year  = 0;
	$date_col  = 0;
	$theme_col = 1;
	$title_col = 3;
	$holi_col  = 2;
	$speak_col = 4;
	$role_cols = array();
	$header    = false;
	$prev_mon  = 0;

	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		$cells = array_map( static fn( $cell ) => trim( (string) $cell ), $row );
		$line  = implode( ' ', $cells );
		if ( ! $header && preg_match( '/\b(20\d{2})\s*[-–]\s*(20\d{2})\b/', $line, $years ) ) {
			$year     = (int) $years[1];
			$end_year = (int) $years[2];
		}
		if ( ! $header ) {
			foreach ( $cells as $index => $cell ) {
				$label = emerson_schedule_sheet_header_label( $cell );
				if ( 'monthly theme' === $label ) {
					$header    = true;
					$theme_col = $index;
					$date_col  = max( 0, $index - 1 );
				} elseif ( 'special event/holiday' === $label || 'special event' === $label || 'holiday' === $label ) {
					$holi_col = $index;
				} elseif ( 'title/topic' === $label || 'title' === $label || 'topic' === $label ) {
					$title_col = $index;
				} elseif ( 'speaker' === $label ) {
					$speak_col = $index;
				} elseif ( 'liturgist' === $label ) {
					$role_cols['liturgist'] = $index;
				} elseif ( 'tech' === $label ) {
					$role_cols['tech'] = $index;
				} elseif ( 'greeter' === $label ) {
					$role_cols['greeter'] = $index;
				} elseif ( 'hospitality' === $label ) {
					$role_cols['hospitality'] = $index;
				} elseif ( 'board person of the day' === $label ) {
					$role_cols['board'] = $index;
				} elseif ( 'religious education volunteers' === $label || 're volunteers' === $label ) {
					$role_cols['re_volunteers'] = $index;
				} elseif ( 're class topic' === $label ) {
					$role_cols['re_topic'] = $index;
				}
			}
			continue;
		}

		$raw = $cells[ $date_col ] ?? '';
		if ( $raw === '' || ! preg_match( '/^\d{1,2}-[A-Za-z]{3,}$/', $raw ) ) {
			continue;
		}
		$month = emerson_schedule_sheet_month_number( $raw );
		if ( ! $month ) {
			continue;
		}
		if ( ! $prev_mon && $end_year && $month <= 7 ) {
			$year = $end_year;
		} elseif ( $prev_mon && $month < $prev_mon ) {
			++$year;
		}
		$prev_mon = $month;
		$ymd      = emerson_schedule_sheet_parse_date( $raw, $year );
		if ( ! $ymd ) {
			continue;
		}

		$theme   = $cells[ $theme_col ] ?? '';
		$holiday = $cells[ $holi_col ] ?? '';
		$title   = $cells[ $title_col ] ?? '';
		$speaker = $cells[ $speak_col ] ?? '';
		$roster  = array();
		foreach ( $role_cols as $key => $index ) {
			$value = $cells[ $index ] ?? '';
			if ( $value !== '' ) {
				$roster[ $key ] = $value;
			}
		}
		$ym = substr( $ymd, 0, 7 );
		if ( $theme !== '' && ! isset( $out['themes'][ $ym ] ) ) {
			$out['themes'][ $ym ] = $theme;
		}
		if ( $holiday === '' && $title === '' && $speaker === '' && ! $roster ) {
			continue;
		}
		$out['sundays'][ $ymd ] = array(
			'holiday' => $holiday,
			'title'   => $title,
			'speaker' => $speaker,
			'roster'  => $roster,
		);
	}
	fclose( $handle );
	return $out;
}

function emerson_schedule_sheet_header_label( string $cell ): string {
	$cell = str_replace( "\xc2\xa0", ' ', $cell );
	return strtolower( trim( (string) preg_replace( '/\s+/', ' ', $cell ) ) );
}

function emerson_schedule_sheet_month_number( string $raw ): int {
	if ( ! preg_match( '/-([A-Za-z]+)$/', $raw, $match ) ) {
		return 0;
	}
	$map = array(
		'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3,
		'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
		'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10,
		'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12,
	);
	return $map[ strtolower( $match[1] ) ] ?? 0;
}

function emerson_schedule_sheet_parse_date( string $raw, int $year ): string {
	foreach ( array( 'j-M', 'j-F', 'd-M', 'd-F' ) as $format ) {
		$dt = DateTime::createFromFormat( '!' . $format . ' Y', $raw . ' ' . $year );
		if ( ! $dt instanceof DateTime ) {
			continue;
		}
		$errors = DateTime::getLastErrors();
		if ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) {
			continue;
		}
		return $dt->format( 'Y-m-d' );
	}
	return '';
}

function emerson_schedule_theme( string $ym ): string {
	return emerson_schedule_sheet_data()['themes'][ $ym ] ?? '';
}

function emerson_schedule_sunday( string $ymd ): ?array {
	$row = emerson_schedule_sheet_data()['sundays'][ $ymd ] ?? null;
	return is_array( $row ) ? $row : null;
}

function emerson_schedule_planning_link_html(): string {
	if ( ! emerson_schedule_can_view_roster() ) {
		return '';
	}
	$url = emerson_schedule_sheet_url();
	if ( $url === '' ) {
		return '';
	}
	return '<p class="emerson-planning-calendar"><a href="' . esc_url( $url ) . '">Planning Calendar</a></p>';
}

function emerson_schedule_theme_html( string $ym ): string {
	$theme = emerson_schedule_theme( $ym );
	$class = 'emerson-month-theme' . ( $theme === '' ? ' is-empty' : '' );
	$label = $theme !== '' ? 'This month&#8217;s theme: <strong>' . esc_html( $theme ) . '</strong>' : '';
	return '<p class="' . esc_attr( $class ) . '" data-month="' . esc_attr( $ym ) . '">' . $label . '</p>';
}

function emerson_schedule_break_slashes( string $text ): string {
	return str_replace( '/', '/&#8203;', esc_html( $text ) );
}

function emerson_schedule_detail_html( array $row, string $class = 'emerson-ca-service' ): string {
	$parts = array();
	if ( $row['title'] !== '' ) {
		$parts[] = '<span class="' . esc_attr( $class ) . '__title">' . esc_html( $row['title'] ) . '</span>';
	}
	if ( $row['holiday'] !== '' ) {
		$parts[] = '<span class="' . esc_attr( $class ) . '__holiday">' . emerson_schedule_break_slashes( $row['holiday'] ) . '</span>';
	}
	if ( $row['speaker'] !== '' ) {
		$parts[] = '<span class="' . esc_attr( $class ) . '__speaker">' . esc_html( $row['speaker'] ) . '</span>';
	}
	if ( ( $row['location'] ?? '' ) !== '' ) {
		$parts[] = '<span class="' . esc_attr( $class ) . '__location">' . esc_html( $row['location'] ) . '</span>';
	}
	return $parts ? '<span class="' . esc_attr( $class ) . '">' . implode( '', $parts ) . '</span>' : '';
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! wp_script_is( 'emerson-chapel', 'enqueued' ) ) {
			return;
		}
		$data    = emerson_schedule_sheet_data();
		$can     = emerson_schedule_can_view_roster();
		$from   = gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-d' ) . ' -1 month' ) );
		$until  = gmdate( 'Y-m-t', strtotime( current_time( 'Y-m-d' ) . ' +24 months' ) );
		$others = array();
		if ( function_exists( 'emerson_calendar_other_events' ) ) {
			foreach ( emerson_calendar_other_events( $from, $until ) as $ymd => $items ) {
				foreach ( $items as $item ) {
					$others[ $ymd ][] = array(
						'title'    => $item['title'],
						'time'     => $item['time'],
						'location' => $item['location'] ?? '',
						'label'    => function_exists( 'emerson_calendar_event_label' ) ? emerson_calendar_event_label( $item ) : $item['title'],
					);
				}
			}
		}
		$sundays = array();
		foreach ( $data['sundays'] as $ymd => $row ) {
			$public = array(
				'holiday'  => $row['holiday'] ?? '',
				'title'    => $row['title'] ?? '',
				'speaker'  => $row['speaker'] ?? '',
				'location' => function_exists( 'emerson_sunday_place_line' ) ? emerson_sunday_place_line( $ymd ) : '',
			);
			if ( $can && ! empty( $row['roster'] ) && is_array( $row['roster'] ) ) {
				$public['roster'] = $row['roster'];
			}
			$sundays[ $ymd ] = $public;
		}
		wp_localize_script(
			'emerson-chapel',
			'emersonScheduleSheet',
			array(
				'themes'        => $data['themes'],
				'sundays'       => $sundays,
				'events'        => $others,
				'canViewRoster'   => $can,
				'labels'          => $can ? emerson_schedule_roster_labels() : array(),
				'serving'         => emerson_schedule_my_serving(),
				'serviceRequests' => emerson_schedule_service_requests( $from, $until ),
			)
		);
	},
	20
);

add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag, $attr ) {
		if ( 'church_admin' !== $tag || ! is_string( $output ) ) {
			return $output;
		}
		$type = $attr['type'] ?? '';
		if ( 'calendar' === $type ) {
			if ( function_exists( 'emerson_calendar_expand_sidebar_excerpts' ) ) {
				$output = emerson_calendar_expand_sidebar_excerpts( $output );
			}
			return $output . emerson_schedule_planning_link_html();
		}
		if ( 'calendar-list' === $type ) {
			return emerson_schedule_decorate_list( $output );
		}
		return $output;
	},
	20,
	3
);

function emerson_schedule_decorate_list( string $html ): string {
	return (string) preg_replace_callback(
		'/<tr>\s*<td class="ca-list-date">([^<]+)<\/td>(.*?)<\/tr>/s',
		static function ( array $match ): string {
			$ymd = emerson_schedule_parse_display_date( $match[1] );
			$row = $ymd ? emerson_schedule_sunday( $ymd ) : null;
			if ( ! $row ) {
				return $match[0];
			}
			$event = $match[2];
			if ( $row['title'] !== '' ) {
				$event = preg_replace( '/<strong>Sunday Service<\/strong>/', '<strong>' . esc_html( $row['title'] ) . '</strong>', $event, 1 ) ?? $event;
			}
			$detail = emerson_schedule_detail_html(
				array(
					'holiday'  => $row['holiday'],
					'title'    => '',
					'speaker'  => $row['speaker'],
					'location' => function_exists( 'emerson_sunday_place_line' ) ? emerson_sunday_place_line( $ymd ) : '',
				),
				'emerson-ca-list'
			);
			if ( $detail !== '' ) {
				$event = preg_replace( '/(<\/strong>)/', '$1' . $detail, $event, 1 ) ?? $event;
			}
			return '<tr><td class="ca-list-date">' . $match[1] . '</td>' . $event . '</tr>';
		},
		$html
	);
}

function emerson_schedule_parse_display_date( string $text ): string {
	$dt = date_create( $text );
	return $dt instanceof DateTimeInterface ? $dt->format( 'Y-m-d' ) : '';
}

/**
 * Church people who may click a day and see liturgist / greeter / RE names.
 * Names only — matched to Church Admin people and WordPress logins in the database.
 *
 * @return list<string>
 */
function emerson_schedule_default_viewer_names(): array {
	return array(
		'PJ Pyrchla',
		'Nathan Williams',
		'Jenny Kerr',
		'Sharon Swanson',
		'Karyl Howard',
		'Lauren Williams',
		'Lana Michels',
		'Marie Sebert',
		'Harlan Bloom',
	);
}

/**
 * @return list<string>
 */
function emerson_schedule_name_aliases( string $name ): array {
	$aliases = array( strtolower( $name ) );
	if ( 'pj pyrchla' === strtolower( $name ) ) {
		$aliases[] = 'paula pyrchla';
		$aliases[] = 'p.j. pyrchla';
		$aliases[] = 'p.j pyrchla';
	}
	return $aliases;
}

function emerson_schedule_person_matches_name( string $first, string $last, string $wanted ): bool {
	$have = strtolower( trim( $first . ' ' . $last ) );
	return in_array( $have, emerson_schedule_name_aliases( $wanted ), true );
}

/**
 * @return list<int>
 */
function emerson_schedule_match_viewer_user_ids(): array {
	global $wpdb;
	$ids = array();
	$people = $wpdb->get_results( "SELECT first_name, last_name, user_id FROM {$wpdb->prefix}church_admin_people WHERE user_id > 0" );
	if ( is_array( $people ) ) {
		foreach ( $people as $person ) {
			foreach ( emerson_schedule_default_viewer_names() as $wanted ) {
				if ( emerson_schedule_person_matches_name( (string) $person->first_name, (string) $person->last_name, $wanted ) ) {
					$ids[] = (int) $person->user_id;
				}
			}
		}
	}
	foreach ( get_users( array( 'number' => 300 ) ) as $user ) {
		$full = function_exists( 'emerson_account_name' ) ? emerson_account_name( $user ) : (string) $user->display_name;
		foreach ( emerson_schedule_default_viewer_names() as $wanted ) {
			if ( in_array( strtolower( trim( $full ) ), emerson_schedule_name_aliases( $wanted ), true ) ) {
				$ids[] = (int) $user->ID;
			}
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * @return list<int>
 */
function emerson_schedule_viewer_user_ids(): array {
	$stored = get_option( EMERSON_SCHEDULE_VIEWERS_OPTION, null );
	if ( ! is_array( $stored ) ) {
		$stored = emerson_schedule_match_viewer_user_ids();
		update_option( EMERSON_SCHEDULE_VIEWERS_OPTION, $stored, false );
	}
	return array_values( array_unique( array_map( 'intval', $stored ) ) );
}

function emerson_schedule_user_label( WP_User $user ): string {
	$name = function_exists( 'emerson_account_name' ) ? emerson_account_name( $user ) : trim( $user->display_name );
	if ( $name !== '' && ! str_contains( $name, '@' ) ) {
		return $name;
	}
	global $wpdb;
	$person = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT first_name, last_name FROM {$wpdb->prefix}church_admin_people WHERE user_id = %d",
			$user->ID
		)
	);
	if ( $person ) {
		$from = trim( (string) $person->first_name . ' ' . (string) $person->last_name );
		if ( $from !== '' ) {
			return $from;
		}
	}
	return '';
}

/**
 * Dates this login ticked as available, positions they offered, and jobs assigned to them.
 *
 * @return array{available:array<string,bool>,jobs:array<string,list<string>>,offered:array<string,list<string>>}
 */
function emerson_schedule_my_serving(): array {
	global $wpdb;
	$out = array(
		'available' => array(),
		'jobs'      => array(),
		'offered'   => array(),
	);
	if ( ! is_user_logged_in() ) {
		return $out;
	}
	$person = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT people_id FROM {$wpdb->prefix}church_admin_people WHERE user_id = %d",
			get_current_user_id()
		)
	);
	if ( $person && function_exists( 'emerson_serving_table' ) ) {
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT serve_date FROM ' . emerson_serving_table() . ' WHERE people_id = %d AND can_serve = 1',
				$person
			)
		);
		foreach ( $dates as $date ) {
			$out['available'][ substr( (string) $date, 0, 10 ) ] = true;
		}
		if ( function_exists( 'emerson_serving_roles_table' ) && function_exists( 'emerson_serving_role_choices' ) ) {
			$role_rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT serve_date, role_key FROM ' . emerson_serving_roles_table() . ' WHERE people_id = %d',
					$person
				)
			);
			$labels = emerson_serving_role_choices();
			if ( is_array( $role_rows ) ) {
				foreach ( $role_rows as $role_row ) {
					$key = (string) $role_row->role_key;
					if ( ! isset( $labels[ $key ] ) ) {
						continue;
					}
					$out['offered'][ substr( (string) $role_row->serve_date, 0, 10 ) ][] = $labels[ $key ];
				}
			}
		}
	}
	if ( $person && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'church_admin_new_rota' ) ) ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(r.rota_date) AS day, s.rota_task FROM {$wpdb->prefix}church_admin_new_rota r
				JOIN {$wpdb->prefix}church_admin_rota_settings s ON s.rota_id = r.rota_task_id
				WHERE r.people_id = %d ORDER BY r.rota_date",
				$person
			)
		);
		foreach ( $rows as $row ) {
			$task = trim( (string) $row->rota_task );
			$ymd  = substr( (string) $row->day, 0, 10 );
			if ( $task !== '' ) {
				$out['jobs'][ $ymd ][] = $task;
			}
		}
	}
	if ( function_exists( 'emerson_pdf_viewer_names' ) && function_exists( 'emerson_pdf_name_in_cell' ) ) {
		$names  = emerson_pdf_viewer_names();
		$labels = emerson_schedule_roster_labels();
		foreach ( emerson_schedule_sheet_data()['sundays'] as $ymd => $row ) {
			foreach ( $row['roster'] ?? array() as $key => $who ) {
				if ( emerson_pdf_name_in_cell( $names, $who ) ) {
					$label = $labels[ $key ] ?? $key;
					if ( ! in_array( $label, $out['jobs'][ $ymd ] ?? array(), true ) ) {
						$out['jobs'][ $ymd ][] = $label;
					}
				}
			}
		}
	}
	return $out;
}

/**
 * People who ticked that they can serve, by date, with any positions they chose.
 * Administrators, editors, and the designated board list only.
 *
 * @return array<string, list<array{name: string, roles: list<string>}>>
 */
function emerson_schedule_service_requests( string $from, string $until ): array {
	if ( ! emerson_schedule_can_view_roster() || ! function_exists( 'emerson_serving_table' ) ) {
		return array();
	}
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT a.serve_date, a.people_id, p.first_name, p.last_name, p.user_id
			FROM ' . emerson_serving_table() . ' a
			JOIN ' . $wpdb->prefix . 'church_admin_people p ON p.people_id = a.people_id
			WHERE a.can_serve = 1 AND a.serve_date >= %s AND a.serve_date <= %s
			ORDER BY a.serve_date, p.last_name, p.first_name',
			$from,
			$until
		)
	);
	$out = array();
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	$chosen = array();
	if ( function_exists( 'emerson_serving_roles_table' ) && function_exists( 'emerson_serving_role_choices' ) ) {
		$role_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT people_id, serve_date, role_key FROM ' . emerson_serving_roles_table() . ' WHERE serve_date >= %s AND serve_date <= %s',
				$from,
				$until
			)
		);
		$labels = emerson_serving_role_choices();
		if ( is_array( $role_rows ) ) {
			foreach ( $role_rows as $role_row ) {
				$key = (string) $role_row->role_key;
				if ( ! isset( $labels[ $key ] ) ) {
					continue;
				}
				$day = substr( (string) $role_row->serve_date, 0, 10 );
				$chosen[ (int) $role_row->people_id . '|' . $day ][] = $labels[ $key ];
			}
		}
	}
	foreach ( $rows as $row ) {
		$name = function_exists( 'emerson_account_name' )
			? emerson_account_name( null, $row )
			: trim( (string) $row->first_name . ' ' . (string) $row->last_name );
		if ( $name === '' || str_contains( $name, '@' ) ) {
			continue;
		}
		$ymd   = substr( (string) $row->serve_date, 0, 10 );
		$entry = array(
			'name'  => $name,
			'roles' => $chosen[ (int) $row->people_id . '|' . $ymd ] ?? array(),
		);
		$already = false;
		foreach ( $out[ $ymd ] ?? array() as $existing ) {
			if ( ( $existing['name'] ?? '' ) === $name ) {
				$already = true;
				break;
			}
		}
		if ( ! $already ) {
			$out[ $ymd ][] = $entry;
		}
	}
	return $out;
}

function emerson_schedule_can_view_roster( ?int $user_id = null ): bool {
	$user_id = $user_id ?? get_current_user_id();
	if ( $user_id <= 0 ) {
		return false;
	}
	if ( user_can( $user_id, 'edit_pages' ) ) {
		return true;
	}
	return in_array( $user_id, emerson_schedule_viewer_user_ids(), true );
}

function emerson_schedule_can_manage(): bool {
	return current_user_can( 'edit_pages' );
}

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Sunday Schedule',
			'Sunday Schedule',
			'edit_pages',
			'emerson-sunday-schedule',
			'emerson_schedule_admin_page',
			'dashicons-media-spreadsheet',
			59
		);
	}
);

function emerson_schedule_admin_save(): ?string {
	if ( ! emerson_schedule_can_manage() || empty( $_POST['emerson_schedule_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_schedule_save' );
	$url = isset( $_POST['sheet_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['sheet_url'] ) ) : '';
	if ( ! emerson_schedule_sheet_parse_url( $url ) && ! preg_match( '/[?&](format|output)=csv\b/', $url ) ) {
		return 'That does not look like a Google Sheet link. Paste the address from the browser while the Sunday Morning Schedule tab is open.';
	}
	update_option( EMERSON_SCHEDULE_SHEET_OPTION, $url, false );
	emerson_schedule_sheet_data( true );
	return 'Saved. The calendar will use this spreadsheet.';
}

function emerson_schedule_admin_refresh(): ?string {
	if ( ! emerson_schedule_can_manage() || empty( $_POST['emerson_schedule_refresh'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_schedule_save' );
	emerson_schedule_sheet_data( true );
	return 'Refreshed from the spreadsheet.';
}

function emerson_schedule_admin_page(): void {
	if ( ! emerson_schedule_can_manage() ) {
		wp_die( esc_html__( 'You do not have permission to edit the Sunday Schedule spreadsheet URL.' ) );
	}
	$notice = emerson_schedule_admin_save() ?? emerson_schedule_admin_refresh() ?? emerson_schedule_admin_save_viewers() ?? emerson_schedule_admin_seed_groups();
	$data   = emerson_schedule_sheet_data();
	$meta   = get_option( EMERSON_SCHEDULE_SHEET_META, array() );
	$meta   = is_array( $meta ) ? $meta : array();
	$ym     = current_time( 'Y-m' );
	$theme  = emerson_schedule_theme( $ym );

	echo '<div class="wrap emerson-schedule-admin"><h1>Sunday Schedule</h1>';
	if ( $notice ) {
		$bad = 0 === strpos( $notice, 'That does not' );
		echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>The public calendar reads <strong>monthly theme</strong>, <strong>holiday</strong>, <strong>title</strong>, and <strong>speaker</strong> from the church&#8217;s Sunday Morning Schedule spreadsheet. Serving-role names stay off the public site. The line under the month name is the theme; the PDF is <strong>This calendar PDF</strong> at the top of the Calendar page.</p>';
	echo '<p>Edit the same Google Sheet they already use. Those edits show on the site after about 30 minutes, or immediately after <strong>Refresh from spreadsheet</strong>.</p>';
	echo '<p>If they <em>replace</em> the file (a new spreadsheet, or a different tab), paste the new address below. It is the URL in the browser while that tab is open &mdash; it contains <code>/spreadsheets/d/&hellip;</code> and usually <code>gid=</code>.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_schedule_save' );
	echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="emerson-sheet-url">Spreadsheet URL</label></th><td>';
	echo '<input name="sheet_url" id="emerson-sheet-url" type="url" class="large-text" value="' . esc_attr( emerson_schedule_sheet_url() ) . '">';
	echo '<p class="description">Anyone-with-the-link view is enough. Do not put this edit address on a public page if they want the file private.</p>';
	echo '</td></tr></table>';
	echo '<p class="submit">';
	echo '<button type="submit" name="emerson_schedule_save" value="1" class="button button-primary">Save URL</button> ';
	echo '<button type="submit" name="emerson_schedule_refresh" value="1" class="button">Refresh from spreadsheet</button>';
	echo '</p></form>';

	echo '<h2>Last fetch</h2>';
	if ( ! empty( $meta['error'] ) ) {
		echo '<p><strong>Error:</strong> ' . esc_html( (string) $meta['error'] ) . '</p>';
	} elseif ( ! empty( $meta['fetched'] ) ) {
		echo '<p>Read at ' . esc_html( (string) $meta['fetched'] ) . '. ';
		echo (int) ( $meta['sundays'] ?? 0 ) . ' dated rows, ';
		echo (int) ( $meta['themes'] ?? 0 ) . ' monthly themes.</p>';
	} else {
		echo '<p>Nothing fetched yet. Save or refresh.</p>';
	}
	if ( $theme !== '' ) {
		echo '<p>This month&#8217;s theme (' . esc_html( date_i18n( 'F Y' ) ) . '): <strong>' . esc_html( $theme ) . '</strong></p>';
	} elseif ( empty( $data['error'] ) ) {
		echo '<p>No theme is listed for ' . esc_html( date_i18n( 'F Y' ) ) . '.</p>';
	}

	emerson_schedule_admin_viewers_form();
	emerson_schedule_admin_groups_form();
	echo '</div>';
}

function emerson_schedule_admin_seed_groups(): ?string {
	if ( ! emerson_schedule_can_manage() || empty( $_POST['emerson_schedule_seed_groups'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_schedule_save' );
	if ( ! function_exists( 'emerson_calendar_seed_listed_events' ) ) {
		return 'Could not add the group dates.';
	}
	$added = emerson_calendar_seed_listed_events();
	if ( $added === 0 ) {
		return 'Womyn’s Web, Men’s Night, and Board Meeting already have upcoming dates on the calendar.';
	}
	return 'Added ' . $added . ' group date' . ( 1 === $added ? '' : 's' ) . ' to Church Admin. Edit a date there to set this month’s host.';
}

function emerson_schedule_admin_groups_form(): void {
	echo '<h2>Womyn’s Web, Men’s Night, and Board Meeting</h2>';
	echo '<p>These are Church Admin calendar events, so a click on the month grid offers an iCal download like Sunday Service. Womyn’s Web and Men’s Night default to <strong>Rotating location — check This Week at Emerson</strong>. To name this month’s host, edit that date in <strong>Church Admin → Calendar</strong>. Board meetings default to <strong>Rotating</strong>; name a place for one Monday under <strong>Board Location</strong>.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_schedule_save' );
	echo '<p class="submit"><button type="submit" name="emerson_schedule_seed_groups" value="1" class="button">Add missing group dates</button></p>';
	echo '</form>';
}

function emerson_schedule_admin_save_viewers(): ?string {
	if ( ! emerson_schedule_can_manage() || empty( $_POST['emerson_schedule_viewers_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_schedule_save' );
	$posted = isset( $_POST['viewers'] ) && is_array( $_POST['viewers'] ) ? wp_unslash( $_POST['viewers'] ) : array();
	$ids    = array();
	foreach ( $posted as $id ) {
		$id = (int) $id;
		if ( $id > 0 && get_userdata( $id ) ) {
			$ids[] = $id;
		}
	}
	update_option( EMERSON_SCHEDULE_VIEWERS_OPTION, array_values( array_unique( $ids ) ), false );
	return 'Saved who can open the full Sunday row.';
}

function emerson_schedule_admin_viewers_form(): void {
	$chosen = emerson_schedule_viewer_user_ids();
	global $wpdb;
	$linked_ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT user_id FROM {$wpdb->prefix}church_admin_people WHERE user_id > 0" ) );
	$linked     = array();
	foreach ( get_users( array( 'number' => 300 ) ) as $user ) {
		$id = (int) $user->ID;
		if ( ! in_array( $id, $linked_ids, true ) && ! in_array( $id, $chosen, true ) ) {
			continue;
		}
		$name = emerson_schedule_user_label( $user );
		if ( $name === '' ) {
			continue;
		}
		$linked[ $id ] = $name;
	}
	asort( $linked, SORT_NATURAL | SORT_FLAG_CASE );

	echo '<h2>Who can click a day for the full sheet row</h2>';
	echo '<p>Anyone who can edit pages (Administrator or Editor) already can. The people below also can, once they log in. Visitors still see only theme, holiday, title, and speaker.</p>';
	echo '<p>The same people see <strong>Service Requests</strong> on the calendar. A click or tap lists who marked that they can serve that day. Those names are not on the public calendar.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_schedule_save' );
	echo '<fieldset><ul style="columns:2;max-width:40rem">';
	foreach ( $linked as $id => $name ) {
		echo '<li><label><input type="checkbox" name="viewers[]" value="' . esc_attr( (string) $id ) . '"' . checked( in_array( $id, $chosen, true ), true, false ) . '> ' . esc_html( $name ) . '</label></li>';
	}
	echo '</ul></fieldset>';
	echo '<p class="submit"><button type="submit" name="emerson_schedule_viewers_save" value="1" class="button button-primary">Save who can see the roster</button></p>';
	echo '</form>';

	$missing = array();
	foreach ( emerson_schedule_default_viewer_names() as $wanted ) {
		$found = false;
		foreach ( $linked as $id => $name ) {
			if ( in_array( strtolower( $name ), emerson_schedule_name_aliases( $wanted ), true ) && in_array( $id, $chosen, true ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			$missing[] = $wanted;
		}
	}
	if ( $missing ) {
		echo '<p>No login is linked yet for: <strong>' . esc_html( implode( ', ', $missing ) ) . '</strong>. They cannot see the roster until they have a WordPress account and are ticked above.</p>';
	}
}
