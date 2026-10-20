<?php
/**
 * Documents page: three lists of files from the Media Library.
 *
 * Board minutes, congregational meetings, and other documents. The dashboard
 * screen uploads a file and puts it on a list. The page shortcodes draw the lists.
 */

declare( strict_types=1 );

const EMERSON_DOCUMENTS_OPTION = 'emerson_documents';

/**
 * @return array<string,string>
 */
function emerson_documents_lists(): array {
	return array(
		'board'           => 'Board of Trustees meetings',
		'congregational'  => 'Congregational Meetings',
		'other'           => 'Other Documents',
	);
}

/**
 * @return list<array{id:string,list:string,label:string,note:string,attachment_id:int,url:string}>
 */
function emerson_documents_all(): array {
	$stored = get_option( EMERSON_DOCUMENTS_OPTION, null );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$out = array();
	foreach ( $stored as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$list = (string) ( $row['list'] ?? '' );
		if ( ! isset( emerson_documents_lists()[ $list ] ) ) {
			continue;
		}
		$id = (string) ( $row['id'] ?? '' );
		if ( $id === '' ) {
			continue;
		}
		$out[] = array(
			'id'            => $id,
			'list'          => $list,
			'label'         => (string) ( $row['label'] ?? '' ),
			'note'          => (string) ( $row['note'] ?? '' ),
			'attachment_id' => (int) ( $row['attachment_id'] ?? 0 ),
			'url'           => (string) ( $row['url'] ?? '' ),
		);
	}
	return $out;
}

/**
 * @param list<array{id:string,list:string,label:string,note:string,attachment_id:int,url:string}> $docs
 */
function emerson_documents_save( array $docs ): void {
	update_option( EMERSON_DOCUMENTS_OPTION, array_values( $docs ), false );
}

function emerson_documents_file_url( array $doc ): string {
	if ( $doc['attachment_id'] > 0 ) {
		$url = wp_get_attachment_url( $doc['attachment_id'] );
		if ( is_string( $url ) && $url !== '' ) {
			return $url;
		}
	}
	return $doc['url'];
}

/**
 * @return list<array{url:string,label:string}>
 */
function emerson_documents_links_from_html( string $html ): array {
	$out = array();
	if ( ! preg_match_all( '#<a\s+href="([^"]+)"[^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER ) ) {
		return $out;
	}
	foreach ( $matches as $match ) {
		$url = html_entity_decode( $match[1], ENT_QUOTES );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			continue;
		}
		$out[] = array(
			'url'   => $url,
			'label' => trim( wp_strip_all_tags( $match[2] ) ),
		);
	}
	return $out;
}

function emerson_documents_plain_note( string $html ): string {
	if ( stripos( $html, '<a ' ) !== false ) {
		return '';
	}
	$text = trim( wp_strip_all_tags( $html ) );
	if ( $text === '' || strcasecmp( $text, 'Missing' ) === 0 ) {
		return '';
	}
	return $text;
}

function emerson_documents_attachment_from_url( string $url ): int {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( ! preg_match( '#/uploads/(.+)$#', $path, $match ) ) {
		return 0;
	}
	global $wpdb;
	$found = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			$match[1]
		)
	);
	return $found ? (int) $found : 0;
}

/**
 * Copy the three TablePress tables into the document list once.
 *
 * @return list<array{id:string,list:string,label:string,note:string,attachment_id:int,url:string}>
 */
function emerson_documents_import_tables(): array {
	$sources = array(
		'board'          => 506,
		'congregational' => 512,
		'other'          => 515,
	);
	$docs = array();
	foreach ( $sources as $list => $post_id ) {
		$raw  = get_post_field( 'post_content', $post_id );
		$rows = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $rows ) ) {
			continue;
		}
		foreach ( $rows as $index => $row ) {
			if ( 0 === $index || ! is_array( $row ) ) {
				continue;
			}
			$cells = array_map( 'strval', $row );
			$note  = 'board' === $list ? emerson_documents_plain_note( $cells[1] ?? '' ) : '';
			$seen  = array();
			foreach ( $cells as $cell_index => $cell ) {
				foreach ( emerson_documents_links_from_html( $cell ) as $link ) {
					if ( isset( $seen[ $link['url'] ] ) ) {
						continue;
					}
					$seen[ $link['url'] ] = true;
					$label = $link['label'] !== '' ? $link['label'] : trim( wp_strip_all_tags( $cells[0] ?? '' ) );
					$docs[] = array(
						'id'            => wp_generate_password( 8, false ),
						'list'          => $list,
						'label'         => $label !== '' ? $label : basename( (string) wp_parse_url( $link['url'], PHP_URL_PATH ) ),
						'note'          => ( 'board' === $list && $cell_index > 1 ) ? $note : '',
						'attachment_id' => emerson_documents_attachment_from_url( $link['url'] ),
						'url'           => $link['url'],
					);
				}
			}
		}
	}
	return $docs;
}

function emerson_documents_ensure(): void {
	$stored = get_option( EMERSON_DOCUMENTS_OPTION, null );
	if ( null !== $stored && false !== $stored ) {
		return;
	}
	emerson_documents_save( emerson_documents_import_tables() );
}
add_action( 'init', 'emerson_documents_ensure' );

/**
 * @param array<string,string>|string $attributes
 */
function emerson_documents_shortcode( $attributes = array() ): string {
	$attributes = shortcode_atts( array( 'list' => '' ), is_array( $attributes ) ? $attributes : array(), 'emerson_documents' );
	$list       = (string) $attributes['list'];
	if ( ! isset( emerson_documents_lists()[ $list ] ) ) {
		return '';
	}
	$items = array();
	foreach ( emerson_documents_all() as $doc ) {
		if ( $doc['list'] !== $list ) {
			continue;
		}
		$url = emerson_documents_file_url( $doc );
		if ( $url === '' || $doc['label'] === '' ) {
			continue;
		}
		$items[] = $doc;
	}
	if ( ! $items ) {
		return '<p class="emerson-documents__empty">Nothing is listed here yet.</p>';
	}
	$html = '<ul class="emerson-documents">';
	foreach ( $items as $doc ) {
		$html .= '<li><a href="' . esc_url( emerson_documents_file_url( $doc ) ) . '">' . esc_html( $doc['label'] ) . '</a>';
		if ( $doc['note'] !== '' ) {
			$html .= ' <span class="emerson-documents__note">' . esc_html( $doc['note'] ) . '</span>';
		}
		$html .= '</li>';
	}
	$html .= '</ul>';
	return $html;
}
add_shortcode( 'emerson_documents', 'emerson_documents_shortcode' );

/**
 * Three category columns on the public Documents page. Each list shows ten
 * files, and the rest scroll inside that column.
 */
function emerson_documents_public_layout( string $content ): string {
	if ( is_admin() || ! is_page( 'documents' ) || str_contains( $content, 'emerson-documents-board' ) ) {
		return $content;
	}
	$sections = '';
	$rest     = preg_replace_callback(
		'/(<h[23]\b[^>]*>.*?<\/h[23]>\s*)(<(?:ul|p)\b[^>]*class="[^"]*emerson-documents[^"]*"[^>]*>.*?<\/(?:ul|p)>)/s',
		static function ( array $match ) use ( &$sections ): string {
			$sections .= '<section class="emerson-documents-column">' . $match[1]
				. '<div class="emerson-documents-scroll">' . $match[2] . '</div></section>';
			return '';
		},
		$content
	);
	if ( ! is_string( $rest ) || $sections === '' ) {
		return $content;
	}
	$board = '<div class="emerson-documents-board">' . $sections . '</div>';
	if ( trim( wp_strip_all_tags( $rest ) ) === '' ) {
		return $board;
	}
	return $board . $rest;
}
add_filter( 'the_content', 'emerson_documents_public_layout', 12 );

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Documents',
			'Documents',
			'edit_pages',
			'emerson-documents',
			'emerson_documents_admin_page',
			'dashicons-media-document',
			63
		);
	}
);

/**
 * @return int|WP_Error
 */
function emerson_documents_take_upload( string $field ) {
	if ( empty( $_FILES[ $field ]['name'] ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$added = media_handle_upload( $field, 0 );
	return is_wp_error( $added ) ? $added : (int) $added;
}

function emerson_documents_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || ( empty( $_POST['emerson_documents_save'] ) && empty( $_POST['doc_move'] ) ) ) {
		return null;
	}
	check_admin_referer( 'emerson_documents_save' );
	$list = isset( $_POST['doc_list'] ) ? sanitize_key( wp_unslash( (string) $_POST['doc_list'] ) ) : '';
	if ( ! isset( emerson_documents_lists()[ $list ] ) ) {
		return 'Choose which list this file belongs on.';
	}
	$docs  = emerson_documents_all();
	$move  = isset( $_POST['doc_move'] ) ? sanitize_key( wp_unslash( (string) $_POST['doc_move'] ) ) : '';
	$moved = isset( $_POST['doc_move_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['doc_move_id'] ) ) : '';

	$new_label = isset( $_POST['new_label'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['new_label'] ) ) : '';
	$new_note  = isset( $_POST['new_note'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['new_note'] ) ) : '';
	$upload    = emerson_documents_take_upload( 'new_file' );
	if ( is_wp_error( $upload ) ) {
		return $upload->get_error_message();
	}
	if ( $upload > 0 ) {
		if ( $new_label === '' ) {
			$file = get_attached_file( $upload );
			$new_label = $file ? sanitize_text_field( basename( $file ) ) : 'Document';
		}
		array_unshift(
			$docs,
			array(
				'id'            => wp_generate_password( 8, false ),
				'list'          => $list,
				'label'         => $new_label,
				'note'          => $new_note,
				'attachment_id' => $upload,
				'url'           => '',
			)
		);
	}

	$next = array();
	foreach ( $docs as $doc ) {
		if ( $doc['list'] !== $list ) {
			$next[] = $doc;
			continue;
		}
		$id = $doc['id'];
		if ( ! empty( $_POST[ 'remove_' . $id ] ) ) {
			continue;
		}
		if ( isset( $_POST[ 'label_' . $id ] ) ) {
			$label = sanitize_text_field( wp_unslash( (string) $_POST[ 'label_' . $id ] ) );
			if ( $label === '' ) {
				return 'Every document needs the words that should appear as the link.';
			}
			$doc['label'] = $label;
			$doc['note']  = sanitize_text_field( wp_unslash( (string) ( $_POST[ 'note_' . $id ] ?? '' ) ) );
		}
		$replaced = emerson_documents_take_upload( 'replace_' . $id );
		if ( is_wp_error( $replaced ) ) {
			return $replaced->get_error_message();
		}
		if ( $replaced > 0 ) {
			$doc['attachment_id'] = $replaced;
			$doc['url']           = '';
		}
		$next[] = $doc;
	}

	if ( $move !== '' && $moved !== '' ) {
		$indexes = array();
		foreach ( $next as $index => $doc ) {
			if ( $doc['list'] === $list ) {
				$indexes[] = $index;
			}
		}
		$at = null;
		foreach ( $indexes as $position => $index ) {
			if ( $next[ $index ]['id'] === $moved ) {
				$at = $position;
				break;
			}
		}
		if ( null !== $at ) {
			$swap = 'up' === $move ? $at - 1 : $at + 1;
			if ( isset( $indexes[ $swap ] ) ) {
				$left  = $indexes[ $at ];
				$right = $indexes[ $swap ];
				$hold  = $next[ $left ];
				$next[ $left ]  = $next[ $right ];
				$next[ $right ] = $hold;
			}
		}
	}

	emerson_documents_save( $next );
	return 'Saved. The Documents page will show this list.';
}

function emerson_documents_admin_row( array $doc ): string {
	$id   = $doc['id'];
	$url  = emerson_documents_file_url( $doc );
	$name = $doc['attachment_id'] > 0 ? get_the_title( $doc['attachment_id'] ) : basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$html = '<div class="emerson-doc-row" data-doc-id="' . esc_attr( $id ) . '">';
	$html .= '<p><label for="label-' . esc_attr( $id ) . '">Link text<br>';
	$html .= '<input name="label_' . esc_attr( $id ) . '" id="label-' . esc_attr( $id ) . '" type="text" class="regular-text" maxlength="160" value="' . esc_attr( $doc['label'] ) . '"></label></p>';
	$html .= '<p><label for="note-' . esc_attr( $id ) . '">Note<br>';
	$html .= '<input name="note_' . esc_attr( $id ) . '" id="note-' . esc_attr( $id ) . '" type="text" class="regular-text" maxlength="160" value="' . esc_attr( $doc['note'] ) . '"></label></p>';
	if ( $url !== '' ) {
		$html .= '<p class="emerson-doc-row__file">Current file: <a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></p>';
	}
	$html .= '<p><label for="replace-' . esc_attr( $id ) . '">Replace file<br>';
	$html .= '<input name="replace_' . esc_attr( $id ) . '" id="replace-' . esc_attr( $id ) . '" type="file"></label></p>';
	$html .= '<p><label><input type="checkbox" name="remove_' . esc_attr( $id ) . '" value="1"> Remove from this list</label></p>';
	$html .= '<p><button type="submit" name="doc_move" value="up" class="button" data-id="' . esc_attr( $id ) . '">Move up</button> ';
	$html .= '<button type="submit" name="doc_move" value="down" class="button" data-id="' . esc_attr( $id ) . '">Move down</button></p>';
	$html .= '</div>';
	return $html;
}

function emerson_documents_ajax_save(): void {
	$before  = emerson_documents_all();
	$message = emerson_documents_admin_save();
	if ( ! is_string( $message ) || 0 !== strpos( $message, 'Saved.' ) ) {
		wp_send_json_error(
			array( 'message' => is_string( $message ) && $message !== '' ? $message : 'Could not save.' )
		);
	}
	$before_ids = array();
	foreach ( $before as $doc ) {
		$before_ids[] = $doc['id'];
	}
	$added = null;
	foreach ( emerson_documents_all() as $doc ) {
		if ( ! in_array( $doc['id'], $before_ids, true ) ) {
			$added = $doc;
			break;
		}
	}
	$payload = array( 'message' => $message );
	if ( is_array( $added ) ) {
		$payload['id']  = $added['id'];
		$payload['row'] = emerson_documents_admin_row( $added );
	}
	$files = array();
	foreach ( emerson_documents_all() as $doc ) {
		foreach ( $before as $old ) {
			if ( $old['id'] !== $doc['id'] || $old['attachment_id'] === $doc['attachment_id'] ) {
				continue;
			}
			$url  = emerson_documents_file_url( $doc );
			$name = $doc['attachment_id'] > 0 ? get_the_title( $doc['attachment_id'] ) : basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
			$files[ $doc['id'] ] = 'Current file: <a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
		}
	}
	if ( $files ) {
		$payload['files'] = $files;
	}
	wp_send_json_success( $payload );
}
add_action( 'wp_ajax_emerson_documents_save', 'emerson_documents_ajax_save' );

add_action(
	'admin_enqueue_scripts',
	static function ( string $hook ): void {
		if ( 'toplevel_page_emerson-documents' !== $hook ) {
			return;
		}
		$path = get_stylesheet_directory() . '/assets/js/documents-admin.js';
		wp_enqueue_script(
			'emerson-documents-admin',
			get_stylesheet_directory_uri() . '/assets/js/documents-admin.js',
			array(),
			(string) filemtime( $path ),
			true
		);
		wp_localize_script(
			'emerson-documents-admin',
			'emersonDocumentsAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			)
		);
	}
);

function emerson_documents_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit documents.' ) );
	}
	$notice = emerson_documents_admin_save();
	echo '<div class="wrap"><h1>Documents</h1>';
	if ( $notice ) {
		$bad = 0 !== strpos( $notice, 'Saved.' );
		echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>Files added here show on the public <a href="' . esc_url( home_url( '/documents/' ) ) . '">Documents</a> page. Upload the file, type the words for the link, and choose the list. The file is stored in the Media Library. Adding, moving, or removing stays on this list.</p>';
	echo '<style>
		.emerson-doc-row{margin:0 0 1.25rem;padding-bottom:1rem;border-bottom:1px solid #dcdcde;}
		.emerson-doc-rows.is-scroll{overflow-y:auto;padding-right:0.35rem;}
		.emerson-documents-admin__status{min-height:1.4em;margin:0.25rem 0 1rem;}
		.emerson-documents-admin__status.is-error{color:#b32d2e;}
		.emerson-documents-admin__actions{display:flex;flex-wrap:wrap;align-items:center;gap:0.6rem;margin:0 0 1.5rem;}
		.emerson-documents-admin__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.5rem 2rem;align-items:start;}
		.emerson-documents-admin__module--wide{grid-column:1 / -1;}
		.emerson-documents-admin__module .form-table th{width:7rem;padding-left:0;}
		@media (max-width:782px){.emerson-documents-admin__grid{grid-template-columns:1fr;}.emerson-documents-admin__module--wide{grid-column:auto;}}
		.emerson-docs-top{position:fixed;z-index:100000;right:1.5rem;bottom:1.5rem;display:flex;align-items:center;justify-content:center;width:3.25rem;height:3.25rem;padding:0;border:0;border-radius:50%;cursor:pointer;background:conic-gradient(#e40303,#ff8c00,#ffed00,#008026,#24408e,#732982,#e40303);box-shadow:0 6px 18px rgba(20,24,50,.28);opacity:0;pointer-events:none;transition:opacity .25s ease;}
		.emerson-docs-top[hidden]{display:none;}
		.emerson-docs-top.is-visible{opacity:1;pointer-events:auto;}
		.emerson-docs-top svg{display:block;width:1.45rem;height:1.45rem;fill:#fff;filter:drop-shadow(0 1px 1px rgba(0,0,0,.45));}
	</style>';
	$docs  = emerson_documents_all();
	$lists = emerson_documents_lists();
	$order = array( 'congregational', 'other', 'board' );
	echo '<div class="emerson-documents-admin__grid">';
	foreach ( $order as $key ) {
		$title = $lists[ $key ];
		$wide  = 'board' === $key ? ' emerson-documents-admin__module--wide' : '';
		echo '<section class="emerson-documents-admin__module' . $wide . '">';
		echo '<h2 id="emerson-docs-' . esc_attr( $key ) . '">' . esc_html( $title ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data" class="emerson-documents-admin__form" data-list="' . esc_attr( $key ) . '">';
		wp_nonce_field( 'emerson_documents_save' );
		echo '<input type="hidden" name="doc_list" value="' . esc_attr( $key ) . '">';
		echo '<input type="hidden" name="action" value="emerson_documents_save">';
		echo '<p class="emerson-documents-admin__status" role="status"></p>';
		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="new-label-' . esc_attr( $key ) . '">Link text</label></th><td>';
		echo '<input name="new_label" id="new-label-' . esc_attr( $key ) . '" type="text" class="regular-text" maxlength="160" placeholder="March 18, 2026">';
		echo '<p class="description">The words visitors click. For minutes, use the meeting date.</p>';
		echo '</td></tr><tr><th scope="row"><label for="new-note-' . esc_attr( $key ) . '">Note</label></th><td>';
		echo '<input name="new_note" id="new-note-' . esc_attr( $key ) . '" type="text" class="regular-text" maxlength="160" placeholder="Special Board Meeting">';
		echo '<p class="description">Optional. Shown next to the link.</p>';
		echo '</td></tr><tr><th scope="row"><label for="new-file-' . esc_attr( $key ) . '">File</label></th><td>';
		echo '<input name="new_file" id="new-file-' . esc_attr( $key ) . '" type="file">';
		echo '</td></tr></table>';
		echo '<p class="emerson-documents-admin__actions">';
		echo '<button type="submit" name="emerson_documents_save" value="add" class="button button-primary">Add to ' . esc_html( $title ) . '</button>';
		echo '<button type="submit" name="emerson_documents_save" value="1" class="button">Save ' . esc_html( $title ) . '</button>';
		echo '</p>';
		echo '<h3>On this list</h3>';
		echo '<div class="emerson-doc-rows">';
		$mine = array();
		foreach ( $docs as $doc ) {
			if ( $doc['list'] === $key ) {
				$mine[] = $doc;
			}
		}
		if ( ! $mine ) {
			echo '<p class="emerson-doc-empty">Nothing is on this list yet.</p>';
		}
		foreach ( $mine as $doc ) {
			echo emerson_documents_admin_row( $doc );
		}
		echo '</div>';
		echo '<input type="hidden" name="doc_move_id" value="">';
		echo '</form></section>';
	}
	echo '</div>';
	echo '<button type="button" class="emerson-docs-top" hidden aria-label="Back to top">';
	echo '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.2 4.8 13.1a1.15 1.15 0 0 0 1.7 1.55L12 8.7l5.5 6a1.15 1.15 0 0 0 1.7-1.55L12 5.2z"/></svg>';
	echo '</button>';
	echo '</div>';
}
