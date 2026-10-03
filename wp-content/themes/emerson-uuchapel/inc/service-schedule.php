<?php
/**
 * [emerson_service_schedule]: a month calendar of Sunday services on the Service Schedule page (437).
 *
 * Services come from Church Admin's calendar (category "Sunday Service"), so the office keeps them in one place.
 * That is a different table from the rota, which is who is assigned to greeter, liturgist, and so on. The rota
 * has no future rows, which is why Church Admin's own [church_admin type="rota"] only said "No schedule for this month".
 *
 * Logged-in members see a mark on Sundays they ticked on "Dates I can serve" (emerson_serving_answers).
 * If they are later assigned a job on the rota, that job name is shown instead.
 * Month navigation is ?month=YYYY-MM, so it works without JavaScript and each month has its own address.
 */

declare( strict_types=1 );

// Shown as the location on every service. The link goes to Visit Us, which holds the address and map.
// Change the Visit Us page when the church confirms the address (checklist, section 4); this follows it.
const EMERSON_SERVICE_PLACE = 'Emerson Chapel';

add_shortcode(
	'emerson_service_schedule',
	static function (): string {
		global $wpdb, $wp_locale;
		if ( ! function_exists( 'church_admin_premium_level_check' ) ) {
			return '';
		}

		$asked = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
		$first = preg_match( '/^(\d{4})-(0[1-9]|1[0-2])$/', $asked ) ? $asked . '-01' : current_time( 'Y-m' ) . '-01';
		$noon  = strtotime( $first . ' 12:00:00' );
		$days  = (int) gmdate( 't', $noon );
		$start = (int) gmdate( 'w', $noon );
		$last  = gmdate( 'Y-m-', $noon ) . sprintf( '%02d', $days );

		$services = array();
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.start_date, a.start_time, a.end_time, a.title, a.location
				FROM {$wpdb->prefix}church_admin_calendar_date a
				JOIN {$wpdb->prefix}church_admin_calendar_category b ON a.cat_id = b.cat_id
				WHERE b.category = 'Sunday Service' AND a.start_date BETWEEN %s AND %s
				ORDER BY a.start_date, a.start_time",
				$first,
				$last
			)
		);
		foreach ( $rows as $row ) {
			$services[ (int) substr( $row->start_date, 8, 2 ) ][] = $row;
		}

		// Days this member ticked as available, and any jobs the office has assigned them.
		$available = array();
		$jobs      = array();
		if ( is_user_logged_in() ) {
			$person = $wpdb->get_var( $wpdb->prepare( "SELECT people_id FROM {$wpdb->prefix}church_admin_people WHERE user_id = %d", get_current_user_id() ) );
			if ( $person ) {
				if ( function_exists( 'emerson_serving_table' ) ) {
					$ticked = $wpdb->get_col(
						$wpdb->prepare(
							'SELECT serve_date FROM ' . emerson_serving_table() . ' WHERE people_id = %d AND can_serve = 1 AND serve_date BETWEEN %s AND %s',
							(int) $person,
							$first,
							$last
						)
					);
					foreach ( $ticked as $day ) {
						$available[ (int) substr( $day, 8, 2 ) ] = true;
					}
				}
				$assigned = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DATE(r.rota_date) AS day, s.rota_task FROM {$wpdb->prefix}church_admin_new_rota r
						JOIN {$wpdb->prefix}church_admin_rota_settings s ON s.rota_id = r.rota_task_id
						WHERE r.people_id = %d AND DATE(r.rota_date) BETWEEN %s AND %s ORDER BY r.rota_date",
						(int) $person,
						$first,
						$last
					)
				);
				foreach ( $assigned as $job ) {
					$jobs[ (int) substr( $job->day, 8, 2 ) ][] = trim( $job->rota_task );
				}
			}
		}

		$marked_available = false;
		foreach ( $available as $day => $_ ) {
			if ( isset( $services[ $day ] ) ) {
				$marked_available = true;
				break;
			}
		}

		$link     = static fn( string $month ): string => esc_url( add_query_arg( 'month', $month, get_permalink() ) . '#emerson-schedule' );
		$prev     = gmdate( 'Y-m', strtotime( $first . ' -1 month' ) );
		$next     = gmdate( 'Y-m', strtotime( $first . ' +1 month' ) );
		$time     = static fn( string $t ): string => esc_html( mysql2date( get_option( 'time_format' ), $t ) );
		$place    = esc_url( home_url( '/visit-us/' ) );
		$place_el = '<a href="' . $place . '">' . esc_html( EMERSON_SERVICE_PLACE ) . '</a>';

		$out  = '<div class="emerson-schedule" id="emerson-schedule">';
		$out .= '<p class="emerson-schedule__nav">'
			. '<a class="emerson-schedule__arrow" href="' . $link( $prev ) . '" aria-label="Previous month">&larr;</a>'
			. '<span class="emerson-schedule__month">' . esc_html( date_i18n( 'F Y', $noon ) ) . '</span>'
			. '<a class="emerson-schedule__arrow" href="' . $link( $next ) . '" aria-label="Next month">&rarr;</a></p>';
		if ( $jobs ) {
			$out .= '<p class="emerson-schedule__legend"><span class="emerson-schedule__serving">You&#8217;re serving</span> marks a Sunday you&#8217;re on the schedule.</p>';
		} elseif ( $marked_available ) {
			$out .= '<p class="emerson-schedule__legend"><span class="emerson-schedule__available">You can serve</span> marks the Sundays you&#8217;ve said you can help.</p>';
		}

		$out .= '<table class="emerson-schedule__grid"><thead><tr>';
		foreach ( range( 0, 6 ) as $d ) {
			$out .= '<th scope="col"><abbr title="' . esc_attr( $wp_locale->get_weekday( $d ) ) . '">' . esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $d ) ) ) . '</abbr></th>';
		}
		$out .= '</tr></thead><tbody><tr>';

		$cells = (int) ceil( ( $start + $days ) / 7 ) * 7;
		for ( $cell = 0; $cell < $cells; $cell++ ) {
			if ( $cell && 0 === $cell % 7 ) {
				$out .= '</tr><tr>';
			}
			$day = $cell - $start + 1;
			if ( $day < 1 || $day > $days ) {
				$out .= '<td class="emerson-schedule__empty"></td>';
				continue;
			}
			$has     = isset( $services[ $day ] );
			$serving = $has && ( isset( $available[ $day ] ) || isset( $jobs[ $day ] ) );
			$class   = trim( ( $has ? 'has-service' : '' ) . ( $serving ? ' is-serving' : '' ) );
			$out    .= '<td' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '><span class="emerson-schedule__day">'
				. '<span class="emerson-schedule__weekday">' . esc_html( date_i18n( 'l, ', strtotime( sprintf( '%s%02d 12:00:00', substr( $first, 0, 8 ), $day ) ) ) ) . '</span>'
				. $day . '</span>';
			foreach ( $services[ $day ] ?? array() as $service ) {
				$out .= '<span class="emerson-schedule__service"><strong>' . $time( $service->start_time ) . '</strong> ' . esc_html( $service->title )
					. '<br>' . $place_el . '</span>';
			}
			if ( isset( $jobs[ $day ] ) ) {
				foreach ( $jobs[ $day ] as $job ) {
					$out .= '<span class="emerson-schedule__serving">You&#8217;re serving: ' . esc_html( $job ) . '</span>';
				}
			} elseif ( $has && isset( $available[ $day ] ) ) {
				$out .= '<span class="emerson-schedule__available">You can serve</span>';
			}
			$out .= '</td>';
		}
		$out .= '</tr></tbody></table>';
		if ( ! $services ) {
			$out .= '<p class="emerson-schedule__none">No services are on the calendar for this month yet.</p>';
		}
		return $out . '</div>';
	}
);

// Church Admin's own serving table says only "No schedule for this month" while the office isn't using it; hide it then.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag, $attr ) {
		if ( 'church_admin' === $tag && 'rota' === ( $attr['type'] ?? '' ) && is_string( $output ) && false !== strpos( $output, 'No schedule for this month' ) ) {
			return '';
		}
		return $output;
	},
	10,
	3
);
