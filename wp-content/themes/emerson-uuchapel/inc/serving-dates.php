<?php
/**
 * Serving dates on the Members page (32): members tick the dates they CAN serve.
 *
 * Church Admin records only the dates someone can't serve (church_admin_not_available) and treats every other date
 * as available; its auto-fill, assignment warnings, clash emails and app all read that table. The church wants a
 * date nobody has answered to count as "can't serve", so:
 * - emerson_serving_answers keeps every answer a member saves: can_serve 1 (ticked) or 0 (left unticked).
 * - emerson_serving_sync() keeps church_admin_not_available matching it for as far ahead as auto-fill can schedule:
 *   a row for every date without a "can serve" answer, and none for dates with one.
 * Church Admin's own screen (Schedules → Not available, in wp-admin) still edits the plugin table directly, and the
 * daily sync overrides it. Admins set other people's dates with "Choose person" on the Members page instead.
 * Before removing this file, run emerson_serving_undo() (wp eval) so only real "can't serve" answers remain.
 */

declare( strict_types=1 );

const EMERSON_SERVING_DB_VERSION = '1';
// Church Admin's auto-fill offers up to 6 months ahead.
const EMERSON_SERVING_MONTHS = 6;

function emerson_serving_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'emerson_serving_answers';
}

function emerson_serving_ready(): bool {
	return function_exists( 'church_admin_premium_get_next_twelve' );
}

add_action(
	'init',
	static function (): void {
		if ( ! emerson_serving_ready() || get_option( 'emerson_serving_db_version' ) === EMERSON_SERVING_DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = emerson_serving_table();
		dbDelta(
			"CREATE TABLE {$table} (
  people_id int(11) NOT NULL,
  serve_date date NOT NULL,
  can_serve tinyint(1) NOT NULL,
  answered datetime NOT NULL,
  PRIMARY KEY  (people_id,serve_date)
) {$wpdb->get_charset_collate()};"
		);
		// Dates saved before this change are real "can't serve" answers.
		$wpdb->query(
			"INSERT IGNORE INTO {$table} (people_id, serve_date, can_serve, answered)
			SELECT DISTINCT people_id, unavailable, 0, NOW() FROM {$wpdb->prefix}church_admin_not_available WHERE unavailable IS NOT NULL"
		);
		update_option( 'emerson_serving_db_version', EMERSON_SERVING_DB_VERSION );
		emerson_serving_sync();
	}
);

add_action( 'emerson_serving_daily_sync', static fn() => emerson_serving_sync() );
add_action(
	'init',
	static function (): void {
		if ( emerson_serving_ready() && ! wp_next_scheduled( 'emerson_serving_daily_sync' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'emerson_serving_daily_sync' );
		}
	}
);

/** The dates Church Admin's form lists (its next dozen or so per service), oldest first. */
function emerson_serving_form_dates(): array {
	global $wpdb;
	$days = array();
	foreach ( $wpdb->get_col( "SELECT service_frequency FROM {$wpdb->prefix}church_admin_services ORDER BY service_day" ) as $frequency ) {
		$next = church_admin_premium_get_next_twelve( $frequency );
		if ( is_array( $next ) ) {
			$days = $days + $next;
		}
	}
	ksort( $days );
	return array_values( $days );
}

/** The form's dates plus every weekly service date up to auto-fill's furthest reach. */
function emerson_serving_sync_dates(): array {
	global $wpdb;
	$dates = emerson_serving_form_dates();
	$today = current_time( 'Y-m-d' );
	$end   = ( new DateTime( $today ) )->modify( '+' . EMERSON_SERVING_MONTHS . ' months' );
	foreach ( $wpdb->get_col( "SELECT service_frequency FROM {$wpdb->prefix}church_admin_services" ) as $frequency ) {
		if ( ! preg_match( '/^7([0-6])$/', (string) $frequency, $m ) ) {
			continue;
		}
		$day = new DateTime( $today );
		while ( (int) $day->format( 'w' ) !== (int) $m[1] ) {
			$day->modify( '+1 day' );
		}
		for ( ; $day <= $end; $day->modify( '+7 days' ) ) {
			$dates[] = $day->format( 'Y-m-d' );
		}
	}
	$dates = array_values( array_unique( $dates ) );
	sort( $dates );
	return $dates;
}

/** Make Church Admin's "can't serve" table match the saved answers, for everyone or one person. */
function emerson_serving_sync( ?int $people_id = null ): void {
	global $wpdb;
	if ( ! emerson_serving_ready() ) {
		return;
	}
	$not_available = $wpdb->prefix . 'church_admin_not_available';
	$people        = $wpdb->prefix . 'church_admin_people';
	$answers       = emerson_serving_table();
	$only_na       = $people_id ? $wpdb->prepare( ' AND na.people_id = %d', $people_id ) : '';
	$only_person   = $people_id ? $wpdb->prepare( ' AND p.people_id = %d', $people_id ) : '';

	$wpdb->query(
		"DELETE na FROM {$not_available} na
		JOIN {$answers} a ON a.people_id = na.people_id AND a.serve_date = na.unavailable AND a.can_serve = 1
		WHERE 1 = 1{$only_na}"
	);
	foreach ( emerson_serving_sync_dates() as $date ) {
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$not_available} (people_id, unavailable)
				SELECT p.people_id, %s FROM {$people} p
				WHERE NOT EXISTS ( SELECT 1 FROM {$not_available} x WHERE x.people_id = p.people_id AND x.unavailable = %s )
				AND NOT EXISTS ( SELECT 1 FROM {$answers} a WHERE a.people_id = p.people_id AND a.serve_date = %s AND a.can_serve = 1 ){$only_person}",
				$date,
				$date,
				$date
			)
		);
	}
}

/** Put Church Admin back to its own rules: remove the upcoming "not answered" rows, keeping real "can't serve" answers. */
function emerson_serving_undo(): int {
	global $wpdb;
	wp_clear_scheduled_hook( 'emerson_serving_daily_sync' );
	return (int) $wpdb->query(
		$wpdb->prepare(
			"DELETE na FROM {$wpdb->prefix}church_admin_not_available na
			LEFT JOIN " . emerson_serving_table() . " a ON a.people_id = na.people_id AND a.serve_date = na.unavailable AND a.can_serve = 0
			WHERE a.people_id IS NULL AND na.unavailable >= %s",
			current_time( 'Y-m-d' )
		)
	);
}

/** The person Church Admin's form is for: someone an admin picked with "Choose person", otherwise the member. */
function emerson_serving_person(): ?object {
	global $wpdb;
	$people = $wpdb->prefix . 'church_admin_people';
	if ( ! empty( $_REQUEST['people_id'] ) && function_exists( 'church_admin_premium_level_check' ) && church_admin_premium_level_check( 'Rota' ) ) {
		$person = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$people} WHERE people_id = %d", (int) $_REQUEST['people_id'] ) );
		if ( $person ) {
			return $person;
		}
	}
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$people} WHERE user_id = %d", get_current_user_id() ) ) ?: null;
}

// On Save, record the answers and hand Church Admin the unticked dates as "can't serve", before it saves them.
add_filter(
	'pre_do_shortcode_tag',
	static function ( $return, $tag, $attr ) {
		global $wpdb;
		if ( 'church_admin' !== $tag || 'not-available' !== ( $attr['type'] ?? '' ) || empty( $_POST['not-available'] ) || ! is_user_logged_in() || ! emerson_serving_ready() ) {
			return $return;
		}
		$person = emerson_serving_person();
		if ( ! $person ) {
			return $return;
		}
		$shown  = emerson_serving_form_dates();
		$ticked = array_intersect( $shown, array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['can_serve'] ?? array() ) ) );
		$_POST['dates'] = array_values( array_diff( $shown, $ticked ) );
		foreach ( $shown as $date ) {
			$wpdb->replace(
				emerson_serving_table(),
				array(
					'people_id'  => (int) $person->people_id,
					'serve_date' => $date,
					'can_serve'  => in_array( $date, $ticked, true ) ? 1 : 0,
					'answered'   => current_time( 'mysql' ),
				)
			);
		}
		$GLOBALS['emerson_serving_saved_for'] = (int) $person->people_id;
		return $return;
	},
	10,
	3
);

// Turn Church Admin's "dates I can't serve" form into "dates I can serve", list the answers, and return to the section.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		global $wpdb;
		if ( 'church_admin' !== $tag || ! is_string( $output ) || false === strpos( $output, 'name="not-available"' ) || ! emerson_serving_ready() ) {
			return $output;
		}
		$person = emerson_serving_person();
		if ( ! $person ) {
			return $output;
		}
		// Church Admin's save cleared the person's dates beyond the form; restore them from the answers.
		if ( ( $GLOBALS['emerson_serving_saved_for'] ?? 0 ) === (int) $person->people_id ) {
			emerson_serving_sync( (int) $person->people_id );
		}

		$can_serve = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT serve_date FROM ' . emerson_serving_table() . ' WHERE people_id = %d AND can_serve = 1 AND serve_date >= %s ORDER BY serve_date',
				(int) $person->people_id,
				current_time( 'Y-m-d' )
			)
		);
		$name = esc_html( emerson_account_name( null, $person ) );
		$own  = (int) $person->user_id === get_current_user_id();

		$output = str_replace(
			'<form action="" method="POST">',
			'<form action="' . esc_url( get_permalink() . '#dates-unavailable' ) . '" method="POST">',
			$output
		);
		$output = str_replace(
			array( '<h2>Non availability</h2>', '<h2>Unavailable dates saved</h2>', '<h3>Please choose dates you are NOT available to serve on service schedules</h3>' ),
			array( '<h2>Serving dates</h2>', '<h2>Your serving dates are saved</h2>', '<h3>Tick the dates you can serve</h3>' ),
			$output
		);
		if ( '' !== $name ) {
			$output = preg_replace( '#<h3>Set non availability for (.*?)</h3>#', '<h3>Tick the dates ' . $name . ' can serve</h3>', $output );
		} else {
			$output = preg_replace( '#<h3>Set non availability for (.*?)</h3>#', '<h3>Tick the dates $1 can serve</h3>', $output );
		}

		$output = preg_replace_callback(
			'#<input type="checkbox" name="dates\[\]"\s*(?:checked="checked"\s*)?value="(\d{4}-\d{2}-\d{2})" /> <label>#',
			static function ( array $m ) use ( $can_serve ): string {
				$id = 'can-serve-' . $m[1];
				return sprintf(
					'<input type="checkbox" name="can_serve[]" id="%1$s" value="%2$s"%3$s /> <label for="%1$s">',
					esc_attr( $id ),
					esc_attr( $m[1] ),
					in_array( $m[1], $can_serve, true ) ? ' checked="checked"' : ''
				);
			},
			$output
		);

		$bulk = '<p class="emerson-serve-bulk">'
			. '<button type="button" onclick="this.form.querySelectorAll(\'input[name=&quot;can_serve[]&quot;]\').forEach(function(b){b.checked=true;})">Mark all</button> '
			. '<button type="button" onclick="this.form.querySelectorAll(\'input[name=&quot;can_serve[]&quot;]\').forEach(function(b){b.checked=false;})">Clear all</button>'
			. '</p>';
		$output = preg_replace( '#(<div class="church-admin-form-group"><input type="checkbox" name="can_serve\[\]")#', $bulk . '$1', $output, 1 );

		$shown     = emerson_serving_form_dates();
		$available = array_values( array_intersect( $shown, $can_serve ) );
		$can       = count( $available );
		if ( $can ) {
			$who     = $own ? 'You' : $name;
			$summary = sprintf( '%1$s can serve <strong>%2$d of the %3$d dates</strong> listed.', $who, $can, count( $shown ) );
			$summary .= ' Available: <strong>' . esc_html( implode( ', ', array_map( static fn( $d ) => mysql2date( get_option( 'date_format' ), $d ), $available ) ) ) . '</strong>.';
		} else {
			$summary = $own
				? 'You haven&#8217;t ticked any dates yet. Until you do, you won&#8217;t be put on the serving schedule.'
				: "{$name} hasn&#8217;t ticked any dates yet, so they won&#8217;t be put on the serving schedule.";
		}
		return preg_replace( '#(<h3>Tick the dates)#', '<p class="emerson-unavailable-summary">' . $summary . '</p>$1', $output, 1 );
	},
	12,
	2
);
