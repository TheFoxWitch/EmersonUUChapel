<?php
/**
 * Church Admin Premium front-end register fixes (kept out of the vendor plugin).
 *
 * - Directory "Edit Entry" (?household_id=) opens that household, not only the editor's.
 * - Person type / marital status selects show and save the real DB values.
 * - "Show me on the address list" / fine privacy: the church-admin/register block omits
 *   full_privacy_show, so those checkboxes never render; CA still writes show_me=0 and
 *   all-zero privacy on save. Emerson forces the UI on and restores visibility when the
 *   privacy fields were not posted (form had no privacy section).
 * - Wedding anniversary: CA hides the date field unless church_admin_show_wedding_anniversary
 *   is on (missing here), and only reveals it on marital change — not on load. Emerson
 *   forces the option on the front end, shows/hides for Married, and saves the date to
 *   the household (CA's person save never writes wedding_anniversary).
 * - Typed dates (birthday, anniversary, custom date fields): CA only persists the hidden
 *   Y-m-d input filled by the calendar popup. Typing into the visible *x field is ignored.
 *   Emerson rewrites those POST keys from the typed value before CA saves.
 * - Person photo: CA's draganddrop.js often never loads for the register block, and the
 *   markup has no file input — drops open the image in a new tab. Emerson wires click
 *   and drag/drop on Edit person/address boxes and on the household overview person
 *   image cell (the silhouette placeholder). CA also has a separate household photo
 *   on Edit address (no remove control upstream).
 * - Delete household: CA calls church_admin_premium_address_list() afterward; that function
 *   lives in directory.php (admin only). Stub it on the front end so delete does not fatal.
 * - Email already on file: CA replaces the register form with “Please login” + a second
 *   wp_login_form. On Members that sits under Profile Builder’s login. Emerson strips that
 *   form and opens a dialog: “already registered — please log in above.”
 * - Household overview card: CA has no way back to Members; Emerson inserts a Back link
 *   immediately before “Edit information” (CA’s Edit address control).
 * - Logged-in visits to /register/ keep the WP page title “Register”; Emerson renames the
 *   on-page and browser titles to “Edit Profile” (new sign-up still says Register).
 *
 * Do not patch church-admin-premium for these; CA updates wipe vendor edits.
 * The register page uses the church-admin/register block (and sometimes the shortcode).
 */

declare( strict_types=1 );

/**
 * Front-end household delete ends with church_admin_premium_address_list(), which is only
 * defined when directory.php is loaded (wp-admin). Install a no-op for this request.
 */
function emerson_ca_ensure_address_list_stub(): void {
	if ( function_exists( 'church_admin_premium_address_list' ) ) {
		return;
	}
	/**
	 * @param int $selected_member_type_id Unused; matches CA's signature.
	 */
	function church_admin_premium_address_list( $selected_member_type_id = 0 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		// Intentionally empty — admin directory UI is not available on /register/.
	}
}

/**
 * Whether the current user may edit any directory household on the front end.
 */
function emerson_ca_is_directory_editor(): bool {
	return function_exists( 'church_admin_premium_level_check' )
		&& church_admin_premium_level_check( 'Directory' );
}

/**
 * Household to expose during register render (Directory editors only).
 */
function emerson_ca_register_target_household_id(): int {
	if ( ! emerson_ca_is_directory_editor() ) {
		return 0;
	}

	if ( ! empty( $_GET['household_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested = (int) $_GET['household_id'];
		if ( $requested > 0 ) {
			return $requested;
		}
	}

	if ( empty( $_POST['ca-cmd'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return 0;
	}

	$cmd = sanitize_key( wp_unslash( (string) $_POST['ca-cmd'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$id  = ! empty( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $id < 1 && ! empty( $_POST['people_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$id = (int) $_POST['people_id'];
	}
	if ( $id < 1 ) {
		return 0;
	}

	global $wpdb;
	// CA's household card passes the head person's people_id for "Edit address" (not household_id),
	// so resolve it like the person commands. Treating it as a household id could open the wrong household.
	if ( in_array( $cmd, array( 'edit-person', 'delete-person', 'edit-address' ), true ) ) {
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
				$id
			)
		);
	}
	if ( in_array( $cmd, array( 'delete-household', 'add-person' ), true ) ) {
		return $id;
	}

	return 0;
}

/**
 * Directory person photos only render when photo_permission is truthy (CA checks it in
 * formatted_household). Uploading a portrait on Edit person does not flip that flag, so the
 * image shows on the profile and vanishes on the members directory. Mark permission on upload.
 */
function emerson_ca_allow_directory_photo_for_person( int $people_id ): bool {
	if ( $people_id < 1 ) {
		return false;
	}
	global $wpdb;
	$person = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_id, household_id, attachment_id, photo_permission FROM '
			. $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( ! $person || (int) $person->attachment_id < 1 ) {
		return false;
	}

	$editor_household = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE user_id = %d',
			get_current_user_id()
		)
	);
	$allowed = emerson_ca_is_directory_editor()
		|| ( $editor_household > 0 && (int) $person->household_id === $editor_household );
	if ( ! $allowed ) {
		return false;
	}
	if ( ! empty( $person->photo_permission ) ) {
		return true;
	}

	return false !== $wpdb->update(
		$wpdb->prefix . 'church_admin_people',
		array( 'photo_permission' => 1 ),
		array( 'people_id' => $people_id ),
		array( '%d' ),
		array( '%d' )
	);
}

/**
 * AJAX: after Emerson’s people-upload succeeds, allow the portrait in the directory.
 */
function emerson_ca_ajax_allow_directory_photo(): void {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}
	check_ajax_referer( 'emerson_ca_directory_photo', 'nonce' );
	$people_id = isset( $_POST['people_id'] ) ? (int) $_POST['people_id'] : 0;
	if ( ! emerson_ca_allow_directory_photo_for_person( $people_id ) ) {
		wp_send_json_error( array( 'message' => 'not_updated' ), 400 );
	}
	wp_send_json_success( array( 'people_id' => $people_id ) );
}
add_action( 'wp_ajax_emerson_ca_allow_directory_photo', 'emerson_ca_ajax_allow_directory_photo' );

/**
 * Whether the current user may clear a household or person photo on the front end.
 */
function emerson_ca_can_edit_household_photos( int $household_id ): bool {
	if ( $household_id < 1 || ! is_user_logged_in() ) {
		return false;
	}
	if ( emerson_ca_is_directory_editor() ) {
		return true;
	}
	global $wpdb;
	$editor_household = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE user_id = %d',
			get_current_user_id()
		)
	);
	return $editor_household > 0 && $editor_household === $household_id;
}

/**
 * Clear household or person attachment_id (CA has upload but no remove UI).
 */
function emerson_ca_ajax_clear_photo(): void {
	if ( ! check_ajax_referer( 'emerson_ca_clear_photo', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
	}
	$which = isset( $_POST['which'] ) ? sanitize_key( (string) wp_unslash( $_POST['which'] ) ) : '';
	$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	if ( $id < 1 || ! in_array( $which, array( 'people', 'household' ), true ) ) {
		wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
	}

	global $wpdb;
	if ( 'household' === $which ) {
		if ( ! emerson_ca_can_edit_household_photos( $id ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $wpdb->prefix . 'church_admin_household SET attachment_id = NULL WHERE household_id = %d',
				$id
			)
		);
		wp_send_json_success( array( 'which' => 'household', 'id' => $id ) );
	}

	$person = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_id, household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$id
		)
	);
	if ( ! $person || ! emerson_ca_can_edit_household_photos( (int) $person->household_id ) ) {
		wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
	}
	$wpdb->query(
		$wpdb->prepare(
			'UPDATE ' . $wpdb->prefix . 'church_admin_people SET attachment_id = NULL WHERE people_id = %d',
			$id
		)
	);
	wp_send_json_success( array( 'which' => 'people', 'id' => $id ) );
}
add_action( 'wp_ajax_emerson_ca_clear_photo', 'emerson_ca_ajax_clear_photo' );

/**
 * One-time sync: anyone who already has a portrait but photo_permission=0 (e.g. alice fox).
 */
function emerson_ca_sync_directory_photo_permissions(): void {
	if ( get_option( 'emerson_ca_synced_directory_photo_perm' ) === '1' ) {
		return;
	}
	global $wpdb;
	$wpdb->query(
		'UPDATE ' . $wpdb->prefix . 'church_admin_people
		SET photo_permission = 1
		WHERE attachment_id IS NOT NULL AND attachment_id != 0 AND attachment_id != ""
		AND (photo_permission IS NULL OR photo_permission = 0 OR photo_permission = "")'
	);
	update_option( 'emerson_ca_synced_directory_photo_perm', '1', false );
}
add_action( 'init', 'emerson_ca_sync_directory_photo_permissions', 20 );

/**
 * CA’s Edit person save sets attachment_id from POST people_attachment_id, or NULL when
 * that field is empty — so a Save after editing other fields (or after an AJAX photo upload
 * whose hidden input did not stick) wipes the portrait and the household table falls back
 * to man.svg / woman.svg. If the form omitted the id, keep the photo already on the person.
 */
function emerson_ca_preserve_person_photo_on_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- CA verifies edit-person / save-person.
	if ( empty( $_POST['ca-cmd'] ) || 'edit-person' !== $_POST['ca-cmd'] ) {
		return;
	}
	if ( empty( $_POST['save-person'] ) ) {
		return;
	}
	// Emerson "Remove photo" intentionally cleared the field — do not reinstate.
	if ( ! empty( $_POST['emerson_ca_clear_people_photo'] ) ) {
		return;
	}
	if ( ! empty( $_POST['people_attachment_id'] ) ) {
		return;
	}
	$people_id = ! empty( $_POST['people_id'] ) ? (int) $_POST['people_id'] : 0;
	if ( $people_id < 1 && ! empty( $_POST['id'] ) ) {
		$people_id = (int) $_POST['id'];
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( $people_id < 1 ) {
		return;
	}

	global $wpdb;
	$existing = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT attachment_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( $existing < 1 ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- feeding CA the id it already stored.
	$_POST['people_attachment_id'] = (string) $existing;
}

/**
 * Keep an already-granted photo_permission when Save omits the checkbox (privacy
 * block missing / stripped). Does not override an intentional uncheck after upload
 * has already set permission — upload AJAX is what turns it on for new portraits.
 */
function emerson_ca_ensure_photo_permission_on_save(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- CA verifies edit-person / save-person.
	if ( empty( $_POST['ca-cmd'] ) || 'edit-person' !== $_POST['ca-cmd'] ) {
		return;
	}
	if ( empty( $_POST['save-person'] ) ) {
		return;
	}
	if ( ! empty( $_POST['photo_permission'] ) ) {
		return;
	}
	$people_id = ! empty( $_POST['people_id'] ) ? (int) $_POST['people_id'] : 0;
	if ( $people_id < 1 && ! empty( $_POST['id'] ) ) {
		$people_id = (int) $_POST['id'];
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( $people_id < 1 ) {
		return;
	}

	global $wpdb;
	$had = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT photo_permission FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( $had < 1 ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- keep prior directory consent.
	$_POST['photo_permission'] = 'TRUE';
}

/**
 * During CA register/profile render, show date pickers as n/j/Y (e.g. 2/14/1980)
 * so typed dates match the calendar and birthday behavior. Site-wide date_format
 * stays untouched outside this request-scoped flag.
 *
 * @param mixed $format Stored WP date_format.
 * @return mixed
 */
function emerson_ca_register_mdy_date_format( $format ) {
	if ( ! empty( $GLOBALS['emerson_ca_mdy_dates'] ) ) {
		return 'n/j/Y';
	}
	return $format;
}

add_filter( 'option_date_format', 'emerson_ca_register_mdy_date_format' );

/**
 * Start request-scoped household spoofing for Directory editors.
 */
function emerson_ca_begin_register_spoof(): void {
	// Match birthday: easy US numeric dates on every CA register date picker this request.
	$GLOBALS['emerson_ca_mdy_dates'] = true;

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- CA verifies its own nonce later.
	if ( ! empty( $_POST['ca-cmd'] ) && 'delete-household' === $_POST['ca-cmd'] ) {
		emerson_ca_ensure_address_list_stub();
	}

	// Before CA reads POST on edit-person / edit-address / register / add-person.
	emerson_ca_normalize_typed_date_posts();

	emerson_ca_preserve_person_photo_on_save();
	emerson_ca_ensure_photo_permission_on_save();
	emerson_ca_snapshot_person_directory_visibility();
	// Before the household spoof filter is attached and before CA renders the post-save household card.
	emerson_ca_persist_household_contact();

	if ( ! empty( $GLOBALS['emerson_ca_spoof_household_id'] ) ) {
		return;
	}
	$target = emerson_ca_register_target_household_id();
	if ( $target < 1 ) {
		return;
	}
	global $wpdb;
	$exists = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_household WHERE household_id = %d',
			$target
		)
	);
	if ( $exists !== $target ) {
		return;
	}
	$GLOBALS['emerson_ca_spoof_household_id'] = $target;
	add_filter( 'query', 'emerson_ca_spoof_household_query' );
}

/**
 * End spoofing, persist type/marital/privacy, and repair select markup.
 */
function emerson_ca_finish_register_output( string $output ): string {
	$spoof_household = (int) ( $GLOBALS['emerson_ca_spoof_household_id'] ?? 0 );
	remove_filter( 'query', 'emerson_ca_spoof_household_query' );
	unset( $GLOBALS['emerson_ca_spoof_household_id'] );
	emerson_ca_persist_person_type_and_marital();
	emerson_ca_persist_wedding_anniversary();
	emerson_ca_maybe_clear_anniversary_after_delete( $spoof_household );
	emerson_ca_restore_person_directory_visibility();
	$output = emerson_ca_fix_person_type_and_marital_selects( $output );
	$output = emerson_ca_add_household_contact_fields( $output );
	// Keep mdy flag on so an injected anniversary picker matches DOB format.
	$output = emerson_ca_ensure_wedding_anniversary_ui( $output );
	$output = emerson_ca_mark_privacy_ui_in_form( $output );
	$output = emerson_ca_rewrite_existing_email_login( $output );
	$output = emerson_ca_label_photo_boxes( $output );
	$output = emerson_ca_enhance_overview_person_photos( $output );
	$output = emerson_ca_add_members_back_button( $output );
	$output = emerson_ca_relabel_edit_address_button( $output );
	$output = emerson_ca_restructure_household_overview( $output );
	if ( str_contains( $output, 'ca-upload-area' ) || str_contains( $output, 'emerson-ca-person-photo' ) ) {
		emerson_ca_enqueue_register_upload_script();
	}
	if ( str_contains( $output, 'clonableDatePicker' ) ) {
		emerson_ca_enqueue_register_script();
		// Force jQuery UI format even if a picker was rendered before the mdy flag.
		$output = (string) preg_replace(
			'/var caJSDateFormat="[^"]*"/',
			'var caJSDateFormat="m/d/yy"',
			$output
		);
	}
	unset( $GLOBALS['emerson_ca_mdy_dates'] );
	return emerson_ca_fix_delete_household_message( $output );
}

/**
 * Household overview: Back to Members before “Edit information” (CA has no return link).
 */
function emerson_ca_add_members_back_button( string $html ): string {
	if ( ! str_contains( $html, 'name="ca-cmd" value="edit-address"' ) ) {
		return $html;
	}
	if ( str_contains( $html, 'emerson-ca-register-back' ) ) {
		return $html;
	}

	$members = defined( 'EMERSON_MEMBERS_PAGE' )
		? home_url( EMERSON_MEMBERS_PAGE )
		: home_url( '/members/' );
	$back    = sprintf(
		'<a class="emerson-ca-register-back church-admin-register-button" href="%1$s#member-directory">%2$s</a>',
		esc_url( $members ),
		esc_html__( 'Back', 'emerson-chapel' )
	);

	$replaced = preg_replace(
		'#<p>(\s*<form\b[^>]*>\s*<input\b[^>]*name="ca-cmd"[^>]*value="edit-address"[^>]*>)#i',
		'<p class="emerson-ca-register-actions">' . $back . '$1',
		$html,
		1
	);

	return is_string( $replaced ) ? $replaced : $html;
}

/**
 * Household overview green button: CA says “Edit address”; chapel wording is “Edit information”.
 * Does not change the edit-address form itself (still saves address/phone).
 */
function emerson_ca_relabel_edit_address_button( string $html ): string {
	if ( ! str_contains( $html, 'value="edit-address"' ) || ! str_contains( $html, 'Edit address' ) ) {
		return $html;
	}

	$label = esc_attr__( 'Edit information', 'emerson-chapel' );
	$with  = preg_replace(
		'#(<form\b[^>]*>\s*<input\b[^>]*name="ca-cmd"[^>]*value="edit-address"[^>]*>.*?<input\b[^>]*type="submit"[^>]*\bvalue=")Edit address(")#is',
		'$1' . $label . '$2',
		$html,
		1
	);

	return is_string( $with ) ? $with : $html;
}

/**
 * Household overview layout helpers:
 * - People rows in head / people_order / first-added order (earliest person first).
 * - Move “Add a person” up with Back / Edit information (matches the desktop card mock).
 *
 * @param string $html Register output.
 * @return string
 */
function emerson_ca_restructure_household_overview( string $html ): string {
	if ( ! str_contains( $html, 'class="table table-bordered ca-table"' )
		&& ! str_contains( $html, "class='table table-bordered ca-table'" )
		&& ! str_contains( $html, 'ca-table' )
	) {
		return $html;
	}
	if ( ! str_contains( $html, 'value="edit-person"' ) ) {
		return $html;
	}

	if ( ! preg_match( '#<table\b[^>]*\bca-table\b[^>]*>(.*?)</table>#is', $html, $table_m ) ) {
		return $html;
	}

	$inner = $table_m[1];
	if ( ! preg_match_all( '#<tr\b[^>]*>.*?</tr>#is', $inner, $row_m ) ) {
		return $html;
	}

	$person_rows = array();
	$add_row     = null;
	$other_rows  = array();

	foreach ( $row_m[0] as $row ) {
		if ( str_contains( $row, 'value="add-person"' ) ) {
			$add_row = $row;
			continue;
		}
		if ( str_contains( $row, 'value="edit-person"' )
			&& preg_match( '#name="id"\s+value="(\d+)"#', $row, $id_m )
		) {
			$person_rows[] = array(
				'id'  => (int) $id_m[1],
				'html' => $row,
			);
			continue;
		}
		$other_rows[] = $row;
	}

	if ( $person_rows ) {
		$ids = array_values( array_unique( array_column( $person_rows, 'id' ) ) );
		$order_map = emerson_ca_household_people_display_order( $ids );
		usort(
			$person_rows,
			static function ( array $a, array $b ) use ( $order_map ): int {
				$oa = $order_map[ $a['id'] ] ?? PHP_INT_MAX;
				$ob = $order_map[ $b['id'] ] ?? PHP_INT_MAX;
				if ( $oa === $ob ) {
					return $a['id'] <=> $b['id'];
				}
				return $oa <=> $ob;
			}
		);
	}

	$new_inner = '';
	foreach ( $person_rows as $person ) {
		$new_inner .= $person['html'];
	}
	foreach ( $other_rows as $row ) {
		$new_inner .= $row;
	}

	$html = str_replace( $table_m[0], '<table class="table table-bordered ca-table">' . $new_inner . '</table>', $html );

	if ( null !== $add_row && preg_match( '#<form\b[^>]*>.*?</form>#is', $add_row, $form_m ) ) {
		$add_form = '<div class="emerson-ca-register-add">' . $form_m[0] . '</div>';
		// After Edit information (Back → Edit information → Add a person).
		$with = preg_replace(
			'#(<form\b[^>]*>\s*<input\b[^>]*name="ca-cmd"[^>]*value="edit-address"[^>]*>.*?</form>)#is',
			'$1' . $add_form,
			$html,
			1
		);
		if ( ! is_string( $with ) || $with === $html ) {
			$with = str_replace(
				'<table class="table table-bordered ca-table">',
				$add_form . '<table class="table table-bordered ca-table">',
				$html
			);
		}
		if ( is_string( $with ) ) {
			$html = $with;
		}
	}

	return $html;
}

/**
 * Stable display order for household people: head first, then people_order, then earliest id.
 *
 * @param int[] $people_ids People ids present in the overview table.
 * @return array<int, int> people_id => sort rank (0 = first).
 */
function emerson_ca_household_people_display_order( array $people_ids ): array {
	$people_ids = array_values( array_filter( array_map( 'intval', $people_ids ) ) );
	if ( ! $people_ids ) {
		return array();
	}

	global $wpdb;
	$placeholders = implode( ',', array_fill( 0, count( $people_ids ), '%d' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders built above.
	$sql = $wpdb->prepare(
		"SELECT people_id FROM {$wpdb->prefix}church_admin_people
		WHERE people_id IN ($placeholders)
		ORDER BY head_of_household DESC, people_order ASC, people_id ASC",
		...$people_ids
	);
	$ordered = $wpdb->get_col( $sql );
	if ( ! is_array( $ordered ) ) {
		return array();
	}

	$map = array();
	foreach ( array_values( $ordered ) as $rank => $id ) {
		$map[ (int) $id ] = (int) $rank;
	}
	return $map;
}

/**
 * Clarify CA’s identical “Photo” headings on Edit person vs Edit address.
 */
function emerson_ca_label_photo_boxes( string $html ): string {
	$html = (string) preg_replace_callback(
		'#(<div\b[^>]*\bca-upload-area\b[^>]*\bdata-which="people"[^>]*>\s*<h3>)Photo(</h3>)#i',
		static function ( array $m ): string {
			return $m[1] . 'Person photo' . $m[2];
		},
		$html
	);
	$html = (string) preg_replace_callback(
		'#(<div\b[^>]*\bca-upload-area\b[^>]*\bdata-which="household"[^>]*>\s*<h3>)Photo(</h3>)#i',
		static function ( array $m ): string {
			return $m[1] . 'Household photo (optional)' . $m[2];
		},
		$html
	);
	// data-which may appear after <h3> in attribute order variants.
	$html = (string) preg_replace_callback(
		'#(<div\b[^>]*\bdata-which="people"[^>]*\bca-upload-area\b[^>]*>\s*<h3>)Photo(</h3>)#i',
		static function ( array $m ): string {
			return $m[1] . 'Person photo' . $m[2];
		},
		$html
	);
	$html = (string) preg_replace_callback(
		'#(<div\b[^>]*\bdata-which="household"[^>]*\bca-upload-area\b[^>]*>\s*<h3>)Photo(</h3>)#i',
		static function ( array $m ): string {
			return $m[1] . 'Household photo (optional)' . $m[2];
		},
		$html
	);
	return $html;
}

/**
 * Household overview: make the person Image cell a drop/click upload target.
 * CA only prints a plain &lt;img&gt; there (silhouette or portrait), so a drop
 * otherwise just opens the file in the browser.
 */
function emerson_ca_enhance_overview_person_photos( string $html ): string {
	if ( ! str_contains( $html, 'data-colname="Image"' ) || ! str_contains( $html, 'edit-person' ) ) {
		return $html;
	}

	return (string) preg_replace_callback(
		'#<tr>(.*?)</tr>#is',
		static function ( array $m ): string {
			$row = $m[0];
			if ( ! str_contains( $row, 'value="edit-person"' ) ) {
				return $row;
			}
			if ( ! preg_match( '#name="id"\s+value="(\d+)"#', $row, $id_m ) ) {
				return $row;
			}
			$people_id = (int) $id_m[1];
			if ( $people_id < 1 ) {
				return $row;
			}
			$nonce = wp_create_nonce( 'people-image-upload' );

			return (string) preg_replace_callback(
				'#<td([^>]*data-colname="Image"[^>]*)>(.*?)</td>#is',
				static function ( array $td ) use ( $people_id, $nonce ): string {
					$inner = $td[2];
					// CA sometimes emits a stray quote after Image (…Image"  ">). Rebuild attrs.
					$attrs = ' data-colname="Image" class="emerson-ca-person-photo ca-upload-area" data-which="people" data-id="'
						. $people_id . '" data-nonce="' . esc_attr( $nonce ) . '"';
					if ( ! str_contains( $inner, 'drag-message' ) ) {
						$inner .= '<p class="drag-message">Drag a photo here, or click to choose</p>';
					}
					return '<td' . $attrs . '>' . $inner . '</td>';
				},
				$row,
				1
			);
		},
		$html
	);
}

/**
 * CA’s “email already on file” response embeds a second WordPress login form.
 */
function emerson_ca_is_existing_email_login_html( string $html ): bool {
	if ( ! str_contains( $html, 'loginform' ) ) {
		return false;
	}
	return (bool) preg_match(
		'/Please login|Looks like you are on our system|Looks like you have a user account for this site/i',
		$html
	);
}

/**
 * Remove CA’s embedded login form and its “Please login” / “Looks like…” copy.
 */
function emerson_ca_strip_embedded_login_form( string $html ): string {
	$html = (string) preg_replace(
		'#<p>\s*(?:Please login|Looks like you are on our system,? please login|Looks like you have a user account for this site, but no directory entry)\s*</p>#i',
		'',
		$html
	);
	$html = (string) preg_replace(
		'#<form\b[^>]*\b(?:id|name)=["\']loginform["\'][^>]*>.*?</form>#is',
		'',
		$html
	);
	$html = (string) preg_replace(
		'#<p>\s*<a\b[^>]*>\s*Lost Password\s*</a>\s*</p>#i',
		'',
		$html
	);
	return $html;
}

/**
 * Prefill hint from the email the visitor just checked (POST), if present.
 */
function emerson_ca_register_checked_email(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only hint for the Members login field.
	$raw = isset( $_POST['ca-email'] ) ? (string) wp_unslash( $_POST['ca-email'] ) : '';
	$email = sanitize_email( $raw );
	return is_email( $email ) ? $email : '';
}

/**
 * Dialog + quiet note when register finds an email already on file.
 */
function emerson_ca_rewrite_existing_email_login( string $html ): string {
	if ( ! emerson_ca_is_existing_email_login_html( $html ) ) {
		return $html;
	}

	$html  = emerson_ca_strip_embedded_login_form( $html );
	$email = emerson_ca_register_checked_email();
	$on_members = defined( 'EMERSON_MEMBERS_PAGE' ) && is_page( 'members' );

	if ( $on_members ) {
		$title   = __( 'Already registered', 'emerson-chapel' );
		$message = __( 'Your email is already registered. Please log in above.', 'emerson-chapel' );
		$action  = __( 'Go to login', 'emerson-chapel' );
		$note    = __( 'You already have an account. Use Member login above.', 'emerson-chapel' );
	} else {
		$members = defined( 'EMERSON_MEMBERS_PAGE' ) ? home_url( EMERSON_MEMBERS_PAGE ) : home_url( '/members/' );
		$title   = __( 'Already registered', 'emerson-chapel' );
		$message = sprintf(
			/* translators: %s: Members page URL */
			__( 'Your email is already registered. Please <a href="%s">log in on the Members page</a>.', 'emerson-chapel' ),
			esc_url( $members )
		);
		$action = __( 'Go to Members login', 'emerson-chapel' );
		$note   = sprintf(
			/* translators: %s: Members page URL */
			__( 'You already have an account. <a href="%s">Log in on the Members page</a>.', 'emerson-chapel' ),
			esc_url( $members )
		);
	}

	$dialog  = '<dialog class="emerson-nl-dialog emerson-ca-already-dialog" id="emerson-ca-already-registered"';
	$dialog .= $email !== '' ? ' data-emerson-login-email="' . esc_attr( $email ) . '"' : '';
	$dialog .= $on_members ? ' data-emerson-scroll-login="1"' : '';
	$dialog .= '>';
	$dialog .= '<div class="emerson-nl-dialog__inner">';
	$dialog .= '<button type="button" class="emerson-nl-dialog__close" aria-label="' . esc_attr__( 'Close', 'emerson-chapel' ) . '">&times;</button>';
	$dialog .= '<h2 class="emerson-nl-dialog__title">' . esc_html( $title ) . '</h2>';
	$dialog .= '<p class="emerson-nl-dialog__intro">' . wp_kses( $message, array( 'a' => array( 'href' => true ) ) ) . '</p>';
	$dialog .= '<p class="emerson-ca-already-dialog__actions">';
	if ( $on_members ) {
		$dialog .= '<button type="button" class="emerson-ca-already-dialog__ok">' . esc_html( $action ) . '</button>';
	} else {
		$dialog .= '<a class="emerson-ca-already-dialog__ok" href="' . esc_url( $members ) . '">' . esc_html( $action ) . '</a>';
	}
	$dialog .= '</p></div></dialog>';
	$dialog .= '<p class="emerson-ca-already-note">' . wp_kses( $note, array( 'a' => array( 'href' => true ) ) ) . '</p>';

	emerson_ca_enqueue_register_script();

	return $html . $dialog;
}

/**
 * Click + drag/drop for the register photo box (CA's script often missing on the block).
 */
function emerson_ca_enqueue_register_upload_script(): void {
	$path = emerson_chapel_path( 'assets/js/church-admin-register-upload.js' );
	if ( ! is_readable( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'emerson-church-admin-register-upload',
		emerson_chapel_url( 'assets/js/church-admin-register-upload.js' ),
		array( 'jquery' ),
		(string) filemtime( $path ),
		true
	);

	wp_localize_script(
		'emerson-church-admin-register-upload',
		'emersonCaRegisterUpload',
		array(
			'defaultAvatar' => plugins_url(
				'/images/default-avatar.jpg',
				WP_PLUGIN_DIR . '/church-admin-premium/includes'
			),
			'allowDirectoryPhoto' => array(
				'action' => 'emerson_ca_allow_directory_photo',
				'nonce'  => wp_create_nonce( 'emerson_ca_directory_photo' ),
			),
			'clearPhoto' => array(
				'action' => 'emerson_ca_clear_photo',
				'nonce'  => wp_create_nonce( 'emerson_ca_clear_photo' ),
			),
		)
	);
}

/**
 * CA only outputs the wedding anniversary date field when this option is truthy.
 * Force it on for front-end register so Married can show a date picker.
 *
 * @param mixed $pre Short-circuit value.
 * @return mixed
 */
function emerson_ca_force_show_wedding_anniversary_option( $pre ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return $pre;
	}
	return '1';
}

add_filter( 'pre_option_church_admin_show_wedding_anniversary', 'emerson_ca_force_show_wedding_anniversary_option' );

/**
 * Marital status option id for "Married" (CA hard-codes "3" in its script).
 */
function emerson_ca_married_status_id(): string {
	$statuses = (array) get_option( 'church_admin_marital_status', array() );
	foreach ( $statuses as $id => $label ) {
		if ( is_string( $label ) && strcasecmp( $label, 'Married' ) === 0 ) {
			return (string) $id;
		}
	}
	return '3';
}

/**
 * True when at least one person in the household is Married.
 */
function emerson_ca_household_has_married_person( int $household_id ): bool {
	if ( $household_id < 1 ) {
		return false;
	}
	global $wpdb;
	$found = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT people_id FROM ' . $wpdb->prefix . 'church_admin_people
			WHERE household_id = %d AND marital_status = %d LIMIT 1',
			$household_id,
			(int) emerson_ca_married_status_id()
		)
	);
	return $found > 0;
}

/**
 * Clear household.wedding_anniversary (NULL).
 */
function emerson_ca_clear_household_wedding_anniversary( int $household_id ): void {
	if ( $household_id < 1 ) {
		return;
	}
	global $wpdb;
	$wpdb->update(
		$wpdb->prefix . 'church_admin_household',
		array( 'wedding_anniversary' => null ),
		array( 'household_id' => $household_id ),
		array( '%s' ),
		array( '%d' )
	);
}

/**
 * After delete-person: drop leftover anniversary if nobody left is Married.
 *
 * @param int $hint_household_id Spoof/target household from before CA deleted the person.
 */
function emerson_ca_maybe_clear_anniversary_after_delete( int $hint_household_id = 0 ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['ca-cmd'] ) || 'delete-person' !== $_POST['ca-cmd'] ) {
		return;
	}
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'delete-person' ) ) {
		return;
	}

	$household_id = $hint_household_id;
	if ( $household_id < 1 ) {
		global $wpdb;
		$household_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE user_id = %d',
				get_current_user_id()
			)
		);
	}
	if ( $household_id < 1 ) {
		return;
	}
	if ( ! emerson_ca_household_has_married_person( $household_id ) ) {
		emerson_ca_clear_household_wedding_anniversary( $household_id );
	}
}

/**
 * One-shot: clear household anniversaries with no Married person left (stale after divorce / partner leave).
 */
function emerson_ca_scrub_stale_wedding_anniversaries_once(): void {
	if ( get_option( 'emerson_ca_wa_scrub_v2' ) ) {
		return;
	}
	global $wpdb;
	$married = (int) emerson_ca_married_status_id();
	$people  = $wpdb->prefix . 'church_admin_people';
	$house   = $wpdb->prefix . 'church_admin_household';
	// DATE column: IS NOT NULL only (comparisons to '' / 0000-00-00 abort under strict SQL).
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb->prefix.
	$result = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$house} h
			SET h.wedding_anniversary = NULL
			WHERE h.wedding_anniversary IS NOT NULL
			AND NOT EXISTS (
				SELECT 1 FROM {$people} p
				WHERE p.household_id = h.household_id AND p.marital_status = %d
			)",
			$married
		)
	);
	if ( false !== $result ) {
		update_option( 'emerson_ca_wa_scrub_v2', 1, false );
		delete_option( 'emerson_ca_wa_scrub_v1' );
	}
}

add_action( 'init', 'emerson_ca_scrub_stale_wedding_anniversaries_once', 25 );

/**
 * Enqueue register JS (footer). Safe to call during shortcode render after
 * wp_enqueue_scripts — WP still prints footer scripts queued late.
 */
function emerson_ca_enqueue_register_script(): void {
	$path = emerson_chapel_path( 'assets/js/church-admin-register.js' );
	if ( ! is_readable( $path ) ) {
		return;
	}
	wp_enqueue_script(
		'emerson-church-admin-register',
		emerson_chapel_url( 'assets/js/church-admin-register.js' ),
		array( 'jquery' ),
		(string) filemtime( $path ),
		true
	);
	wp_localize_script(
		'emerson-church-admin-register',
		'emersonCaRegister',
		array(
			'marriedStatusId' => emerson_ca_married_status_id(),
		)
	);
}

/**
 * Ensure #wedding-anniversary exists next to marital status, and toggle it for Married
 * on change and on load (CA only shows on change).
 *
 * Script lives in assets/js/church-admin-register.js — inline JS through the_content
 * gets && turned into &#038;&#038;, which breaks the whole handler.
 */
function emerson_ca_ensure_wedding_anniversary_ui( string $html ): string {
	if ( ! str_contains( $html, 'name="marital_status"' ) ) {
		return $html;
	}

	emerson_ca_enqueue_register_script();

	if ( ! str_contains( $html, 'id="wedding-anniversary"' ) && function_exists( 'church_admin_premium_date_picker' ) ) {
		$people_id = 0;
		if ( preg_match( '/name="people_id"[^>]*\svalue="(\d+)"/', $html, $match )
			|| preg_match( '/name="people_id"\s+value="(\d+)"/', $html, $match )
		) {
			$people_id = (int) $match[1];
		}
		$wa = null;
		if ( $people_id > 0 ) {
			global $wpdb;
			$wa = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT b.wedding_anniversary FROM ' . $wpdb->prefix . 'church_admin_people a
					INNER JOIN ' . $wpdb->prefix . 'church_admin_household b ON a.household_id = b.household_id
					WHERE a.people_id = %d',
					$people_id
				)
			);
			if ( ! is_string( $wa ) || $wa === '' || $wa === '0000-00-00' ) {
				$wa = null;
			}
		}
		$field  = '<div class="church-admin-form-group" id="wedding-anniversary" style="display:none">';
		// for= visible input (…x); hidden Y-m-d must not steal focus when the icon is clicked.
		$field .= '<label for="wedding_anniversaryx">' . esc_html__( 'Wedding Anniversary', 'church-admin' ) . '</label>';
		$field .= church_admin_premium_date_picker( $wa, 'wedding_anniversary', false, '1910', null, 'wedding_anniversary', 'wedding_anniversary', false, null, null, null );
		$field .= '</div>';
		$with   = preg_replace(
			'/(<select\b[^>]*\bname="marital_status"[^>]*>.*?<\/select>\s*<\/div>)/is',
			'$1' . $field,
			$html,
			1
		);
		if ( is_string( $with ) ) {
			$html = $with;
		}
	}

	return $html;
}

/**
 * Parse a typed or picker date into Y-m-d. Accepts Y-m-d, m/d/Y, m/d/y (US), and
 * common dash/dot variants. Two-digit years: 00–69 → 2000–2069, 70–99 → 1970–1999.
 * Also accepts site-style strings (e.g. "January 15, 1980") via strtotime.
 */
function emerson_ca_parse_flexible_date( string $raw ): ?string {
	$raw = trim( $raw );
	if ( $raw === '' ) {
		return null;
	}

	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m ) ) {
		$y  = (int) $m[1];
		$mo = (int) $m[2];
		$d  = (int) $m[3];
		return checkdate( $mo, $d, $y ) ? sprintf( '%04d-%02d-%02d', $y, $mo, $d ) : null;
	}

	// 2/14/21, 02/14/2021, 2-14-2021, 2.14.21
	if ( preg_match( '/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2}|\d{4})$/', $raw, $m ) ) {
		$mo = (int) $m[1];
		$d  = (int) $m[2];
		$y  = (int) $m[3];
		if ( $y < 100 ) {
			$y += ( $y <= 69 ) ? 2000 : 1900;
		}
		return checkdate( $mo, $d, $y ) ? sprintf( '%04d-%02d-%02d', $y, $mo, $d ) : null;
	}

	$ts = strtotime( $raw );
	if ( false === $ts ) {
		return null;
	}
	// Calendar date only — avoid site timezone shifting the day.
	return gmdate( 'Y-m-d', $ts );
}

/**
 * Church Admin date pickers: visible field is {name}x; save only reads hidden {name} (Y-m-d),
 * which the calendar popup fills. Rewrite POST from the typed value before CA saves so
 * birthday / anniversary / custom dates survive without using the popup — on edit-person,
 * edit-address, basic-register, and add-person alike.
 */
function emerson_ca_normalize_typed_date_posts(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- CA verifies its own nonce.
	if ( empty( $_POST ) ) {
		return;
	}

	$bases = array();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST['date_of_birthx'] ) ) {
		$bases[] = 'date_of_birth';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( isset( $_POST['wedding_anniversaryx'] ) ) {
		$bases[] = 'wedding_anniversary';
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	foreach ( array_keys( $_POST ) as $key ) {
		if ( is_string( $key ) && preg_match( '/^(custom-\d+)x$/', $key, $m ) ) {
			$bases[] = $m[1];
		}
	}
	$bases = array_values( array_unique( $bases ) );
	if ( ! $bases ) {
		return;
	}

	foreach ( $bases as $base ) {
		$typed_key = $base . 'x';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $typed_key ] ) ) {
			continue;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$typed = sanitize_text_field( wp_unslash( (string) $_POST[ $typed_key ] ) );
		if ( trim( $typed ) === '' ) {
			// Member cleared the visible field — clear the hidden Y-m-d too.
			$_POST[ $base ] = '';
			continue;
		}
		$ymd = emerson_ca_parse_flexible_date( $typed );
		if ( null !== $ymd ) {
			$_POST[ $base ] = $ymd;
		}
		// Unparseable typed text: leave the hidden field alone (calendar value or empty).
	}
}

/**
 * Person save never writes household.wedding_anniversary; persist it after edit-person.
 * CA's picker only fills the hidden Y-m-d field when a calendar day is clicked — typed
 * values land in wedding_anniversaryx, so we parse that too.
 * Non-Married status clears the household date so divorce / partner-leave does not leave
 * a stale anniversary (Edit information never shows it, but the DB would keep it).
 */
function emerson_ca_persist_wedding_anniversary(): void {
	if ( empty( $_POST['ca-cmd'] ) || 'edit-person' !== $_POST['ca-cmd'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'edit-person' ) ) {
		return;
	}

	$people_id = ! empty( $_POST['people_id'] ) ? (int) $_POST['people_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $people_id < 1 && ! empty( $_POST['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$people_id = (int) $_POST['id'];
	}
	if ( $people_id < 1 ) {
		return;
	}

	global $wpdb;
	$person = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_id, household_id, marital_status FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( ! $person || (int) $person->household_id < 1 ) {
		return;
	}

	$editor_household = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE user_id = %d',
			get_current_user_id()
		)
	);
	$allowed = emerson_ca_is_directory_editor()
		|| ( $editor_household > 0 && (int) $person->household_id === $editor_household );
	if ( ! $allowed ) {
		return;
	}

	$married_id = (int) emerson_ca_married_status_id();
	// Prefer POST (just saved by persist_person_type_and_marital); fall back to DB row.
	$status = isset( $_POST['marital_status'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? (int) $_POST['marital_status']
		: (int) $person->marital_status;
	if ( $status !== $married_id ) {
		emerson_ca_clear_household_wedding_anniversary( (int) $person->household_id );
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['wedding_anniversary'] ) && ! isset( $_POST['wedding_anniversaryx'] ) ) {
		return;
	}

	// Prefer the visible typed field: the hidden Y-m-d only updates when the calendar
	// is used, so a stale hidden value would otherwise win over what the member typed.
	$typed  = isset( $_POST['wedding_anniversaryx'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? sanitize_text_field( wp_unslash( (string) $_POST['wedding_anniversaryx'] ) )
		: null;
	$hidden = isset( $_POST['wedding_anniversary'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		? sanitize_text_field( wp_unslash( (string) $_POST['wedding_anniversary'] ) )
		: null;

	$date = null;
	if ( null !== $typed && trim( $typed ) !== '' ) {
		$date = emerson_ca_parse_flexible_date( $typed );
		if ( null === $date ) {
			// Typed something we could not parse — leave existing DB value alone.
			return;
		}
	} elseif ( null !== $hidden ) {
		$date = emerson_ca_parse_flexible_date( $hidden );
	}

	$wpdb->update(
		$wpdb->prefix . 'church_admin_household',
		array( 'wedding_anniversary' => $date ),
		array( 'household_id' => (int) $person->household_id ),
		array( '%s' ),
		array( '%d' )
	);
}

/**
 * Address and phone live on the household row (church_admin_household.address / .phone).
 * Edit Address and the member directory card already read and write that row, but the Edit
 * person ("profile") form only had the person's own Cell number (church_admin_people.mobile),
 * so the profile never showed what Edit Address saved. Add the household's Address and Home
 * phone to the Edit person form, pre-filled from, and saved back to, the same household row.
 *
 * @param string $html Register output.
 * @return string
 */
function emerson_ca_add_household_contact_fields( string $html ): string {
	if ( ! str_contains( $html, 'name="save-person"' )
		|| ! str_contains( $html, 'value="edit-person"' )
		|| str_contains( $html, 'name="emerson_household_contact"' )
	) {
		return $html;
	}

	$people_id = 0;
	if ( preg_match( '/name="people_id"[^>]*\svalue="(\d+)"/', $html, $match )
		|| preg_match( '/name="people_id"\s+value="(\d+)"/', $html, $match )
		|| preg_match( '/value="(\d+)"[^>]*\sname="people_id"/', $html, $match )
	) {
		$people_id = (int) $match[1];
	}
	if ( $people_id < 1 ) {
		return $html;
	}

	global $wpdb;
	$household = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT h.address, h.phone FROM ' . $wpdb->prefix . 'church_admin_household h
			INNER JOIN ' . $wpdb->prefix . 'church_admin_people p ON p.household_id = h.household_id
			WHERE p.people_id = %d',
			$people_id
		)
	);
	if ( ! $household ) {
		return $html;
	}

	$fields  = '<input type="hidden" name="emerson_household_contact" value="1" />';
	$fields .= '<div class="church-admin-form-group emerson-household-contact"><label for="emerson-household-address">'
		. esc_html__( 'Address', 'church-admin' ) . '</label>'
		. '<input id="emerson-household-address" class="church-admin-form-control" type="text" name="emerson_household_address" value="'
		. esc_attr( (string) $household->address ) . '" /></div>';
	$fields .= '<div class="church-admin-form-group emerson-household-contact"><label for="emerson-household-phone">'
		. esc_html__( 'Home phone', 'church-admin' ) . '</label>'
		. '<input id="emerson-household-phone" class="church-admin-form-control" type="text" name="emerson_household_phone" value="'
		. esc_attr( (string) $household->phone ) . '" />'
		. '<p class="description">' . esc_html__( 'Shared with Edit address and the member directory.', 'emerson-chapel' ) . '</p></div>';

	$with = preg_replace_callback(
		'/<input\b[^>]*\bname="mobile"[^>]*>\s*<\/div>/i',
		static fn( array $m ): string => $m[0] . $fields,
		$html,
		1
	);
	return is_string( $with ) ? $with : $html;
}

/**
 * Save the household Address / Home phone posted from the Edit person form to the same
 * household row Edit Address writes. Runs before CA renders, so the post-save household
 * card and the directory show the new values straight away.
 */
function emerson_ca_persist_household_contact(): void {
	static $done = false;
	if ( $done ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below.
	if ( empty( $_POST['ca-cmd'] ) || 'edit-person' !== $_POST['ca-cmd'] || empty( $_POST['emerson_household_contact'] ) ) {
		return;
	}
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'edit-person' ) ) {
		return;
	}
	if ( empty( $_POST['save-person'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['save-person'] ) ), 'save-person' ) ) {
		return;
	}
	// CA skips the person save when a name is missing; skip ours too so the form re-renders untouched.
	if ( empty( $_POST['first_name'] ) || empty( $_POST['last_name'] ) ) {
		return;
	}

	$people_id = ! empty( $_POST['people_id'] ) ? (int) $_POST['people_id'] : 0;
	if ( $people_id < 1 && ! empty( $_POST['id'] ) ) {
		$people_id = (int) $_POST['id'];
	}
	if ( $people_id < 1 ) {
		return;
	}
	$address = isset( $_POST['emerson_household_address'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['emerson_household_address'] ) ) : null;
	$phone   = isset( $_POST['emerson_household_phone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['emerson_household_phone'] ) ) : null;
	// phpcs:enable WordPress.Security.NonceVerification.Missing
	if ( null === $address && null === $phone ) {
		return;
	}

	global $wpdb;
	$person = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_id, household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( ! $person || (int) $person->household_id < 1 ) {
		return;
	}

	$editor_household = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE user_id = %d',
			get_current_user_id()
		)
	);
	$allowed = emerson_ca_is_directory_editor()
		|| ( $editor_household > 0 && (int) $person->household_id === $editor_household );
	if ( ! $allowed ) {
		return;
	}
	$done = true;

	$table   = $wpdb->prefix . 'church_admin_household';
	$current = $wpdb->get_row(
		$wpdb->prepare( 'SELECT address, phone FROM ' . $table . ' WHERE household_id = %d', (int) $person->household_id )
	);
	if ( ! $current ) {
		return;
	}

	$update  = array();
	$formats = array();
	if ( null !== $address && trim( $address ) !== trim( (string) $current->address ) ) {
		$update['address'] = $address;
		$formats[]         = '%s';
		// The map pin belonged to the old address; clear it so it is not shown for the new one.
		$update['lat']      = null;
		$update['lng']      = null;
		$update['geocoded'] = 0;
		array_push( $formats, '%s', '%s', '%d' );
	}
	if ( null !== $phone && trim( $phone ) !== trim( (string) $current->phone ) ) {
		$update['phone'] = $phone;
		$formats[]       = '%s';
	}
	if ( array() === $update ) {
		return;
	}

	$wpdb->update( $table, $update, array( 'household_id' => (int) $person->household_id ), $formats, array( '%d' ) );
	delete_option( 'church_admin_app_address_cache' );
	delete_option( 'church_admin_app_admin_address_cache' );
}

/**
 * The church-admin/register block never declares full_privacy_show, so CA always
 * passes false and hides "show me" / fine privacy. Force it on before render.
 *
 * @param array<string, mixed> $parsed_block Block data.
 * @return array<string, mixed>
 */
function emerson_ca_force_register_privacy_ui( array $parsed_block ): array {
	$name = $parsed_block['blockName'] ?? '';
	if ( 'church-admin/register' !== $name && 'church-admin/basic-register' !== $name ) {
		return $parsed_block;
	}
	if ( ! isset( $parsed_block['attrs'] ) || ! is_array( $parsed_block['attrs'] ) ) {
		$parsed_block['attrs'] = array();
	}
	$parsed_block['attrs']['full_privacy_show'] = true;
	return $parsed_block;
}

add_filter( 'render_block_data', 'emerson_ca_force_register_privacy_ui' );

/**
 * Hidden marker so a later save can tell privacy checkboxes were on the form.
 * Unchecked boxes are absent from POST; without this marker we cannot tell
 * "intentionally hidden" from "UI never rendered".
 */
function emerson_ca_mark_privacy_ui_in_form( string $html ): string {
	if ( ! str_contains( $html, 'name="show_me"' ) ) {
		return $html;
	}
	if ( str_contains( $html, 'name="emerson_ca_privacy_ui"' ) ) {
		return $html;
	}
	$marker = '<input type="hidden" name="emerson_ca_privacy_ui" value="1" />';
	$with   = preg_replace(
		'/(<input\b[^>]*\bname="show_me"[^>]*>)/i',
		'$1' . $marker,
		$html,
		1
	);
	return is_string( $with ) ? $with : $html;
}

/**
 * Snapshot show_me / privacy before CA's edit-person save (which zeros them when
 * the privacy UI was missing from the submitted form).
 */
function emerson_ca_snapshot_person_directory_visibility(): void {
	if ( ! empty( $GLOBALS['emerson_ca_privacy_snapshot'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified later on restore.
	if ( empty( $_POST['ca-cmd'] ) || 'edit-person' !== $_POST['ca-cmd'] ) {
		return;
	}
	// Privacy UI was present → honour whatever CA saved (including intentional hide).
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['emerson_ca_privacy_ui'] ) ) {
		return;
	}

	$people_id = ! empty( $_POST['people_id'] ) ? (int) $_POST['people_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $people_id < 1 && ! empty( $_POST['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$people_id = (int) $_POST['id'];
	}
	if ( $people_id < 1 ) {
		return;
	}

	global $wpdb;
	$row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_id, show_me, privacy FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( ! $row ) {
		return;
	}

	$GLOBALS['emerson_ca_privacy_snapshot'] = array(
		'people_id' => (int) $row->people_id,
		'show_me'   => (int) $row->show_me,
		'privacy'   => $row->privacy,
	);
}

/**
 * After CA's wipe, put show_me / privacy back when the form had no privacy section.
 */
function emerson_ca_restore_person_directory_visibility(): void {
	$snap = $GLOBALS['emerson_ca_privacy_snapshot'] ?? null;
	unset( $GLOBALS['emerson_ca_privacy_snapshot'] );
	if ( ! is_array( $snap ) || empty( $snap['people_id'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! empty( $_POST['emerson_ca_privacy_ui'] ) ) {
		return;
	}
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'edit-person' ) ) {
		return;
	}

	$people_id = (int) $snap['people_id'];
	global $wpdb;
	$person = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_id, household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( ! $person ) {
		return;
	}

	$editor_household = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE user_id = %d',
			get_current_user_id()
		)
	);
	$allowed = emerson_ca_is_directory_editor()
		|| ( $editor_household > 0 && (int) $person->household_id === $editor_household );
	if ( ! $allowed ) {
		return;
	}

	$wpdb->update(
		$wpdb->prefix . 'church_admin_people',
		array(
			'show_me' => (int) $snap['show_me'],
			'privacy' => $snap['privacy'],
		),
		array( 'people_id' => $people_id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
}

/**
 * CA's delete_household() echoes success but returns nothing, so the front-end
 * register switch treats it as failure. Swap that string when the household is gone.
 */
function emerson_ca_fix_delete_household_message( string $output ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( empty( $_POST['ca-cmd'] ) || 'delete-household' !== $_POST['ca-cmd'] ) {
		return $output;
	}
	$failed = esc_html__( 'Household deletion failed, please get in touch', 'church-admin' );
	if ( ! str_contains( $output, $failed ) ) {
		return $output;
	}
	$id = ! empty( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $id < 1 ) {
		return $output;
	}
	global $wpdb;
	$still = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_household WHERE household_id = %d',
			$id
		)
	);
	if ( $still === $id ) {
		return $output;
	}
	$ok = esc_html__( 'Your household has been completely deleted', 'church-admin' );
	return str_replace( $failed, $ok, $output );
}

/**
 * Rewrite CA "find my household by user_id" queries so Directory editors can act on another household.
 */
function emerson_ca_spoof_household_query( string $query ): string {
	$target = $GLOBALS['emerson_ca_spoof_household_id'] ?? 0;
	if ( ! is_int( $target ) || $target < 1 ) {
		return $query;
	}

	global $wpdb;
	$people = $wpdb->prefix . 'church_admin_people';
	if ( ! str_contains( $query, $people ) || ! str_contains( $query, 'user_id' ) ) {
		return $query;
	}

	// SELECT household_id FROM …people WHERE user_id=N
	if ( preg_match(
		'/^\s*SELECT\s+household_id\s+FROM\s+' . preg_quote( $people, '/' ) . '\s+WHERE\s+user_id\s*=\s*\d+\s*$/i',
		$query
	) ) {
		return $wpdb->prepare(
			'SELECT household_id FROM ' . $people . ' WHERE household_id = %d LIMIT 1',
			$target
		);
	}

	// SELECT * FROM …people WHERE user_id=N
	if ( preg_match(
		'/^\s*SELECT\s+\*\s+FROM\s+' . preg_quote( $people, '/' ) . '\s+WHERE\s+user_id\s*=\s*\d+\s*$/i',
		$query
	) ) {
		return $wpdb->prepare(
			'SELECT * FROM ' . $people . ' WHERE household_id = %d ORDER BY head_of_household DESC, people_order ASC LIMIT 1',
			$target
		);
	}

	return $query;
}

// Shortcode path (rare; Members / older pages).
add_filter(
	'pre_do_shortcode_tag',
	static function ( $return, string $tag ) {
		if ( 'church_admin_register' === $tag ) {
			emerson_ca_begin_register_spoof();
		}
		return $return;
	},
	10,
	2
);

add_filter(
	'do_shortcode_tag',
	static function ( $output, string $tag ) {
		if ( 'church_admin_register' !== $tag || ! is_string( $output ) ) {
			return $output;
		}
		return emerson_ca_finish_register_output( $output );
	},
	10,
	2
);

// Block path used by /register/ (<!-- wp:church-admin/register /-->).
add_filter(
	'pre_render_block',
	static function ( $pre_render, array $parsed_block ) {
		if ( ( $parsed_block['blockName'] ?? '' ) === 'church-admin/register' ) {
			emerson_ca_begin_register_spoof();
		}
		return $pre_render;
	},
	10,
	2
);

add_filter(
	'render_block_church-admin/register',
	static function ( string $content ): string {
		return emerson_ca_finish_register_output( $content );
	}
);

/**
 * Whether /register/ is in household-edit mode (logged-in overview / Edit Entry).
 */
function emerson_ca_register_is_edit_profile_view(): bool {
	return is_user_logged_in() && is_page( 'register' );
}

// Page H1: WP page title is still “Register”; show Edit Profile while editing.
add_filter(
	'the_title',
	static function ( $title, $post_id = 0 ) {
		if ( ! emerson_ca_register_is_edit_profile_view() ) {
			return $title;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $title;
		}
		if ( (int) $post_id !== (int) get_queried_object_id() ) {
			return $title;
		}
		return __( 'Edit Profile', 'emerson-chapel' );
	},
	10,
	2
);

// Browser tab title for the same view.
add_filter(
	'document_title_parts',
	static function ( array $parts ): array {
		if ( emerson_ca_register_is_edit_profile_view() ) {
			$parts['title'] = __( 'Edit Profile', 'emerson-chapel' );
		}
		return $parts;
	}
);

/**
 * CA's front-end save omits marital_status; re-apply type + marital after a successful edit POST.
 */
function emerson_ca_persist_person_type_and_marital(): void {
	if ( empty( $_POST['ca-cmd'] ) || 'edit-person' !== $_POST['ca-cmd'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'edit-person' ) ) {
		return;
	}

	$people_id = ! empty( $_POST['people_id'] ) ? (int) $_POST['people_id'] : 0;
	if ( $people_id < 1 && ! empty( $_POST['id'] ) ) {
		$people_id = (int) $_POST['id'];
	}
	if ( $people_id < 1 ) {
		return;
	}

	global $wpdb;
	$person = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_id, household_id, user_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( ! $person ) {
		return;
	}

	$editor_household = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT household_id FROM ' . $wpdb->prefix . 'church_admin_people WHERE user_id = %d',
			get_current_user_id()
		)
	);
	$allowed = emerson_ca_is_directory_editor()
		|| ( $editor_household > 0 && (int) $person->household_id === $editor_household );
	if ( ! $allowed ) {
		return;
	}

	$update = array();
	if ( isset( $_POST['people_type_id'] ) ) {
		$update['people_type_id'] = (int) $_POST['people_type_id'];
	}
	if ( isset( $_POST['marital_status'] ) ) {
		$update['marital_status'] = (int) $_POST['marital_status'];
	}
	if ( $update === array() ) {
		return;
	}

	$wpdb->update(
		$wpdb->prefix . 'church_admin_people',
		$update,
		array( 'people_id' => $people_id ),
		array_fill( 0, count( $update ), '%d' ),
		array( '%d' )
	);
}

/**
 * Rebuild Person type / Marital status selects so the DB values are selected.
 * Stock CA emits broken option markup and compares marital ID to the label.
 */
function emerson_ca_fix_person_type_and_marital_selects( string $html ): string {
	if ( ! str_contains( $html, 'name="people_type_id"' ) && ! str_contains( $html, 'name="marital_status"' ) ) {
		return $html;
	}

	$people_id = 0;
	if ( preg_match( '/name="people_id"[^>]*\svalue="(\d+)"/', $html, $match )
		|| preg_match( '/name="people_id"\s+value="(\d+)"/', $html, $match )
		|| preg_match( '/value="(\d+)"[^>]*\sname="people_id"/', $html, $match )
	) {
		$people_id = (int) $match[1];
	}
	if ( $people_id < 1 ) {
		return $html;
	}

	global $wpdb;
	$person = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT people_type_id, marital_status FROM ' . $wpdb->prefix . 'church_admin_people WHERE people_id = %d',
			$people_id
		)
	);
	if ( ! $person ) {
		return $html;
	}

	$people_types = (array) get_option( 'church_admin_people_type', array() );
	$maritals     = (array) get_option( 'church_admin_marital_status', array() );
	$current_type = (int) $person->people_type_id;
	$current_ms   = (int) $person->marital_status;

	if ( $people_types !== array() && str_contains( $html, 'name="people_type_id"' ) ) {
		$options = '';
		foreach ( $people_types as $id => $label ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>' . "\r\n",
				esc_attr( (string) $id ),
				selected( $current_type, (int) $id, false ),
				esc_html( (string) $label )
			);
		}
		$html = (string) preg_replace(
			'/(<select\b[^>]*\bname="people_type_id"[^>]*>).*?(<\/select>)/is',
			'$1' . $options . '$2',
			$html,
			1
		);
	}

	if ( $maritals !== array() && str_contains( $html, 'name="marital_status"' ) ) {
		$options = '';
		foreach ( $maritals as $id => $label ) {
			$options .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>' . "\r\n",
				esc_attr( (string) $id ),
				selected( $current_ms, (int) $id, false ),
				esc_html( (string) $label )
			);
		}
		$html = (string) preg_replace(
			'/(<select\b[^>]*\bname="marital_status"[^>]*>).*?(<\/select>)/is',
			'$1' . $options . '$2',
			$html,
			1
		);
	}

	return $html;
}
