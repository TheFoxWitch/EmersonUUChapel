<?php
/**
 * Event Places: one admin screen for Sunday, Womyn’s Web, Men’s Night, and Board Meeting.
 *
 * Dropdown picks the series; the table edits upcoming dates. Emerson stores exceptions;
 * each save also writes location and time onto the matching Church Admin calendar row.
 * Old Sunday Location / Board Location menu items redirect here.
 */

declare( strict_types=1 );

/**
 * @return array<string, array{label:string,title:string}>
 */
function emerson_event_places_series(): array {
	return array(
		'sunday'      => array(
			'label' => 'Sunday Service',
			'title' => 'Sunday Service',
		),
		'womyns-web'  => array(
			'label' => "Womyn's Web",
			'title' => "Womyn's Web",
		),
		'mens-night'  => array(
			'label' => "Men's Night",
			'title' => "Men's Night",
		),
		'board'       => array(
			'label' => 'Board Meeting',
			'title' => 'Board Meeting',
		),
	);
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
	$size = emerson_event_places_window_size();
	$win  = emerson_event_places_win();
	$need = $size * ( $win + 1 );
	$series = emerson_event_places_series();
	if ( 'sunday' === $event ) {
		$all = emerson_sunday_upcoming( $need );
	} elseif ( 'board' === $event ) {
		$all = emerson_board_upcoming( $need );
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
	$win  = emerson_event_places_win();
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

	$event           = emerson_event_places_current();
	$series          = emerson_event_places_series();
	$defaults_notice = null;
	$notice          = null;

	if ( 'sunday' === $event ) {
		$defaults_notice = emerson_sunday_defaults_save();
		$notice          = emerson_sunday_admin_save();
	} elseif ( 'board' === $event ) {
		$notice = emerson_board_admin_save();
	} elseif ( in_array( $event, array( 'womyns-web', 'mens-night' ), true ) ) {
		$notice = emerson_group_admin_save( $series[ $event ]['title'] );
	}

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

	if ( is_string( $defaults_notice ) && $defaults_notice !== '' ) {
		$notice_class = str_starts_with( $defaults_notice, 'Pick a start date' ) ? 'notice-error' : 'notice-success';
		echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . esc_html( $defaults_notice ) . '</p></div>';
	}
	if ( is_string( $notice ) && $notice !== '' ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}

	echo '<form method="get" class="emerson-event-places-picker">';
	echo '<input type="hidden" name="page" value="emerson-event-places">';
	echo '<label for="emerson-event-places-event">Event</label>';
	echo '<select name="event" id="emerson-event-places-event" onchange="this.form.submit()">';
	foreach ( $series as $key => $meta ) {
		echo '<option value="' . esc_attr( $key ) . '"' . selected( $event, $key, false ) . '>' . esc_html( $meta['label'] ) . '</option>';
	}
	echo '</select>';
	echo '<p class="description" style="margin:0.5rem 0 0">Edit places for recurring meetings here. The calendar sidebar and iCal follow these saves. One-off events still belong in Church Admin → Calendar.</p>';
	echo '</form>';

	if ( 'sunday' === $event ) {
		emerson_event_places_render_sunday();
	} elseif ( 'board' === $event ) {
		emerson_event_places_render_board();
	} else {
		emerson_event_places_render_group( $series[ $event ]['title'] );
	}

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

function emerson_event_places_render_board(): void {
	$rows = emerson_event_places_rows( 'board' );
	$win  = emerson_event_places_win();
	echo '<p>Board meetings are on the third Monday at 6:30 pm. Type this month’s place in <strong>Located at</strong> and save. Leave <strong>At the</strong> as Rotating (or empty) and the calendar says <strong>Rotating location — check This Week at Emerson</strong>. Choose <strong>Cancelled</strong> when there is no meeting.</p>';
	emerson_event_places_render_nav( 'board', $rows );
	echo '<form method="post">';
	wp_nonce_field( 'emerson_board_save' );
	echo '<input type="hidden" name="event" value="board">';
	echo '<input type="hidden" name="win" value="' . esc_attr( (string) $win ) . '">';
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>Monday</th><th>Status</th><th>Time</th><th>At the</th><th>Located at</th><th>Note</th>';
	echo '</tr></thead><tbody>';
	foreach ( $rows as $base ) {
		$shown  = emerson_board_display( $base );
		$date   = $base['date'];
		$time   = substr( $shown['time'], 0, 5 );
		$status = $shown['status'];
		echo '<tr>';
		echo '<td><strong>' . esc_html( emerson_board_format_date( $date ) ) . '</strong></td>';
		echo '<td><select name="board[' . esc_attr( $date ) . '][status]">';
		foreach ( array( 'regular' => 'Regular', 'cancelled' => 'Cancelled', 'moved' => 'Moved' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="time" name="board[' . esc_attr( $date ) . '][time]" value="' . esc_attr( $time ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="board[' . esc_attr( $date ) . '][venue]" value="' . esc_attr( $shown['venue'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="board[' . esc_attr( $date ) . '][address]" value="' . esc_attr( $shown['address'] ) . '"></td>';
		echo '<td><input type="text" class="large-text" name="board[' . esc_attr( $date ) . '][note]" value="' . esc_attr( $shown['note'] ) . '" placeholder="Optional"></td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" name="emerson_board_save" value="1" class="button button-primary">Save board meetings</button></p>';
	echo '</form>';
}

function emerson_event_places_render_group( string $title ): void {
	$event_key = strcasecmp( $title, "Men's Night" ) === 0 ? 'mens-night' : 'womyns-web';
	$rows      = emerson_event_places_rows( $event_key );
	$win       = emerson_event_places_win();
	echo '<p>' . esc_html( $title ) . ' defaults to <strong>Rotating location — check This Week at Emerson</strong>. Name this month’s host in <strong>Located at</strong> (and/or <strong>At the</strong>). Choose <strong>Cancelled</strong> when that date is off.</p>';
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
