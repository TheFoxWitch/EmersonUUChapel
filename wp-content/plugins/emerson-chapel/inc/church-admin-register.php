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
 * - Person photo: CA's draganddrop.js often never loads for the register block, and the
 *   markup has no file input — drops open the image in a new tab. Emerson wires click
 *   and drag/drop to CA's people/household upload AJAX.
 * - Delete household: CA calls church_admin_premium_address_list() afterward; that function
 *   lives in directory.php (admin only). Stub it on the front end so delete does not fatal.
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
 * Start request-scoped household spoofing for Directory editors.
 */
function emerson_ca_begin_register_spoof(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- CA verifies its own nonce later.
	if ( ! empty( $_POST['ca-cmd'] ) && 'delete-household' === $_POST['ca-cmd'] ) {
		emerson_ca_ensure_address_list_stub();
	}

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
	remove_filter( 'query', 'emerson_ca_spoof_household_query' );
	unset( $GLOBALS['emerson_ca_spoof_household_id'] );
	emerson_ca_persist_person_type_and_marital();
	emerson_ca_persist_wedding_anniversary();
	emerson_ca_restore_person_directory_visibility();
	$output = emerson_ca_fix_person_type_and_marital_selects( $output );
	$output = emerson_ca_add_household_contact_fields( $output );
	$output = emerson_ca_ensure_wedding_anniversary_ui( $output );
	$output = emerson_ca_mark_privacy_ui_in_form( $output );
	if ( str_contains( $output, 'ca-upload-area' ) ) {
		emerson_ca_enqueue_register_upload_script();
	}
	return emerson_ca_fix_delete_household_message( $output );
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
		$field .= '<label for="wedding_anniversary">' . esc_html__( 'Wedding Anniversary', 'church-admin' ) . '</label>';
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
 */
function emerson_ca_parse_flexible_date( string $raw ): ?string {
	$raw = trim( $raw );
	if ( $raw === '' ) {
		return null;
	}

	if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m ) ) {
		$y = (int) $m[1];
		$mo = (int) $m[2];
		$d = (int) $m[3];
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
 * Person save never writes household.wedding_anniversary; persist it after edit-person.
 * CA's picker only fills the hidden Y-m-d field when a calendar day is clicked — typed
 * values land in wedding_anniversaryx, so we parse that too.
 */
function emerson_ca_persist_wedding_anniversary(): void {
	if ( empty( $_POST['ca-cmd'] ) || 'edit-person' !== $_POST['ca-cmd'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return;
	}
	if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ), 'edit-person' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['wedding_anniversary'] ) && ! isset( $_POST['wedding_anniversaryx'] ) ) {
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
