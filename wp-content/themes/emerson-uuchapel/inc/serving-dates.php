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

const EMERSON_SERVING_DB_VERSION = '2';
// Church Admin's auto-fill offers up to 6 months ahead.
const EMERSON_SERVING_MONTHS = 6;

function emerson_serving_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'emerson_serving_answers';
}

function emerson_serving_roles_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'emerson_serving_roles';
}

/**
 * Positions a member can offer for a date. RE class topic is a subject, not a job.
 *
 * @return array<string,string>
 */
function emerson_serving_role_choices(): array {
	$roles = function_exists( 'emerson_schedule_roster_labels' )
		? emerson_schedule_roster_labels()
		: array(
			'liturgist'     => 'Liturgist',
			'tech'          => 'Tech',
			'greeter'       => 'Greeter',
			'hospitality'   => 'Hospitality',
			'board'         => 'Board person of the day',
			're_volunteers' => 'RE volunteers',
		);
	unset( $roles['re_topic'] );
	return $roles;
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
		$roles = emerson_serving_roles_table();
		dbDelta(
			"CREATE TABLE {$roles} (
  people_id int(11) NOT NULL,
  serve_date date NOT NULL,
  role_key varchar(32) NOT NULL,
  PRIMARY KEY  (people_id,serve_date,role_key)
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

/** The directory row for the person who is logged in, ignoring "Choose person". */
function emerson_serving_logged_in_person(): ?object {
	global $wpdb;
	if ( ! is_user_logged_in() ) {
		return null;
	}
	$person = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}church_admin_people WHERE user_id = %d",
			get_current_user_id()
		)
	);
	return $person ?: null;
}

/**
 * "You can serve N of the M dates listed" for one person.
 * $own is the logged-in member. An admin editing someone else still gets "You" for their own list.
 */
function emerson_serving_summary_html( object $person, bool $own ): string {
	global $wpdb;
	$can_serve = $wpdb->get_col(
		$wpdb->prepare(
			'SELECT serve_date FROM ' . emerson_serving_table() . ' WHERE people_id = %d AND can_serve = 1 AND serve_date >= %s ORDER BY serve_date',
			(int) $person->people_id,
			current_time( 'Y-m-d' )
		)
	);
	$shown     = emerson_serving_form_dates();
	$available = array_values( array_intersect( $shown, $can_serve ) );
	$name      = esc_html( emerson_account_name( null, $person ) );
	if ( $available ) {
		$who     = $own ? 'You' : $name;
		$summary = sprintf( '%1$s can serve <strong>%2$d of the %3$d dates</strong> listed.', $who, count( $available ), count( $shown ) );
		$summary .= ' Available: <strong>' . esc_html( implode( ', ', array_map( static fn( $d ) => mysql2date( get_option( 'date_format' ), $d ), $available ) ) ) . '</strong>.';
	} else {
		$summary = $own
			? 'You haven&#8217;t ticked any dates yet. Until you do, you won&#8217;t be put on the serving schedule.'
			: "{$name} hasn&#8217;t ticked any dates yet, so they won&#8217;t be put on the serving schedule.";
	}
	return '<p class="emerson-unavailable-summary">' . $summary . '</p>';
}

/**
 * Positions already saved for one person, keyed by date.
 *
 * @return array<string, list<string>>
 */
function emerson_serving_saved_roles( int $people_id ): array {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			'SELECT serve_date, role_key FROM ' . emerson_serving_roles_table() . ' WHERE people_id = %d',
			$people_id
		)
	);
	$out = array();
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $row ) {
		$out[ substr( (string) $row->serve_date, 0, 10 ) ][] = (string) $row->role_key;
	}
	return $out;
}

/**
 * Replace the positions for the dates on the form. Unticked dates keep none.
 *
 * @param list<string> $shown
 * @param list<string> $ticked
 */
function emerson_serving_save_roles( int $people_id, array $shown, array $ticked ): void {
	global $wpdb;
	if ( ! $shown ) {
		return;
	}
	$allowed = array_keys( emerson_serving_role_choices() );
	$table   = emerson_serving_roles_table();
	$marks   = implode( ', ', array_fill( 0, count( $shown ), '%s' ) );
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$table} WHERE people_id = %d AND serve_date IN ({$marks})",
			array_merge( array( $people_id ), $shown )
		)
	);
	$posted = isset( $_POST['serve_roles'] ) && is_array( $_POST['serve_roles'] ) ? wp_unslash( $_POST['serve_roles'] ) : array();
	foreach ( $ticked as $date ) {
		$chosen = isset( $posted[ $date ] ) ? (array) $posted[ $date ] : array();
		foreach ( array_unique( array_map( 'sanitize_key', $chosen ) ) as $key ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'people_id'  => $people_id,
					'serve_date' => $date,
					'role_key'   => $key,
				),
				array( '%d', '%s', '%s' )
			);
		}
	}
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
		emerson_serving_save_roles( (int) $person->people_id, $shown, $ticked );
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
		$saved_roles = emerson_serving_saved_roles( (int) $person->people_id );
		$name = esc_html( emerson_account_name( null, $person ) );

		$output = str_replace(
			'<form action="" method="POST">',
			'<form action="' . esc_url( get_permalink() . '#dates-unavailable' ) . '" method="POST">',
			$output
		);
		$output = str_replace(
			array( '<h2>Non availability</h2>', '<h2>Unavailable dates saved</h2>', '<h3>Please choose dates you are NOT available to serve on service schedules</h3>' ),
			array( '<h2 id="dates-unavailable">Serving dates</h2>', '<h2 id="dates-unavailable">Your serving dates are saved</h2>', '<h3>Tick the dates you can serve</h3>' ),
			$output
		);
		if ( '' !== $name ) {
			$output = preg_replace( '#<h3>Set non availability for (.*?)</h3>#', '<h3>Tick the dates ' . $name . ' can serve</h3>', $output );
		} else {
			$output = preg_replace( '#<h3>Set non availability for (.*?)</h3>#', '<h3>Tick the dates $1 can serve</h3>', $output );
		}

		$directory = $wpdb->get_results( 'SELECT people_id, first_name, last_name, user_id FROM ' . $wpdb->prefix . 'church_admin_people' );
		$labels    = array();
		foreach ( $directory as $row ) {
			$label = emerson_account_name( null, $row );
			if ( '' !== $label ) {
				$labels[ (int) $row->people_id ] = $label;
			}
		}
		$output = preg_replace_callback(
			'#(<select name="people_id"[^>]*>)(.*?)(</select>)#s',
			static function ( array $select ) use ( $labels ): string {
				$inner = preg_replace_callback(
					'#<option value="(\d+)"([^>]*)>(.*?)</option>#',
					static function ( array $opt ) use ( $labels ): string {
						$id    = (int) $opt[1];
						$label = $labels[ $id ] ?? html_entity_decode( $opt[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
						return '<option value="' . $id . '"' . $opt[2] . '>' . esc_html( $label ) . '</option>';
					},
					$select[2]
				);
				return $select[1] . $inner . $select[3];
			},
			$output
		);

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
		if ( function_exists( 'emerson_sunday_exception' ) ) {
			foreach ( emerson_serving_form_dates() as $serve_date ) {
				$ex = emerson_sunday_exception( $serve_date );
				if ( ! is_array( $ex ) ) {
					continue;
				}
				$mark = 'cancelled' === ( $ex['status'] ?? '' ) ? 'service cancelled' : ( 'moved' === ( $ex['status'] ?? '' ) ? 'moved' : '' );
				if ( '' === $mark ) {
					continue;
				}
				$output = preg_replace(
					'#(id="can-serve-' . preg_quote( $serve_date, '#' ) . '"[^>]*>.*?</label>)#s',
					'$1 <em>(' . $mark . ')</em>',
					$output,
					1
				);
			}
		}

		$choices = emerson_serving_role_choices();
		$output  = preg_replace_callback(
			'#(<div class="church-admin-form-group"><input type="checkbox" name="can_serve\[\]" id="can-serve-(\d{4}-\d{2}-\d{2})"[^>]*> <label for="can-serve-\2">.*?</label>)#s',
			static function ( array $match ) use ( $choices, $saved_roles, $can_serve ): string {
				$date = $match[2];
				$open = in_array( $date, $can_serve, true );
				$picked = $saved_roles[ $date ] ?? array();
				$roles  = '<div class="emerson-serve-roles"' . ( $open ? '' : ' hidden' ) . '>';
				foreach ( $choices as $key => $label ) {
					$id = 'serve-role-' . $date . '-' . $key;
					$roles .= '<label class="emerson-serve-roles__item" for="' . esc_attr( $id ) . '">';
					$roles .= '<input type="checkbox" name="serve_roles[' . esc_attr( $date ) . '][]" id="' . esc_attr( $id ) . '" value="' . esc_attr( $key ) . '"';
					if ( in_array( $key, $picked, true ) ) {
						$roles .= ' checked="checked"';
					}
					if ( ! $open ) {
						$roles .= ' disabled="disabled"';
					}
					$roles .= '> ' . esc_html( $label ) . '</label>';
				}
				$roles .= '</div>';
				return $match[1] . $roles;
			},
			$output
		);

		$bulk = '<p class="emerson-serve-bulk">'
			. '<button type="button" data-emerson-serve-all="mark">Mark all</button> '
			. '<button type="button" data-emerson-serve-all="clear">Clear all</button>'
			. '</p>';
		$output = preg_replace( '#(<div class="church-admin-form-group"><input type="checkbox" name="can_serve\[\]")#', $bulk . '$1', $output, 1 );

		return $output;
	},
	12,
	2
);

// The logged-in person's dates replace "My schedule". Choosing another person does not change this list.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'church_admin' !== $tag || ! is_string( $output ) || ! str_contains( $output, 'church-admin-my-rota' ) ) {
			return $output;
		}
		$person = emerson_serving_logged_in_person();
		if ( ! $person ) {
			return $output;
		}
		$summary = emerson_serving_summary_html( $person, true );
		$updated = preg_replace( '#<h2>\s*My schedule\s*</h2>#', $summary, $output, 1, $count );
		return $count ? $updated : $output;
	},
	13,
	2
);

/**
 * Church Admin → Serving sign-ups. Offers from the Members page, not rota assignments.
 */
function emerson_serving_signups_page(): void {
	if ( ! function_exists( 'emerson_schedule_can_view_roster' ) || ! emerson_schedule_can_view_roster() ) {
		wp_die( esc_html__( 'You do not have permission to view serving sign-ups.' ) );
	}
	$from  = current_time( 'Y-m-d' );
	$until = gmdate( 'Y-m-d', strtotime( $from . ' +' . EMERSON_SERVING_MONTHS . ' months' ) );
	$rows  = function_exists( 'emerson_schedule_service_requests' ) ? emerson_schedule_service_requests( $from, $until ) : array();
	ksort( $rows );

	echo '<div class="wrap"><h1>Serving sign-ups</h1>';
	echo '<p>People who ticked a Sunday on the Members page, and any positions they offered. This is not the rota. Assign someone on <strong>Church Admin → Schedule</strong>.</p>';
	if ( ! $rows ) {
		echo '<p>No one has signed up for an upcoming Sunday yet.</p></div>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>Sunday</th><th>Signed up</th></tr></thead><tbody>';
	foreach ( $rows as $ymd => $people ) {
		echo '<tr><td><strong>' . esc_html( mysql2date( 'l, F j, Y', $ymd ) ) . '</strong></td><td>';
		$lines = array();
		foreach ( $people as $person ) {
			$roles = $person['roles'] ?? array();
			$lines[] = esc_html( $person['name'] ) . ( $roles ? ': ' . esc_html( implode( ', ', $roles ) ) : ': No position chosen' );
		}
		echo implode( '<br>', $lines );
		echo '</td></tr>';
	}
	echo '</tbody></table></div>';
}

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'premium_church_admin',
			'Serving sign-ups',
			'Serving sign-ups',
			'read',
			'emerson-serving-signups',
			'emerson_serving_signups_page'
		);
	},
	20
);

add_action(
	'admin_menu',
	static function (): void {
		if ( function_exists( 'emerson_schedule_can_view_roster' ) && emerson_schedule_can_view_roster() ) {
			return;
		}
		remove_submenu_page( 'premium_church_admin', 'emerson-serving-signups' );
	},
	99
);

add_action(
	'admin_bar_menu',
	static function ( $bar ): void {
		if ( ! $bar instanceof WP_Admin_Bar || ! function_exists( 'emerson_schedule_can_view_roster' ) || ! emerson_schedule_can_view_roster() ) {
			return;
		}
		$bar->add_node(
			array(
				'parent' => 'church_admin',
				'id'     => 'emerson-serving-signups',
				'title'  => 'Serving sign-ups',
				'href'   => admin_url( 'admin.php?page=emerson-serving-signups' ),
			)
		);
	},
	100
);

add_action(
	'admin_notices',
	static function (): void {
		if ( ! is_admin() || ! function_exists( 'emerson_schedule_can_view_roster' ) || ! emerson_schedule_can_view_roster() ) {
			return;
		}
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'premium_church_admin' !== $page || ! empty( $_GET['section'] ) || ! empty( $_GET['task'] ) ) {
			return;
		}
		$url = admin_url( 'admin.php?page=emerson-serving-signups' );
		echo '<div class="notice notice-info"><p>Who has signed up to serve is not in Calendar or Schedule. Open <a href="' . esc_url( $url ) . '">Serving sign-ups</a>.</p></div>';
	}
);

// "Dates I can serve" is the same list, so the heading goes. The form below keeps #dates-unavailable.
add_filter(
	'the_content',
	static function ( $content ) {
		if ( ! is_string( $content ) || ( ! is_page( 32 ) && ! is_page( 'members' ) ) ) {
			return $content;
		}
		return (string) preg_replace( '#<h4[^>]*\bid="dates-unavailable"[^>]*>.*?</h4>#s', '', $content );
	},
	9
);
