<?php
/**
 * Private spreadsheets: files live outside the web root and are listed only for
 * administrators and the Finance role. Everyone else gets no markup and no file.
 *
 * Drop files in private-files/ on this Mac (xlsx, csv, pdf, ods, or a Numbers package).
 * PDFs and Excel/CSV open in the browser. Other types download.
 */

declare( strict_types=1 );

const EMERSON_PRIVATE_CAP = 'emerson_view_spreadsheets';

function emerson_private_files_dir(): string {
	$outside = '/var/emerson-private';
	if ( is_dir( $outside ) ) {
		return $outside;
	}
	return WP_CONTENT_DIR . '/emerson-private';
}

function emerson_can_view_private_files( ?int $user_id = null ): bool {
	$user_id = $user_id ?? get_current_user_id();
	return $user_id > 0 && user_can( $user_id, EMERSON_PRIVATE_CAP );
}

add_action(
	'init',
	static function (): void {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( EMERSON_PRIVATE_CAP ) ) {
			$admin->add_cap( EMERSON_PRIVATE_CAP );
		}
		$finance = get_role( 'finance' );
		if ( $finance ) {
			if ( ! $finance->has_cap( 'read' ) ) {
				$finance->add_cap( 'read' );
			}
			if ( ! $finance->has_cap( EMERSON_PRIVATE_CAP ) ) {
				$finance->add_cap( EMERSON_PRIVATE_CAP );
			}
		}
	}
);

function emerson_private_file_extension( string $name ): string {
	return strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
}

function emerson_private_file_is_allowed( string $name ): bool {
	if ( '' === $name || str_contains( $name, '..' ) || str_contains( $name, '/' ) || str_contains( $name, '\\' ) ) {
		return false;
	}
	return (bool) preg_match( '/^[A-Za-z0-9][A-Za-z0-9._ -]*\.(xlsx|xls|csv|pdf|ods|zip|numbers)$/i', $name );
}

function emerson_private_file_kind( string $name ): string {
	return match ( emerson_private_file_extension( $name ) ) {
		'pdf' => 'PDF',
		'xlsx', 'xls' => 'Excel',
		'csv' => 'CSV',
		'ods' => 'OpenDocument',
		'numbers' => 'Numbers',
		default => 'download',
	};
}

function emerson_private_file_can_preview( string $name ): bool {
	return in_array( emerson_private_file_extension( $name ), array( 'xlsx', 'csv', 'pdf' ), true );
}

function emerson_private_file_mime( string $name ): string {
	return match ( emerson_private_file_extension( $name ) ) {
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'xls' => 'application/vnd.ms-excel',
		'csv' => 'text/csv; charset=utf-8',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		'pdf' => 'application/pdf',
		'zip' => 'application/zip',
		default => 'application/octet-stream',
	};
}

function emerson_private_file_url( string $name, bool $download = false, ?int $sheet = null ): string {
	$args = array(
		'action' => 'emerson_private_file',
		'file'   => $name,
	);
	if ( $download ) {
		$args['download'] = '1';
	}
	if ( null !== $sheet ) {
		$args['sheet'] = (string) $sheet;
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'emerson_private_file' );
}

/** @return list<string> */
function emerson_private_file_list(): array {
	$dir = emerson_private_files_dir();
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$names = array();
	foreach ( scandir( $dir ) ?: array() as $name ) {
		if ( '.' === $name || '..' === $name || str_starts_with( $name, '.' ) || 'README.md' === $name ) {
			continue;
		}
		if ( ! emerson_private_file_is_allowed( $name ) ) {
			continue;
		}
		$path = $dir . '/' . $name;
		if ( is_file( $path ) || ( is_dir( $path ) && str_ends_with( strtolower( $name ), '.numbers' ) ) ) {
			$names[] = $name;
		}
	}
	natcasesort( $names );
	return array_values( $names );
}

add_shortcode(
	'emerson_private_files',
	static function (): string {
		if ( ! emerson_can_view_private_files() ) {
			return '';
		}
		$files = emerson_private_file_list();
		$html  = '<div class="emerson-private-files" id="spreadsheets">';
		$html .= '<h3>Spreadsheets</h3>';
		$html .= '<p>These files are only listed for accounts with the Finance role (and administrators). Other members do not see this section.</p>';
		if ( ! $files ) {
			$html .= '<p>No spreadsheets have been added yet.</p></div>';
			return $html;
		}
		$html .= '<ul>';
		foreach ( $files as $name ) {
			$label = preg_replace( '/\.(xlsx|xls|csv|pdf|ods|zip|numbers)$/i', '', $name );
			$html .= '<li><a href="' . esc_url( emerson_private_file_url( $name ) ) . '">' . esc_html( $label !== '' ? $label : $name ) . '</a>';
			$html .= ' <span class="emerson-private-files__kind">' . esc_html( emerson_private_file_kind( $name ) ) . '</span>';
			if ( emerson_private_file_can_preview( $name ) && 'pdf' !== emerson_private_file_extension( $name ) ) {
				$html .= ' <a class="emerson-private-files__download" href="' . esc_url( emerson_private_file_url( $name, true ) ) . '">download</a>';
			}
			$html .= '</li>';
		}
		$html .= '</ul></div>';
		return $html;
	}
);

add_action(
	'admin_post_nopriv_emerson_private_file',
	static function (): void {
		auth_redirect();
		exit;
	}
);

add_action(
	'admin_post_emerson_private_file',
	static function (): void {
		if ( ! emerson_can_view_private_files() ) {
			wp_die( 'You do not have access to these files.', 'Forbidden', array( 'response' => 403 ) );
		}
		check_admin_referer( 'emerson_private_file' );
		$name = sanitize_file_name( (string) wp_unslash( $_GET['file'] ?? '' ) );
		if ( ! emerson_private_file_is_allowed( $name ) ) {
			wp_die( 'That file is not available.', 'Not found', array( 'response' => 404 ) );
		}
		$path     = emerson_private_files_dir() . '/' . $name;
		$download = '1' === (string) ( $_GET['download'] ?? '' );
		if ( is_dir( $path ) && str_ends_with( strtolower( $name ), '.numbers' ) ) {
			emerson_private_stream_numbers_zip( $path, $name );
			exit;
		}
		if ( ! is_file( $path ) ) {
			wp_die( 'That file is not available.', 'Not found', array( 'response' => 404 ) );
		}
		$ext = emerson_private_file_extension( $name );
		if ( ! $download && in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
			emerson_private_render_spreadsheet( $path, $name );
			exit;
		}
		emerson_private_stream_file( $path, $name, 'pdf' === $ext && ! $download );
		exit;
	}
);

function emerson_private_stream_file( string $path, string $name, bool $inline ): void {
	$mime = emerson_private_file_mime( $name );
	header( 'Content-Type: ' . $mime );
	header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $name . '"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Length: ' . (string) filesize( $path ) );
	readfile( $path );
}

function emerson_private_stream_numbers_zip( string $dir, string $name ): void {
	if ( ! class_exists( 'ZipArchive' ) ) {
		wp_die( 'This Numbers file cannot be downloaded on this server.', 'Unavailable', array( 'response' => 500 ) );
	}
	$tmp = wp_tempnam( $name . '.zip' );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		wp_die( 'This Numbers file cannot be packed.', 'Unavailable', array( 'response' => 500 ) );
	}
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}
		$local = $name . '/' . substr( $file->getPathname(), strlen( $dir ) + 1 );
		$zip->addFile( $file->getPathname(), $local );
	}
	$zip->close();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $name . '.zip"' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Length: ' . (string) filesize( $tmp ) );
	readfile( $tmp );
	wp_delete_file( $tmp );
}

function emerson_private_xml( string $raw ): ?SimpleXMLElement {
	$raw = preg_replace( '/xmlns(:[A-Za-z0-9]+)?="[^"]*"/', '', $raw ) ?? $raw;
	libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $raw, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA );
	libxml_clear_errors();
	return $xml instanceof SimpleXMLElement ? $xml : null;
}

function emerson_private_xlsx_col( string $ref ): int {
	if ( ! preg_match( '/^([A-Z]+)/i', $ref, $match ) ) {
		return 0;
	}
	$n = 0;
	foreach ( str_split( strtoupper( $match[1] ) ) as $ch ) {
		$n = ( $n * 26 ) + ( ord( $ch ) - 64 );
	}
	return max( 0, $n - 1 );
}

/**
 * @return list<list<string>>
 */
function emerson_private_read_csv( string $path ): array {
	$handle = fopen( $path, 'rb' );
	if ( ! $handle ) {
		return array();
	}
	$rows  = array();
	$limit = 500;
	while ( count( $rows ) < $limit && ( $row = fgetcsv( $handle ) ) !== false ) {
		$rows[] = array_map( static fn( $cell ) => (string) $cell, $row );
	}
	fclose( $handle );
	return $rows;
}

/**
 * Excel number-format codes, one per cell style (the s="…" on a cell).
 *
 * @return list<string>
 */
function emerson_private_xlsx_style_formats( ZipArchive $zip ): array {
	$builtin = array(
		14 => 'm/d/yyyy',
		15 => 'd-mmm-yy',
		16 => 'd-mmm',
		17 => 'mmm-yy',
		18 => 'h:mm AM/PM',
		19 => 'h:mm:ss AM/PM',
		20 => 'h:mm',
		21 => 'h:mm:ss',
		22 => 'm/d/yyyy h:mm',
		45 => 'mm:ss',
		46 => 'h:mm:ss',
		47 => 'mm:ss.0',
	);
	$custom  = array();
	$styles  = $zip->getFromName( 'xl/styles.xml' );
	$xml     = is_string( $styles ) ? emerson_private_xml( $styles ) : null;
	if ( $xml && isset( $xml->numFmts->numFmt ) ) {
		foreach ( $xml->numFmts->numFmt as $fmt ) {
			$custom[ (int) $fmt['numFmtId'] ] = (string) $fmt['formatCode'];
		}
	}
	$formats = array();
	if ( $xml && isset( $xml->cellXfs->xf ) ) {
		foreach ( $xml->cellXfs->xf as $xf ) {
			$id        = (int) $xf['numFmtId'];
			$formats[] = $custom[ $id ] ?? ( $builtin[ $id ] ?? 'General' );
		}
	}
	return $formats;
}

function emerson_private_format_kind( string $fmt ): string {
	if ( '' === $fmt || 'general' === strtolower( $fmt ) || '@' === $fmt ) {
		return 'general';
	}
	$bare     = strtolower( (string) preg_replace( '/\[[^\]]*\]|"[^"]*"/', '', $fmt ) );
	$has_date = str_contains( $bare, 'y' ) || 1 === preg_match( '/d/', $bare );
	$has_time = str_contains( $bare, 'h' ) || str_contains( $bare, 's' );
	if ( $has_date && $has_time ) {
		return 'datetime';
	}
	if ( $has_time ) {
		return 'time';
	}
	if ( $has_date ) {
		return 'date';
	}
	return 'general';
}

function emerson_private_format_number( string $raw, string $fmt ): string {
	if ( ! is_numeric( $raw ) ) {
		return $raw;
	}
	// Numbers writes a 30-minute block as the fraction 0.020833 with format [m]"m".
	// That is elapsed minutes plus the letter m, not a decimal or a clock time.
	if ( preg_match( '/\[m\]/i', $fmt ) && ! preg_match( '/\[h\]/i', $fmt ) ) {
		$minutes = (int) round( (float) $raw * 1440 );
		$suffix  = '';
		if ( preg_match_all( '/"([^"]*)"/', $fmt, $quotes ) ) {
			$suffix = implode( '', $quotes[1] );
		}
		return (string) $minutes . $suffix;
	}
	$kind = emerson_private_format_kind( $fmt );
	if ( 'general' === $kind ) {
		if ( preg_match( '/^-?\d+\.0+$/', $raw ) ) {
			return (string) (int) $raw;
		}
		return $raw;
	}
	$serial  = (float) $raw;
	$days    = (int) floor( $serial );
	$seconds = (int) round( ( $serial - $days ) * 86400 );
	$when    = new DateTimeImmutable( '1899-12-30 00:00:00', new DateTimeZone( 'UTC' ) );
	if ( 0 !== $days ) {
		$when = $when->modify( ( $days > 0 ? '+' : '' ) . $days . ' days' );
	}
	if ( 0 !== $seconds ) {
		$when = $when->modify( ( $seconds > 0 ? '+' : '' ) . $seconds . ' seconds' );
	}
	if ( ! $when instanceof DateTimeImmutable ) {
		return $raw;
	}
	if ( 'time' === $kind ) {
		return $when->format( 'g:i A' );
	}
	if ( 'date' === $kind ) {
		return $when->format( 'n/j/Y' );
	}
	return $when->format( 'n/j/Y g:i A' );
}

/**
 * Every worksheet except Numbers' "Export Summary" cover sheet.
 *
 * @return list<array{name:string, rows:list<list<string>>, note:string}>
 */
function emerson_private_read_xlsx( string $path ): array {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return array();
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return array();
	}
	$shared = array();
	$sst    = $zip->getFromName( 'xl/sharedStrings.xml' );
	if ( is_string( $sst ) && $sst !== '' ) {
		$xml = emerson_private_xml( $sst );
		if ( $xml ) {
			foreach ( $xml->si as $si ) {
				$parts = $si->xpath( './/t' ) ?: array();
				$text  = '';
				foreach ( $parts as $part ) {
					$text .= (string) $part;
				}
				$shared[] = $text;
			}
		}
	}
	$formats = emerson_private_xlsx_style_formats( $zip );
	$book    = $zip->getFromName( 'xl/workbook.xml' );
	$rels    = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
	$targets = array();
	if ( is_string( $rels ) && preg_match_all( '/Id="([^"]+)"[^>]*Target="([^"]+)"/', $rels, $found, PREG_SET_ORDER ) ) {
		foreach ( $found as $rel ) {
			$targets[ $rel[1] ] = 'xl/' . ltrim( $rel[2], '/' );
		}
	}
	$sheets = array();
	if ( is_string( $book ) && preg_match_all( '/<sheet\b([^>]*)\/?>/', $book, $tags ) ) {
		foreach ( $tags[1] as $attrs ) {
			$name = '';
			$rid  = '';
			if ( preg_match( '/\bname="([^"]*)"/', $attrs, $match ) ) {
				$name = $match[1];
			}
			if ( preg_match( '/\br:id="([^"]+)"/', $attrs, $match ) ) {
				$rid = $match[1];
			}
			$target = $targets[ $rid ] ?? '';
			if ( '' === $name || '' === $target ) {
				continue;
			}
			$sheets[] = array(
				'name' => $name,
				'path' => $target,
			);
		}
	}
	if ( ! $sheets ) {
		$sheets[] = array(
			'name' => 'Sheet 1',
			'path' => 'xl/worksheets/sheet1.xml',
		);
	}
	$real = array_values(
		array_filter(
			$sheets,
			static fn( array $sheet ): bool => 'export summary' !== strtolower( trim( $sheet['name'] ) )
		)
	);
	if ( $real ) {
		$sheets = $real;
	}

	$out = array();
	foreach ( $sheets as $sheet ) {
		$raw = $zip->getFromName( $sheet['path'] );
		$xml = is_string( $raw ) ? emerson_private_xml( $raw ) : null;
		$rows = array();
		if ( $xml && isset( $xml->sheetData ) ) {
			$max_col = 0;
			foreach ( $xml->sheetData->row as $row ) {
				$r = (int) ( $row['r'] ?? ( count( $rows ) + 1 ) );
				if ( $r > 500 ) {
					break;
				}
				while ( count( $rows ) < $r ) {
					$rows[] = array();
				}
				$line = array();
				foreach ( $row->c as $cell ) {
					$ref = (string) ( $cell['r'] ?? '' );
					$col = $ref !== '' ? emerson_private_xlsx_col( $ref ) : count( $line );
					if ( $col > 50 ) {
						continue;
					}
					$type  = (string) ( $cell['t'] ?? '' );
					$value = '';
					if ( 's' === $type ) {
						$idx   = (int) (string) $cell->v;
						$value = $shared[ $idx ] ?? '';
					} elseif ( 'inlineStr' === $type ) {
						$parts = $cell->xpath( './/t' ) ?: array();
						foreach ( $parts as $part ) {
							$value .= (string) $part;
						}
					} elseif ( isset( $cell->v ) ) {
						$style = (int) ( $cell['s'] ?? -1 );
						$fmt   = $formats[ $style ] ?? 'General';
						$value = emerson_private_format_number( (string) $cell->v, $fmt );
					}
					$line[ $col ] = $value;
					$max_col      = max( $max_col, $col );
				}
				$rows[ $r - 1 ] = $line;
			}
			$width = $max_col + 1;
			$padded_rows = array();
			foreach ( $rows as $line ) {
				$padded = array();
				for ( $i = 0; $i < $width; $i++ ) {
					$padded[] = (string) ( $line[ $i ] ?? '' );
				}
				$padded_rows[] = $padded;
			}
			$rows = $padded_rows;
			while ( $rows && count( array_filter( $rows[ count( $rows ) - 1 ], static fn( $c ) => $c !== '' ) ) === 0 ) {
				array_pop( $rows );
			}
			$used = 0;
			foreach ( $rows as $line ) {
				for ( $i = count( $line ) - 1; $i >= 0; $i-- ) {
					if ( $line[ $i ] !== '' ) {
						$used = max( $used, $i + 1 );
						break;
					}
				}
			}
			if ( $used > 0 && $used < $width ) {
				$rows = array_map( static fn( $line ) => array_slice( $line, 0, $used ), $rows );
			}
		}
		$note = '';
		if ( ! $rows && is_string( $raw ) && str_contains( $raw, 'drawing' ) ) {
			$note = 'This sheet is a drawing from Numbers, not a table. The grids are on the other tabs. Download the Excel file to see the drawing.';
		}
		$out[] = array(
			'name' => $sheet['name'],
			'rows' => $rows,
			'note' => $note,
		);
	}
	$zip->close();
	return $out;
}

/**
 * @param list<array{name:string, rows:list<list<string>>, note:string}> $sheets
 */
function emerson_private_render_sheets( string $name, array $sheets, int $current ): void {
	$label    = preg_replace( '/\.(xlsx|xls|csv)$/i', '', $name );
	$download = emerson_private_file_url( $name, true );
	$back     = home_url( '/members/#spreadsheets' );
	$current  = max( 0, min( $current, count( $sheets ) - 1 ) );
	$sheet    = $sheets[ $current ];
	nocache_headers();
	header( 'Content-Type: text/html; charset=UTF-8' );
	header( 'X-Content-Type-Options: nosniff' );
	echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<title>' . esc_html( $label !== '' ? $label : $name ) . '</title>';
	echo '<link rel="stylesheet" href="' . esc_url( emerson_chapel_url( 'assets/css/custom.css' ) ) . '?v=' . (string) filemtime( emerson_chapel_path( 'assets/css/custom.css' ) ) . '">';
	echo '<style>html,body{background:#fff;color:#1e1e1e;}</style>';
	echo '</head><body class="emerson-private-sheet-page">';
	echo '<p class="emerson-private-sheet-nav"><a href="' . esc_url( $back ) . '">Back to Members</a>';
	echo ' · <a href="' . esc_url( $download ) . '">Download Excel</a></p>';
	echo '<h1>' . esc_html( $label !== '' ? $label : $name ) . '</h1>';
	echo '<p class="emerson-private-sheet-kind">' . esc_html( emerson_private_file_kind( $name ) ) . '</p>';
	if ( count( $sheets ) > 1 ) {
		echo '<nav class="emerson-private-sheet-tabs" aria-label="Worksheets">';
		foreach ( $sheets as $index => $tab ) {
			$class = $index === $current ? ' class="is-current"' : '';
			echo '<a' . $class . ' href="' . esc_url( emerson_private_file_url( $name, false, $index ) ) . '">' . esc_html( $tab['name'] ) . '</a>';
		}
		echo '</nav>';
	}
	if ( '' !== $sheet['note'] ) {
		echo '<p class="emerson-private-sheet-note">' . esc_html( $sheet['note'] ) . '</p>';
	}
	if ( $sheet['rows'] ) {
		echo '<div class="emerson-private-sheet-wrap"><table class="emerson-private-sheet">';
		$first = true;
		foreach ( $sheet['rows'] as $row ) {
			$tag = $first ? 'th' : 'td';
			echo '<tr>';
			foreach ( $row as $cell ) {
				echo '<' . $tag . '>' . esc_html( $cell ) . '</' . $tag . '>';
			}
			echo '</tr>';
			$first = false;
		}
		echo '</table></div>';
	}
	echo '</body></html>';
}

function emerson_private_render_spreadsheet( string $path, string $name ): void {
	$ext = emerson_private_file_extension( $name );
	if ( 'csv' === $ext ) {
		$sheets = array(
			array(
				'name' => $name,
				'rows' => emerson_private_read_csv( $path ),
				'note' => '',
			),
		);
	} else {
		$sheets = emerson_private_read_xlsx( $path );
	}
	$sheets = array_values(
		array_filter(
			$sheets,
			static fn( array $sheet ): bool => $sheet['rows'] || '' !== $sheet['note']
		)
	);
	if ( ! $sheets ) {
		emerson_private_stream_file( $path, $name, false );
		exit;
	}
	$current = isset( $_GET['sheet'] ) ? (int) $_GET['sheet'] : 0;
	emerson_private_render_sheets( $name, $sheets, $current );
}
