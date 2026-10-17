<?php
/**
 * Corrected calendar PDFs for Church Admin's own download links (Calendar page, Members hub).
 *
 * Church Admin Premium 5.7.19 and 5.8.0 build them wrongly:
 * - Year planner (?church_admin_download=yearplanner): all 12 calendars show the current month and year.
 * - Monthly calendar (?church_admin_download=monthly-calendar-pdf): ignores the month asked for, misses events in
 *   January–September (dates built without the leading zero), and when the month ends on a Saturday adds a grey row
 *   that spills onto a second page, taking every event with it.
 * These run on `init` before Church Admin's handler (priority 10) and exit, so its versions never run. Same links,
 * same nonce check for the year planner, same fonts and FPDF library from the plugin.
 */

declare( strict_types=1 );

add_action(
	'init',
	static function (): void {
		$download = isset( $_GET['church_admin_download'] ) ? sanitize_key( wp_unslash( $_GET['church_admin_download'] ) ) : '';
		if ( ! in_array( $download, array( 'yearplanner', 'monthly-calendar-pdf' ), true ) ) {
			return;
		}
		$plugin = WP_PLUGIN_DIR . '/church-admin-premium/includes/';
		if ( ! is_readable( $plugin . 'fpdf.php' ) ) {
			return;
		}
		require_once $plugin . 'fpdf.php';

		if ( 'yearplanner' === $download ) {
			// Same check as Church Admin; its link is made with wp_create_nonce( 'yearplanner' ).
			if ( empty( $_GET['yearplanner'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['yearplanner'] ) ), 'yearplanner' ) ) {
				return;
			}
			$year = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) current_time( 'Y' );
			emerson_year_planner_pdf( $year >= 2000 && $year <= 2100 ? $year : (int) current_time( 'Y' ) );
		}

		$start = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$month = preg_match( '/^(\d{4})-(\d{1,2})/', $start, $m ) && (int) $m[2] >= 1 && (int) $m[2] <= 12
			? array( (int) $m[1], (int) $m[2] )
			: array( (int) current_time( 'Y' ), (int) current_time( 'n' ) );
		emerson_monthly_calendar_pdf( $month[0], $month[1] );
	},
	5
);

function emerson_pdf_new( string $orientation, $size ): FPDF {
	$pdf = new FPDF();
	$pdf->AddFont( 'DejaVu', '', 'DejaVuSans.ttf', true );
	$pdf->AddFont( 'DejaVu', 'B', 'DejaVuSans-Bold.ttf', true );
	$pdf->SetAutoPageBreak( false );
	$pdf->AddPage( $orientation, $size );
	return $pdf;
}

/** "#fdfcb8" or "#fff" → array( r, g, b ), or null. */
function emerson_pdf_rgb( ?string $hex ): ?array {
	$hex = ltrim( (string) $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return preg_match( '/^[0-9a-f]{6}$/i', $hex ) ? array_map( 'hexdec', str_split( $hex, 2 ) ) : null;
}

function emerson_pdf_day_names(): array {
	global $wp_locale;
	return array_map( static fn( $d ) => $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) ), range( 0, 6 ) );
}

function emerson_pdf_current_people_id(): int {
	if ( ! is_user_logged_in() ) {
		return 0;
	}
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT people_id FROM {$wpdb->prefix}church_admin_people WHERE user_id = %d",
			get_current_user_id()
		)
	);
}

/**
 * @return list<string>
 */
function emerson_pdf_viewer_names(): array {
	if ( ! is_user_logged_in() ) {
		return array();
	}
	$user  = wp_get_current_user();
	$names = array();
	if ( function_exists( 'emerson_schedule_user_label' ) ) {
		$names[] = emerson_schedule_user_label( $user );
	}
	if ( function_exists( 'emerson_account_name' ) ) {
		$names[] = emerson_account_name( $user );
	}
	$names[] = trim( $user->first_name . ' ' . $user->last_name );
	return array_values(
		array_unique(
			array_filter(
				array_map( static fn( $name ) => strtolower( trim( (string) $name ) ), $names ),
				static fn( $name ) => $name !== '' && ! str_contains( $name, '@' )
			)
		)
	);
}

/**
 * @return array<int, list<string>>
 */
function emerson_pdf_my_rota( string $first, string $last ): array {
	global $wpdb;
	$person = emerson_pdf_current_people_id();
	if ( ! $person || ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'church_admin_new_rota' ) ) ) {
		return array();
	}
	$jobs = array();
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DATE(r.rota_date) AS day, s.rota_task FROM {$wpdb->prefix}church_admin_new_rota r
			JOIN {$wpdb->prefix}church_admin_rota_settings s ON s.rota_id = r.rota_task_id
			WHERE r.people_id = %d AND DATE(r.rota_date) BETWEEN %s AND %s ORDER BY r.rota_date",
			$person,
			$first,
			$last
		)
	);
	foreach ( $rows as $row ) {
		$task = trim( (string) $row->rota_task );
		if ( $task !== '' ) {
			$jobs[ (int) substr( (string) $row->day, 8, 2 ) ][] = $task;
		}
	}
	return $jobs;
}

function emerson_pdf_name_in_cell( array $names, string $cell ): bool {
	$cell = strtolower( $cell );
	foreach ( $names as $name ) {
		if ( $name !== '' && str_contains( $cell, $name ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param array{holiday?:string,title?:string,speaker?:string,roster?:array<string,string>}|null $sheet
 * @param list<string>                                                                          $mine
 * @return list<string>
 */
function emerson_pdf_my_available( string $first, string $last ): array {
	global $wpdb;
	$person = emerson_pdf_current_people_id();
	if ( ! $person || ! function_exists( 'emerson_serving_table' ) ) {
		return array();
	}
	$dates = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT serve_date FROM ' . emerson_serving_table() . ' WHERE people_id = %d AND can_serve = 1 AND serve_date BETWEEN %s AND %s',
			$person,
			$first,
			$last
		)
	);
	$out = array();
	foreach ( $dates as $date ) {
		$out[ (int) substr( (string) $date, 8, 2 ) ] = true;
	}
	return $out;
}

function emerson_pdf_day_lines( string $time, string $fallback_title, ?array $sheet, array $mine, bool $elevated, bool $available = false ): array {
	$sheet    = is_array( $sheet ) ? $sheet : array();
	$title    = ( $sheet['title'] ?? '' ) !== '' ? $sheet['title'] : $fallback_title;
	$holiday  = $sheet['holiday'] ?? '';
	$speaker  = $sheet['speaker'] ?? '';
	$roster   = is_array( $sheet['roster'] ?? null ) ? $sheet['roster'] : array();
	$labels   = function_exists( 'emerson_schedule_roster_labels' ) ? emerson_schedule_roster_labels() : array();
	$lines    = array();
	$headline = trim( $time . ' ' . $title );
	if ( $headline !== '' ) {
		$lines[] = $headline;
	}
	if ( $holiday !== '' ) {
		$lines[] = $holiday;
	}
	if ( $speaker !== '' ) {
		$lines[] = 'Speaker: ' . $speaker;
	}
	if ( ( $sheet['location'] ?? '' ) !== '' ) {
		$lines[] = $sheet['location'];
	}
	if ( $elevated ) {
		foreach ( $labels as $key => $label ) {
			if ( ( $roster[ $key ] ?? '' ) !== '' ) {
				$lines[] = $label . ': ' . $roster[ $key ];
			}
		}
	}
	if ( $mine ) {
		$lines[] = 'You\'re serving: ' . implode( ', ', $mine );
	} elseif ( $available ) {
		$lines[] = 'You can serve';
	}
	return $lines;
}

function emerson_pdf_sheet_jobs( ?array $sheet, array $names, array $mine ): array {
	if ( ! is_array( $sheet ) || ! $names || empty( $sheet['roster'] ) ) {
		return $mine;
	}
	$labels = function_exists( 'emerson_schedule_roster_labels' ) ? emerson_schedule_roster_labels() : array();
	foreach ( $sheet['roster'] as $key => $who ) {
		if ( emerson_pdf_name_in_cell( $names, $who ) ) {
			$label = $labels[ $key ] ?? $key;
			if ( ! in_array( $label, $mine, true ) ) {
				$mine[] = $label;
			}
		}
	}
	return $mine;
}

function emerson_pdf_push_line( array &$lines, string $line ): void {
	$line = trim( $line );
	if ( $line !== '' && ! in_array( $line, $lines, true ) ) {
		$lines[] = $line;
	}
}

/**
 * Church Admin events, the Sunday Morning Schedule sheet, Engage groups, and serving marks.
 *
 * Everyone gets title, holiday, and speaker. A member who is serving that day
 * sees their role. Elevated viewers (the Sunday Schedule list) get the full row.
 * Weekday availability still prints when there is no Sunday service that day.
 *
 * @return array<int, list<string>>
 */
function emerson_pdf_month_events( int $year, int $month ): array {
	global $wpdb;
	$first     = sprintf( '%d-%02d-01', $year, $month );
	$length    = (int) gmdate( 't', strtotime( $first . ' 12:00:00' ) );
	$last      = sprintf( '%d-%02d-%02d', $year, $month, $length );
	$elevated  = function_exists( 'emerson_schedule_can_view_roster' ) && emerson_schedule_can_view_roster();
	$names     = emerson_pdf_viewer_names();
	$rota      = emerson_pdf_my_rota( $first, $last );
	$available = emerson_pdf_my_available( $first, $last );
	$events    = array();
	$sheeted   = array();
	$rows      = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.start_date, a.start_time, a.title FROM {$wpdb->prefix}church_admin_calendar_date a
			JOIN {$wpdb->prefix}church_admin_calendar_category b ON a.cat_id = b.cat_id
			WHERE a.start_date BETWEEN %s AND %s ORDER BY a.start_date, a.start_time",
			$first,
			$last
		)
	);
	foreach ( $rows as $row ) {
		$ymd   = substr( (string) $row->start_date, 0, 10 );
		$day   = (int) substr( $ymd, 8, 2 );
		$sheet = function_exists( 'emerson_schedule_sunday' ) ? emerson_schedule_sunday( $ymd ) : null;
		$mine  = emerson_pdf_sheet_jobs( $sheet, $names, $rota[ $day ] ?? array() );
		$title = trim( (string) $row->title );
		if ( emerson_calendar_is_sunday_service( $title ) && is_array( $sheet ) ) {
			if ( function_exists( 'emerson_sunday_place_line' ) ) {
				$sheet['location'] = emerson_sunday_place_line( $ymd );
			}
			$events[ $day ] = array_merge(
				$events[ $day ] ?? array(),
				emerson_pdf_day_lines(
					mysql2date( get_option( 'time_format' ), $row->start_time ),
					$title,
					$sheet,
					$mine,
					$elevated,
					false
				)
			);
			$sheeted[ $day ] = true;
			continue;
		}
		if ( ! isset( $events[ $day ] ) ) {
			$events[ $day ] = array();
		}
		emerson_pdf_push_line(
			$events[ $day ],
			trim( mysql2date( get_option( 'time_format' ), $row->start_time ) . ' ' . $title )
		);
	}
	if ( function_exists( 'emerson_schedule_sheet_data' ) ) {
		foreach ( emerson_schedule_sheet_data()['sundays'] as $ymd => $row ) {
			if ( substr( $ymd, 0, 7 ) !== substr( $first, 0, 7 ) ) {
				continue;
			}
			$day = (int) substr( $ymd, 8, 2 );
			if ( ! empty( $sheeted[ $day ] ) ) {
				continue;
			}
			$mine = emerson_pdf_sheet_jobs( $row, $names, $rota[ $day ] ?? array() );
			if ( function_exists( 'emerson_sunday_place_line' ) ) {
				$row['location'] = emerson_sunday_place_line( $ymd );
			}
			$events[ $day ]  = array_merge(
				$events[ $day ] ?? array(),
				emerson_pdf_day_lines( '', '', $row, $mine, $elevated, false )
			);
			$sheeted[ $day ] = true;
		}
	}
	if ( function_exists( 'emerson_calendar_other_events' ) ) {
		foreach ( emerson_calendar_other_events( $first, $last ) as $ymd => $items ) {
			$day = (int) substr( $ymd, 8, 2 );
			if ( ! isset( $events[ $day ] ) ) {
				$events[ $day ] = array();
			}
			foreach ( $items as $item ) {
				emerson_pdf_push_line( $events[ $day ], emerson_calendar_event_label( $item ) );
				if ( ( $item['location'] ?? '' ) !== '' ) {
					emerson_pdf_push_line( $events[ $day ], $item['location'] );
				}
			}
		}
	}
	foreach ( $available as $day => $yes ) {
		if ( ! $yes ) {
			continue;
		}
		$day = (int) $day;
		if ( ! isset( $events[ $day ] ) ) {
			$events[ $day ] = array();
		}
		$marked = false;
		foreach ( $events[ $day ] as $line ) {
			if ( str_starts_with( $line, 'You\'re serving' ) || 'You can serve' === $line ) {
				$marked = true;
				break;
			}
		}
		if ( ! $marked ) {
			emerson_pdf_push_line( $events[ $day ], 'You can serve' );
		}
	}
	return $events;
}

function emerson_pdf_draw_month( FPDF $pdf, int $year, int $month ): void {
	$first  = sprintf( '%d-%02d-01', $year, $month );
	$noon   = strtotime( $first . ' 12:00:00' );
	$length = (int) gmdate( 't', $noon );
	$start  = (int) gmdate( 'w', $noon );
	$weeks  = (int) ceil( ( $start + $length ) / 7 );
	$theme  = function_exists( 'emerson_schedule_theme' ) ? emerson_schedule_theme( sprintf( '%d-%02d', $year, $month ) ) : '';

	$left   = 10;
	$top    = 22;
	$width  = ( $pdf->GetPageWidth() - 20 ) / 7;
	$header = 10;
	$height = ( $pdf->GetPageHeight() - $top - $header - 10 ) / $weeks;

	$pdf->SetXY( $left, 6 );
	$pdf->SetFont( 'DejaVu', 'B', 16 );
	$title = sprintf( 'Calendar %s', date_i18n( 'F Y', $noon ) );
	if ( $theme !== '' ) {
		$title .= '  ·  Theme: ' . $theme;
	}
	$pdf->Cell( $width * 7, 8, $title, 0, 0, 'C' );

	$pdf->SetFont( 'DejaVu', '', 12 );
	foreach ( emerson_pdf_day_names() as $i => $name ) {
		$pdf->SetXY( $left + $i * $width, $top );
		$pdf->Cell( $width, $header, $name, 1, 0, 'C' );
	}

	$events = emerson_pdf_month_events( $year, $month );
	for ( $cell = 0; $cell < $weeks * 7; $cell++ ) {
		$x   = $left + ( $cell % 7 ) * $width;
		$y   = $top + $header + intdiv( $cell, 7 ) * $height;
		$day = $cell - $start + 1;
		if ( $day < 1 || $day > $length ) {
			$pdf->SetFillColor( 200, 200, 200 );
			$pdf->Rect( $x, $y, $width, $height, 'DF' );
			continue;
		}
		$pdf->Rect( $x, $y, $width, $height );
		$pdf->SetXY( $x + 1, $y + 1 );
		$pdf->SetFont( 'DejaVu', 'B', 9 );
		$pdf->Cell( 8, 4.5, (string) $day );

		$fit = emerson_pdf_fit_cell( $pdf, $events[ $day ] ?? array(), $width - 2.2, $height - 6.2 );
		$pdf->SetFont( 'DejaVu', '', $fit['font'] );
		foreach ( $fit['lines'] as $i => $line ) {
			$pdf->SetXY( $x + 1.1, $y + 5.6 + $i * $fit['leading'] );
			$pdf->Cell( $width - 2.2, $fit['leading'], $line );
		}
	}
}

function emerson_pdf_fit_prefix( FPDF $pdf, string $text, float $max_width ): int {
	$len = mb_strlen( $text );
	if ( $len <= 1 ) {
		return $len;
	}
	$lo = 1;
	$hi = $len;
	while ( $lo < $hi ) {
		$mid = (int) ceil( ( $lo + $hi ) / 2 );
		if ( $pdf->GetStringWidth( mb_substr( $text, 0, $mid ) ) <= $max_width ) {
			$lo = $mid;
		} else {
			$hi = $mid - 1;
		}
	}
	return $lo;
}

/**
 * @return list<string>
 */
function emerson_pdf_wrap_line( FPDF $pdf, string $line, float $max_width ): array {
	$line = trim( (string) preg_replace( '/\s+/', ' ', str_replace( '/', '/ ', $line ) ) );
	if ( $line === '' ) {
		return array();
	}
	if ( $pdf->GetStringWidth( $line ) <= $max_width ) {
		return array( $line );
	}
	$words = preg_split( '/\s+/', $line ) ?: array( $line );
	$rows  = array();
	$cur   = '';
	foreach ( $words as $word ) {
		$try = $cur === '' ? $word : $cur . ' ' . $word;
		if ( $pdf->GetStringWidth( $try ) <= $max_width ) {
			$cur = $try;
			continue;
		}
		if ( $cur !== '' ) {
			$rows[] = $cur;
		}
		while ( $word !== '' && $pdf->GetStringWidth( $word ) > $max_width ) {
			$cut = emerson_pdf_fit_prefix( $pdf, $word, $max_width );
			if ( $cut < 1 ) {
				break;
			}
			$rows[] = mb_substr( $word, 0, $cut );
			$word   = ltrim( mb_substr( $word, $cut ) );
		}
		$cur = $word;
	}
	if ( $cur !== '' ) {
		$rows[] = $cur;
	}
	return $rows;
}

/**
 * @param list<string> $lines
 * @return array{font:float,leading:float,lines:list<string>}
 */
function emerson_pdf_fit_cell( FPDF $pdf, array $lines, float $max_width, float $max_height ): array {
	$best = array(
		'font'    => 4.5,
		'leading' => 2.3,
		'lines'   => array(),
	);
	foreach ( array( 7.0, 6.5, 6.0, 5.5, 5.0, 4.5 ) as $size ) {
		$pdf->SetFont( 'DejaVu', '', $size );
		$leading = max( 2.2, $size * 0.42 );
		$wrapped = array();
		foreach ( $lines as $line ) {
			foreach ( emerson_pdf_wrap_line( $pdf, (string) $line, $max_width ) as $row ) {
				$wrapped[] = $row;
			}
		}
		$best = array(
			'font'    => $size,
			'leading' => $leading,
			'lines'   => $wrapped,
		);
		if ( count( $wrapped ) * $leading <= $max_height ) {
			return $best;
		}
	}
	$count = max( 1, count( $best['lines'] ) );
	$best['leading'] = min( $best['leading'], $max_height / $count );
	return $best;
}

/** Twelve monthly pages for $year, from Church Admin plus the Sunday Morning Schedule sheet. */
function emerson_year_planner_pdf( int $year ): void {
	$size = get_option( 'church_admin_pdf_size' ) ?: 'Letter';
	$pdf  = emerson_pdf_new( 'L', $size );
	for ( $month = 1; $month <= 12; $month++ ) {
		if ( $month > 1 ) {
			$pdf->AddPage( 'L', $size );
		}
		emerson_pdf_draw_month( $pdf, $year, $month );
	}
	$pdf->Output( 'I', "Emerson-year-planner-{$year}.pdf" );
	exit;
}

/** One month on one page, wrapping each day's text so it stays in the box. */
function emerson_monthly_calendar_pdf( int $year, int $month ): void {
	$pdf = emerson_pdf_new( 'L', get_option( 'church_admin_pdf_size' ) ?: 'Letter' );
	emerson_pdf_draw_month( $pdf, $year, $month );
	$pdf->Output( 'I', sprintf( 'Emerson-calendar-%d-%02d.pdf', $year, $month ) );
	exit;
}
