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

/** January–December of $year, 3 across and 4 down; days with a year-planner event take their category colour. */
function emerson_year_planner_pdf( int $year ): void {
	global $wpdb;
	$pdf  = emerson_pdf_new( 'L', 'A4' );
	$days = emerson_pdf_day_names();

	$pdf->SetXY( 10, 5 );
	$pdf->SetFont( 'DejaVu', 'B', 18 );
	$pdf->Cell( 0, 8, sprintf( '%s %d', get_option( 'blogname' ), $year ), 0, 0, 'C' );

	$colours = array();
	$rows    = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT b.start_date, a.bgcolor FROM {$wpdb->prefix}church_admin_calendar_category a
			JOIN {$wpdb->prefix}church_admin_calendar_date b ON a.cat_id = b.cat_id
			WHERE b.year_planner = '1' AND b.start_date BETWEEN %s AND %s ORDER BY b.start_date, b.start_time",
			"{$year}-01-01",
			"{$year}-12-31"
		)
	);
	foreach ( $rows as $row ) {
		$colours[ $row->start_date ] = $colours[ $row->start_date ] ?? emerson_pdf_rgb( $row->bgcolor );
	}

	for ( $month = 1; $month <= 12; $month++ ) {
		$x        = 10 + ( ( $month - 1 ) % 3 ) * 80;
		$y        = 15 + intdiv( $month - 1, 3 ) * 44;
		$first    = sprintf( '%d-%02d-01', $year, $month );
		$startday = (int) wp_date( 'w', strtotime( $first . ' 12:00:00' ) );
		$length   = (int) gmdate( 't', strtotime( $first ) );

		$pdf->SetXY( $x, $y );
		$pdf->SetFont( 'DejaVu', 'B', 10 );
		$pdf->Cell( 70, 7, date_i18n( 'F', strtotime( $first . ' 12:00:00' ) ) . ' ' . $year, 0, 0, 'C' );
		$pdf->SetXY( $x, $y + 7 );
		$pdf->SetFont( 'DejaVu', '', 8 );
		foreach ( $days as $name ) {
			$pdf->Cell( 10, 5, $name, 1, 0, 'C' );
		}
		for ( $cell = 0; $cell < 42; $cell++ ) {
			if ( 0 === $cell % 7 ) {
				$pdf->SetXY( $x, $y + 12 + intdiv( $cell, 7 ) * 5 );
			}
			$day = $cell - $startday + 1;
			if ( $day < 1 || $day > $length ) {
				$pdf->SetFillColor( 192, 192, 192 );
				$pdf->Cell( 10, 5, '', 1, 0, 'C', true );
				continue;
			}
			$rgb = $colours[ sprintf( '%d-%02d-%02d', $year, $month, $day ) ] ?? array( 255, 255, 255 );
			$pdf->SetFillColor( $rgb[0], $rgb[1], $rgb[2] );
			$pdf->Cell( 10, 5, (string) $day, 1, 0, 'L', true );
		}
	}

	$y = 23;
	$pdf->SetFont( 'DejaVu', '', 7 );
	foreach ( $wpdb->get_results( "SELECT category, bgcolor FROM {$wpdb->prefix}church_admin_calendar_category" ) as $category ) {
		$rgb = emerson_pdf_rgb( $category->bgcolor ) ?? array( 255, 255, 255 );
		$pdf->SetXY( 248, $y );
		$pdf->SetFillColor( $rgb[0], $rgb[1], $rgb[2] );
		$pdf->Cell( 6, 5, ' ', 0, 0, 'L', true );
		$pdf->Cell( 41, 5, $category->category, 0, 0, 'L' );
		$pdf->Rect( 248, $y, 47, 5 );
		$y += 6;
	}
	$pdf->Output( 'I', "Emerson-year-planner-{$year}.pdf" );
	exit;
}

/** One month on one page, every event in its own day's box (up to three, then "More events…"). */
function emerson_monthly_calendar_pdf( int $year, int $month ): void {
	global $wpdb;
	$pdf    = emerson_pdf_new( 'L', get_option( 'church_admin_pdf_size' ) ?: 'Letter' );
	$first  = sprintf( '%d-%02d-01', $year, $month );
	$noon   = strtotime( $first . ' 12:00:00' );
	$length = (int) gmdate( 't', $noon );
	$start  = (int) gmdate( 'w', $noon );
	$weeks  = (int) ceil( ( $start + $length ) / 7 );

	$left   = 10;
	$top    = 22;
	$width  = ( $pdf->GetPageWidth() - 20 ) / 7;
	$header = 10;
	$height = ( $pdf->GetPageHeight() - $top - $header - 10 ) / $weeks;

	$pdf->SetXY( $left, 8 );
	$pdf->SetFont( 'DejaVu', 'B', 16 );
	$pdf->Cell( $width * 7, 10, sprintf( 'Calendar %s', date_i18n( 'F Y', $noon ) ), 0, 0, 'C' );

	$pdf->SetFont( 'DejaVu', '', 12 );
	foreach ( emerson_pdf_day_names() as $i => $name ) {
		$pdf->SetXY( $left + $i * $width, $top );
		$pdf->Cell( $width, $header, $name, 1, 0, 'C' );
	}

	$events = array();
	$rows   = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.start_date, a.start_time, a.title FROM {$wpdb->prefix}church_admin_calendar_date a
			JOIN {$wpdb->prefix}church_admin_calendar_category b ON a.cat_id = b.cat_id
			WHERE a.start_date BETWEEN %s AND %s ORDER BY a.start_date, a.start_time",
			$first,
			sprintf( '%d-%02d-%02d', $year, $month, $length )
		)
	);
	foreach ( $rows as $row ) {
		$events[ (int) substr( $row->start_date, 8, 2 ) ][] = mysql2date( get_option( 'time_format' ), $row->start_time ) . ' ' . $row->title;
	}

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
		$pdf->SetFont( 'DejaVu', 'B', 10 );
		$pdf->Cell( 8, 5, (string) $day );

		$pdf->SetFont( 'DejaVu', '', 7 );
		$lines = $events[ $day ] ?? array();
		$fit   = max( 1, (int) floor( ( $height - 7 ) / 4 ) );
		if ( count( $lines ) > $fit ) {
			$lines = array_merge( array_slice( $lines, 0, $fit - 1 ), array( __( 'More events…', 'church-admin' ) ) );
		}
		foreach ( $lines as $i => $line ) {
			while ( $pdf->GetStringWidth( $line ) > $width - 2 && mb_strlen( $line ) > 1 ) {
				$line = rtrim( mb_substr( $line, 0, -2 ) ) . '…';
			}
			$pdf->SetXY( $x + 1, $y + 7 + $i * 4 );
			$pdf->Cell( $width - 2, 4, $line );
		}
	}
	$pdf->Output( 'I', sprintf( 'Emerson-calendar-%d-%02d.pdf', $year, $month ) );
	exit;
}
