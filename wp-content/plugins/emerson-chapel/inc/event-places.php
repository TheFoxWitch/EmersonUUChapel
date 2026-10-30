<?php
/**
 * Event Places: one admin screen for Sunday Service and office-managed recurring series.
 *
 * Dropdown picks the series; the table edits upcoming dates. Emerson stores exceptions;
 * each save also writes location and time onto the matching Church Admin calendar row.
 * Add / Edit / Remove series creates or updates Church Admin calendar dates.
 * Old Sunday Location / Board Location menu items redirect here.
 */

declare( strict_types=1 );

/**
 * @return array<string, array{label:string,title:string,locked?:bool}>
 */
function emerson_event_places_series(): array {
	$out = array(
		'sunday' => array(
			'label'  => 'Sunday Service',
			'title'  => 'Sunday Service',
			'locked' => true,
		),
	);
	foreach ( emerson_listed_series_all() as $rule ) {
		$out[ $rule['id'] ] = array(
			'label' => $rule['title'],
			'title' => $rule['title'],
		);
	}
	return $out;
}

function emerson_event_places_current(): string {
	$raw = isset( $_REQUEST['event'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['event'] ) ) : 'sunday';
	$all = emerson_event_places_series();
	return isset( $all[ $raw ] ) ? $raw : 'sunday';
}

/** How many dates each Event Places window shows. */
function emerson_event_places_window_size(): int {
	return 12;
}

/**
 * Window index: 0 is the default upcoming range (cannot go earlier).
 */
function emerson_event_places_win(): int {
	return max( 0, (int) ( $_REQUEST['win'] ?? 0 ) );
}

/**
 * Upcoming rows for the current event + window (old dates drop off as you advance).
 *
 * @return list<array<string, mixed>>
 */
function emerson_event_places_rows( string $event ): array {
	$size   = emerson_event_places_window_size();
	$win    = emerson_event_places_win();
	$need   = $size * ( $win + 1 );
	$series = emerson_event_places_series();
	if ( 'sunday' === $event ) {
		$all = emerson_sunday_upcoming( $need );
	} elseif ( isset( $series[ $event ] ) ) {
		$all = emerson_group_upcoming( $series[ $event ]['title'], $need );
	} else {
		$all = array();
	}
	return array_values( array_slice( $all, $win * $size, $size ) );
}

/**
 * Date-range label + prev/next arrows. Prev is disabled on the default window.
 *
 * @param list<array{date:string}> $rows
 */
function emerson_event_places_render_nav( string $event, array $rows ): void {
	$win   = emerson_event_places_win();
	$first = $rows[0]['date'] ?? '';
	$last  = $rows[ count( $rows ) - 1 ]['date'] ?? '';
	$label = '';
	if ( $first !== '' && $last !== '' ) {
		$label = emerson_board_format_date( $first ) . ' – ' . emerson_board_format_date( $last );
		if ( $first === $last ) {
			$label = emerson_board_format_date( $first );
		}
	}

	$base = admin_url( 'admin.php?page=emerson-event-places&event=' . rawurlencode( $event ) );
	$prev = $win > 0 ? $base . '&win=' . ( $win - 1 ) : '';
	$next = $base . '&win=' . ( $win + 1 );

	echo '<div class="emerson-event-places-nav" role="navigation" aria-label="Date range">';
	if ( $prev !== '' ) {
		echo '<a class="button emerson-event-places-nav__prev" href="' . esc_url( $prev ) . '">&larr; Earlier</a>';
	} else {
		echo '<span class="button disabled emerson-event-places-nav__prev" aria-disabled="true">&larr; Earlier</span>';
	}
	echo '<p class="emerson-event-places-nav__range">' . esc_html( $label !== '' ? $label : 'No upcoming dates' ) . '</p>';
	echo '<a class="button emerson-event-places-nav__next" href="' . esc_url( $next ) . '">Later &rarr;</a>';
	echo '</div>';
}

/**
 * @return list<string>
 */
function emerson_event_places_weekday_labels(): array {
	return array(
		0 => 'Sunday',
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
	);
}

/**
 * @return list<string>
 */
function emerson_event_places_nth_labels(): array {
	return array(
		1 => '1st',
		2 => '2nd',
		3 => '3rd',
		4 => '4th',
	);
}

function emerson_event_places_schedule_label( array $rule ): string {
	$nths = emerson_event_places_nth_labels();
	$days = emerson_event_places_weekday_labels();
	$nth  = $nths[ (int) ( $rule['nth'] ?? 1 ) ] ?? '1st';
	$day  = $days[ (int) ( $rule['wday'] ?? 1 ) ] ?? 'Monday';
	$time = substr( (string) ( $rule['time'] ?? '' ), 0, 5 );
	return $nth . ' ' . $day . ( $time !== '' ? ', ' . $time : '' );
}

/**
 * Redirect back to Event Places without edit_series so the form returns to Add series.
 */
function emerson_event_places_series_redirect( string $message, string $event = '' ): void {
	$args = array(
		'page'           => 'emerson-event-places',
		'series_notice'  => $message,
	);
	if ( $event !== '' && $event !== 'sunday' ) {
		$args['event'] = $event;
	}
	wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
	exit;
}

/**
 * Handle add / edit / remove series POSTs.
 */
function emerson_event_places_series_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return null;
	}

	if ( ! empty( $_POST['emerson_series_remove'] ) ) {
		check_admin_referer( 'emerson_series_manage' );
		$id     = sanitize_title( (string) wp_unslash( $_POST['series_id'] ?? '' ) );
		$result = emerson_listed_series_remove( $id );
		if ( ! empty( $result['ok'] ) ) {
			emerson_event_places_series_redirect( $result['message'] );
		}
		return $result['message'];
	}

	if ( empty( $_POST['emerson_series_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_series_manage' );

	$old_id = sanitize_title( (string) wp_unslash( $_POST['series_id'] ?? '' ) );
	$nth    = (int) ( $_POST['series_nth'] ?? 1 );
	$wday   = (int) ( $_POST['series_wday'] ?? 1 );
	$start  = sanitize_text_field( (string) wp_unslash( $_POST['series_time'] ?? '19:00' ) );
	$end    = sanitize_text_field( (string) wp_unslash( $_POST['series_end'] ?? '21:00' ) );
	if ( preg_match( '/^\d{1,2}:\d{2}$/', $start ) ) {
		$start .= ':00';
	}
	if ( preg_match( '/^\d{1,2}:\d{2}$/', $end ) ) {
		$end .= ':00';
	}

	$result = emerson_listed_series_upsert(
		array(
			'id'          => $old_id,
			'title'       => sanitize_text_field( (string) wp_unslash( $_POST['series_title'] ?? '' ) ),
			'time'        => $start,
			'end'         => $end,
			'nth'         => $nth,
			'wday'        => $wday,
			'recurring'   => emerson_listed_recurring_code( $nth, $wday ),
			'location'    => EMERSON_GROUP_LOCATION_ROTATES,
			'description' => sanitize_textarea_field( (string) wp_unslash( $_POST['series_description'] ?? '' ) ),
			'link'        => home_url( '/engage/' ),
			'link_title'  => 'Engage',
		),
		$old_id
	);

	if ( ! empty( $result['ok'] ) ) {
		$event = (string) ( $result['rule']['id'] ?? $old_id );
		emerson_event_places_series_redirect( $result['message'], $event );
	}

	return $result['message'];
}

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Event Places',
			'Event Places',
			'edit_pages',
			'emerson-event-places',
			'emerson_event_places_admin_page',
			'dashicons-location-alt',
			57.5
		);
	}
);

function emerson_event_places_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit Event Places.' ) );
	}

	$series_notice   = emerson_event_places_series_admin_save();
	$event           = emerson_event_places_current();
	$series          = emerson_event_places_series();
	$defaults_notice = null;
	$notice          = null;

	if ( 'sunday' === $event ) {
		$defaults_notice = emerson_sunday_defaults_save();
		$notice          = emerson_sunday_admin_save();
	} elseif ( isset( $series[ $event ] ) && empty( $series[ $event ]['locked'] ) ) {
		$notice = emerson_group_admin_save( $series[ $event ]['title'] );
	}

	if ( $series_notice === null && isset( $_GET['series_notice'] ) ) {
		$series_notice = sanitize_text_field( (string) wp_unslash( $_GET['series_notice'] ) );
	}

	// After a successful save we redirect without edit_series; only open Edit from an Edit link.
	$edit_id   = isset( $_GET['edit_series'] ) ? sanitize_title( (string) wp_unslash( $_GET['edit_series'] ) ) : '';
	$edit_rule = $edit_id !== '' ? emerson_listed_series_by_id( $edit_id ) : null;

	echo '<div class="wrap emerson-sunday-admin emerson-event-places"><h1>Event Places</h1>';
	echo '<style>'
		. '.emerson-sunday-admin table input.large-text,.emerson-sunday-admin .emerson-sunday-default input.large-text{min-width:16rem}'
		. '.emerson-sunday-admin input.emerson-sunday-time{width:7.5rem}'
		. '.emerson-sunday-admin select{min-width:8rem}'
		. '.emerson-event-places-picker{margin:0 0 1rem;padding:0.85rem 1rem;background:#fff;border:1px solid #c3c4c7;max-width:40rem}'
		. '.emerson-event-places-picker label{font-weight:600;margin-right:0.5rem}'
		. '.emerson-event-places-nav{display:flex;align-items:center;justify-content:center;flex-wrap:wrap;gap:0.75rem 1rem;margin:0 auto 0.75rem;width:100%}'
		. '.emerson-event-places-nav__range{margin:0;text-align:center;font-weight:600}'
		. '.emerson-event-places-nav .button.disabled{opacity:0.45;pointer-events:none}'
		. '.emerson-event-places-series{margin:0 0 1.5rem;padding:1rem;background:#fff;border:1px solid #c3c4c7;max-width:52rem}'
		. '.emerson-event-places-series table{margin:0.75rem 0}'
		. '.emerson-event-places-series .form-table th{width:10rem}'
		. '.emerson-sunday-default{margin:1rem 0 1.5rem;padding:1rem 1rem 0;background:#fff;border:1px solid #c3c4c7}'
		. '.emerson-sunday-default-actions{display:flex;flex-wrap:wrap;align-items:center;gap:.75rem 1.25rem}'
		. '.emerson-sunday-default-actions label{display:inline-flex;align-items:center;gap:.4rem;margin:0}'
		. '.emerson-sunday-confirm{display:none;position:fixed;inset:0;z-index:100000;align-items:center;justify-content:center;padding:1.5rem;background:rgba(0,0,0,.45)}'
		. '.emerson-sunday-confirm.is-open{display:flex}'
		. '.emerson-sunday-confirm__panel{max-width:32rem;width:100%;background:#fff;border:1px solid #c3c4c7;box-shadow:0 8px 24px rgba(0,0,0,.18);padding:1.25rem 1.35rem}'
		. '.emerson-sunday-confirm__panel h2{margin:0 0 .75rem;font-size:1.15rem}'
		. '.emerson-sunday-confirm__panel p{margin:0 0 .75rem}'
		. '.emerson-sunday-confirm__actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}'
		. '</style>';

	if ( is_string( $series_notice ) && $series_notice !== '' ) {
		$class = ( str_contains( $series_notice, 'already exists' ) || str_contains( $series_notice, 'not found' ) || str_contains( $series_notice, 'Enter a' ) || str_contains( $series_notice, 'Permission' ) )
			? 'notice-error'
			: 'notice-success';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $series_notice ) . '</p></div>';
	}
	if ( is_string( $defaults_notice ) && $defaults_notice !== '' ) {
		$notice_class = str_starts_with( $defaults_notice, 'Pick a start date' ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $defaults_notice ) . '</p></div>';
	}
	if ( is_string( $notice ) && $notice !== '' ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}

	emerson_event_places_render_series_manager( $edit_rule );

	echo '<form method="get" class="emerson-event-places-picker">';
	echo '<input type="hidden" name="page" value="emerson-event-places">';
	echo '<label for="emerson-event-places-event">Event</label>';
	echo '<select name="event" id="emerson-event-places-event" onchange="this.form.submit()">';
	foreach ( $series as $key => $meta ) {
		echo '<option value="' . esc_attr( $key ) . '"' . selected( $event, $key, false ) . '>' . esc_html( $meta['label'] ) . '</option>';
	}
	echo '</select>';
	echo '<p class="description" style="margin:0.5rem 0 0">Edit places for recurring meetings here. The calendar sidebar and iCal follow these saves. One-off events still belong in Church Admin → Calendar. Sunday Service is built-in; other series can be added or removed above.</p>';
	echo '</form>';

	if ( 'sunday' === $event ) {
		emerson_event_places_render_sunday();
	} elseif ( isset( $series[ $event ] ) ) {
		emerson_event_places_render_group( $series[ $event ]['title'], $event );
	}

	echo '</div>';
}

/**
 * @param array{id:string,title:string,time:string,end:string,nth:int,wday:int,description:string}|null $edit_rule
 */
function emerson_event_places_render_series_manager( ?array $edit_rule ): void {
	$listed = emerson_listed_series_all();
	$nths   = emerson_event_places_nth_labels();
	$days   = emerson_event_places_weekday_labels();

	echo '<div class="emerson-event-places-series">';
	echo '<h2>Recurring series</h2>';
	echo '<p>Sunday Service stays built-in (homepage, mail, and rota). Add other monthly series here — each one is created or updated on the Church Admin calendar so the sidebar and iCal stay in sync.</p>';

	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Series</th><th>Schedule</th><th>Actions</th>';
	echo '</tr></thead><tbody>';
	echo '<tr><td><strong>Sunday Service</strong></td><td>Every Sunday (built-in)</td><td><span class="description">Locked</span></td></tr>';
	foreach ( $listed as $rule ) {
		$edit_url = admin_url( 'admin.php?page=emerson-event-places&event=' . rawurlencode( $rule['id'] ) . '&edit_series=' . rawurlencode( $rule['id'] ) . '#emerson-series-form' );
		echo '<tr>';
		echo '<td><strong>' . esc_html( $rule['title'] ) . '</strong></td>';
		echo '<td>' . esc_html( emerson_event_places_schedule_label( $rule ) ) . '</td>';
		echo '<td>';
		echo '<a class="button button-small" href="' . esc_url( $edit_url ) . '">Edit</a> ';
		echo '<form method="post" style="display:inline" onsubmit="return confirm(' . esc_attr( wp_json_encode( 'Remove “' . $rule['title'] . '”? Future Church Admin dates for this series will be deleted.' ) ) . ');">';
		wp_nonce_field( 'emerson_series_manage' );
		echo '<input type="hidden" name="series_id" value="' . esc_attr( $rule['id'] ) . '">';
		echo '<button type="submit" name="emerson_series_remove" value="1" class="button button-small">Remove</button>';
		echo '</form>';
		echo '</td></tr>';
	}
	if ( $listed === array() ) {
		echo '<tr><td colspan="3"><span class="description">No custom series yet.</span></td></tr>';
	}
	echo '</tbody></table>';

	$is_edit = is_array( $edit_rule );
	$title   = $is_edit ? $edit_rule['title'] : '';
	$nth     = $is_edit ? (int) $edit_rule['nth'] : 1;
	$wday    = $is_edit ? (int) $edit_rule['wday'] : 1;
	$time    = $is_edit ? substr( $edit_rule['time'], 0, 5 ) : '19:00';
	$end     = $is_edit ? substr( $edit_rule['end'], 0, 5 ) : '21:00';
	$desc    = $is_edit ? $edit_rule['description'] : '';
	$id      = $is_edit ? $edit_rule['id'] : '';

	echo '<form method="post" id="emerson-series-form" style="margin-top:1.25rem">';
	wp_nonce_field( 'emerson_series_manage' );
	echo '<input type="hidden" name="series_id" value="' . esc_attr( $id ) . '">';
	echo '<h3>' . ( $is_edit ? 'Edit series' : 'Add series' ) . '</h3>';
	if ( $is_edit ) {
		echo '<p class="description">Editing <strong>' . esc_html( $title ) . '</strong>. <a href="' . esc_url( admin_url( 'admin.php?page=emerson-event-places' ) ) . '">Cancel</a></p>';
	}
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="emerson-series-title">Title</label></th><td><input type="text" class="regular-text" id="emerson-series-title" name="series_title" value="' . esc_attr( $title ) . '" required maxlength="120"></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-series-nth">Which</label></th><td><select id="emerson-series-nth" name="series_nth">';
	foreach ( $nths as $value => $label ) {
		echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( $nth, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select> <select id="emerson-series-wday" name="series_wday" aria-label="Weekday">';
	foreach ( $days as $value => $label ) {
		echo '<option value="' . esc_attr( (string) $value ) . '"' . selected( $wday, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	echo '</select> <span class="description">of each month</span></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-series-time">Start</label></th><td><input type="time" id="emerson-series-time" name="series_time" value="' . esc_attr( $time ) . '" required> <label for="emerson-series-end">End</label> <input type="time" id="emerson-series-end" name="series_end" value="' . esc_attr( $end ) . '" required></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-series-description">Description</label></th><td><textarea class="large-text" rows="3" id="emerson-series-description" name="series_description" placeholder="Shown in the calendar sidebar">' . esc_textarea( $desc ) . '</textarea></td></tr>';
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_series_save" value="1" class="button button-primary">' . esc_html( $is_edit ? 'Update series' : 'Add series' ) . '</button></p>';
	echo '</form>';
	echo '</div>';
}

function emerson_event_places_render_sunday(): void {
	$defaults   = emerson_sunday_defaults();
	$from_value = $defaults['from'];
	if ( $from_value === '' ) {
		$upcoming_for_from = emerson_sunday_upcoming( 1 );
		$from_value        = $upcoming_for_from[0]['date'] ?? gmdate( 'Y-m-d' );
	}

	echo '<p>The homepage says <strong>This week, meet us at [time], at the [place] located at [address]</strong>. A new street address in <strong>Located at</strong> is kept when you save, even if Status stays <strong>Regular</strong>. Saving <strong>Cancelled</strong> or <strong>Moved</strong> emails anyone assigned that day or who ticked they can serve, plus the office. Saves also update the Sunday Service row on the calendar.</p>';

	echo '<form method="post" class="emerson-sunday-default" id="emerson-sunday-default-form">';
	wp_nonce_field( 'emerson_sunday_defaults_save' );
	echo '<input type="hidden" name="emerson_sunday_defaults_save" value="1">';
	echo '<input type="hidden" name="event" value="sunday">';
	echo '<h2>Regular Sunday default</h2>';
	echo '<p>Save sets the usual time and place for <strong>Regular</strong> Sundays from the date below onward, including Sundays after the 12 listed later. Regular Sundays before that date keep the previous place. Status stays <strong>Regular</strong> here — use the list below for Cancelled or Moved. Tick <strong>Overwrite custom Regular Sundays</strong> to replace saved custom times and places; leave it unticked to choose in a confirm box. Moved and Cancelled stay as they are. Updating this does not send email. Save the list below first if you still have unsaved edits there.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="emerson-default-from">Date</label></th><td><input type="date" id="emerson-default-from" name="default_from" value="' . esc_attr( $from_value ) . '" required> <span class="description">Apply from this Sunday onward.</span></td></tr>';
	echo '<tr><th scope="row">Status</th><td><input type="hidden" name="default_status" value="regular"><strong>Regular</strong> <span class="description">Cancelled and Moved Sundays stay as they are.</span></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-default-time">Time</label></th><td><input type="text" class="emerson-sunday-time" id="emerson-default-time" name="default_time" value="' . esc_attr( emerson_sunday_admin_time_value( $defaults['time'] ) ) . '" placeholder="7:00 PM" inputmode="text" autocomplete="off"> <span class="description">Example: 7:00 PM</span></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-default-venue">At the</label></th><td><input type="text" class="large-text" id="emerson-default-venue" name="default_venue" value="' . esc_attr( $defaults['venue'] ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-default-address">Located at</label></th><td><input type="text" class="large-text" id="emerson-default-address" name="default_address" value="' . esc_attr( $defaults['address'] ) . '"></td></tr>';
	echo '<tr><th scope="row"><label for="emerson-default-note">Note</label></th><td><input type="text" class="large-text" id="emerson-default-note" name="default_note" value="' . esc_attr( $defaults['note'] ) . '" placeholder="Optional"></td></tr>';
	echo '</tbody></table>';
	echo '<p class="submit emerson-sunday-default-actions">';
	echo '<button type="submit" class="button button-primary">Update regular Sundays</button>';
	echo '<label for="emerson-default-overwrite"><input type="checkbox" name="default_overwrite" id="emerson-default-overwrite" value="1"> Overwrite custom Regular Sundays</label>';
	echo '</p>';
	echo '</form>';

	echo '<div class="emerson-sunday-confirm" id="emerson-sunday-default-confirm" role="dialog" aria-modal="true" aria-labelledby="emerson-sunday-default-confirm-title" hidden>';
	echo '<div class="emerson-sunday-confirm__panel">';
	echo '<h2 id="emerson-sunday-default-confirm-title">Update Regular Sunday default?</h2>';
	echo '<p>Setting the default time, place, and note can overwrite custom changes already saved on <strong>Regular</strong> Sundays from the apply-from date onward.</p>';
	echo '<p><strong>Keep changes already made</strong> updates the default for Sundays that still follow the old one. <strong>Overwrite all Regular Sundays</strong> replaces those custom Regular times and places with the new default. Moved and Cancelled Sundays stay as they are either way.</p>';
	echo '<p>Unsaved edits in the list below are not protected — save that list first if you need them.</p>';
	echo '<div class="emerson-sunday-confirm__actions">';
	echo '<button type="button" class="button button-primary" data-emerson-default-choice="keep">Keep changes already made</button>';
	echo '<button type="button" class="button" data-emerson-default-choice="overwrite">Overwrite all Regular Sundays</button>';
	echo '<button type="button" class="button" data-emerson-default-choice="cancel">Cancel</button>';
	echo '</div></div></div>';
	echo '<script>(function(){var form=document.getElementById("emerson-sunday-default-form");var box=document.getElementById("emerson-default-overwrite");var dialog=document.getElementById("emerson-sunday-default-confirm");if(!form||!box||!dialog){return;}var allow=false;function openDialog(){dialog.hidden=false;dialog.classList.add("is-open");}function closeDialog(){dialog.classList.remove("is-open");dialog.hidden=true;}form.addEventListener("submit",function(e){if(allow||box.checked){allow=false;return;}e.preventDefault();openDialog();});dialog.addEventListener("click",function(e){var t=e.target;if(!(t instanceof HTMLElement)){return;}var choice=t.getAttribute("data-emerson-default-choice");if(!choice){if(t===dialog){closeDialog();}return;}if(choice==="cancel"){closeDialog();return;}box.checked=choice==="overwrite";allow=true;closeDialog();if(typeof form.requestSubmit==="function"){form.requestSubmit();}else{form.submit();}});})();</script>';

	$rows = emerson_event_places_rows( 'sunday' );
	$win  = emerson_event_places_win();
	emerson_event_places_render_nav( 'sunday', $rows );

	echo '<form method="post" onsubmit="if (this.dataset.emersonSaved) { return false; } this.dataset.emersonSaved = \'1\';">';
	wp_nonce_field( 'emerson_sunday_save' );
	echo '<input type="hidden" name="event" value="sunday">';
	echo '<input type="hidden" name="win" value="' . esc_attr( (string) $win ) . '">';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Sunday</th><th>Status</th><th>Time</th><th>At the</th><th>Located at</th><th>Note</th><th>Signed up</th>';
	echo '</tr></thead><tbody>';
	foreach ( $rows as $base ) {
		$shown   = emerson_sunday_display( $base );
		$date    = $base['date'];
		$servers = emerson_sunday_servers( $date );
		$time    = emerson_sunday_admin_time_value( $shown['time'] );
		$note    = $shown['note'];
		$status  = $shown['status'];
		$signed  = array();
		foreach ( $servers as $person ) {
			$signed[] = $person['name'] . ( $person['jobs'] ? ' (' . implode( ', ', $person['jobs'] ) . ')' : '' );
		}
		echo '<tr>';
		echo '<td><strong>' . esc_html( emerson_sunday_format_date( $date ) ) . '</strong></td>';
		echo '<td><select name="sunday[' . esc_attr( $date ) . '][status]">';
		foreach ( array( 'regular' => 'Regular', 'cancelled' => 'Cancelled', 'moved' => 'Moved' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="text" class="emerson-sunday-time" name="sunday[' . esc_attr( $date ) . '][time]" value="' . esc_attr( $time ) . '" placeholder="7:00 PM" inputmode="text" autocomplete="off"></td>';
		echo '<td><input type="text" class="large-text" name="sunday[' . esc_attr( $date ) . '][venue]" value="' . esc_attr( $shown['venue'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="sunday[' . esc_attr( $date ) . '][address]" value="' . esc_attr( $shown['address'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="sunday[' . esc_attr( $date ) . '][note]" value="' . esc_attr( $note ) . '" placeholder="Optional"></td>';
		echo '<td>' . ( $signed ? esc_html( implode( ', ', $signed ) ) : 'Nobody yet' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_sunday_save" value="1" class="button button-primary">Save Sundays</button></p>';
	echo '</form>';
}

function emerson_event_places_render_group( string $title, string $event_key = '' ): void {
	if ( $event_key === '' ) {
		$rule      = emerson_group_rule_by_title( $title );
		$event_key = $rule['id'] ?? sanitize_title( $title );
	}
	$rows = emerson_event_places_rows( $event_key );
	$win  = emerson_event_places_win();
	$rule = emerson_group_rule_by_title( $title ) ?? emerson_listed_series_by_id( $event_key );
	$sched = $rule ? emerson_event_places_schedule_label( $rule ) : '';
	echo '<p>' . esc_html( $title );
	if ( $sched !== '' ) {
		echo ' (' . esc_html( $sched ) . ')';
	}
	echo ' defaults to <strong>Rotating location — check This Week at Emerson</strong>. Name this month’s host in <strong>Located at</strong> (and/or <strong>At the</strong>). Choose <strong>Cancelled</strong> when that date is off.</p>';
	emerson_event_places_render_nav( $event_key, $rows );
	echo '<form method="post">';
	wp_nonce_field( 'emerson_group_save' );
	echo '<input type="hidden" name="event" value="' . esc_attr( $event_key ) . '">';
	echo '<input type="hidden" name="win" value="' . esc_attr( (string) $win ) . '">';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Date</th><th>Status</th><th>Time</th><th>At the</th><th>Located at</th><th>Note</th>';
	echo '</tr></thead><tbody>';
	foreach ( $rows as $base ) {
		$shown  = emerson_group_display( $title, $base );
		$date   = $base['date'];
		$time   = substr( $shown['time'], 0, 5 );
		$status = $shown['status'];
		echo '<tr>';
		echo '<td><strong>' . esc_html( emerson_board_format_date( $date ) ) . '</strong></td>';
		echo '<td><select name="group[' . esc_attr( $date ) . '][status]">';
		foreach ( array( 'regular' => 'Regular', 'cancelled' => 'Cancelled', 'moved' => 'Moved' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="time" name="group[' . esc_attr( $date ) . '][time]" value="' . esc_attr( $time ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="group[' . esc_attr( $date ) . '][venue]" value="' . esc_attr( $shown['venue'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="group[' . esc_attr( $date ) . '][address]" value="' . esc_attr( $shown['address'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="group[' . esc_attr( $date ) . '][note]" value="' . esc_attr( $shown['note'] ) . '" placeholder="Optional"></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_group_save" value="1" class="button button-primary">Save ' . esc_html( $title ) . '</button></p>';
	echo '</form>';
}
