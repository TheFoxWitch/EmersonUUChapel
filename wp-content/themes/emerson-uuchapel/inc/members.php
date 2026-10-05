<?php
/**
 * Members area on the Members page (32).
 *
 * - [emerson_visitors_only]…[/emerson_visitors_only]: shown only when logged out (login and registration).
 * - [emerson_members_only]…[/emerson_members_only]: shown only when logged in (the members' hub).
 * - [emerson_member_links]: "Signed in as … · Edit my profile · Log out".
 * Both enclosing shortcodes may span several blocks; their contents can hold other shortcodes.
 */

declare( strict_types=1 );

const EMERSON_MEMBERS_PAGE = '/members/';

/**
 * A readable name for the signed-in account or a directory person: WordPress first + last, then display name, then the directory row.
 */
function emerson_account_name( ?WP_User $user = null, ?object $person = null ): string {
	if ( ! $user && $person && ! empty( $person->user_id ) ) {
		$found = get_userdata( (int) $person->user_id );
		$user  = $found ?: null;
	}
	$tidy = static function ( string $part ): string {
		return trim( (string) preg_replace( '/\s*\(admin\)\s*/i', ' ', $part ) );
	};
	if ( $user instanceof WP_User ) {
		$from_account = trim( $tidy( (string) $user->first_name ) . ' ' . $tidy( (string) $user->last_name ) );
		if ( '' !== $from_account ) {
			return $from_account;
		}
		$display = $tidy( (string) $user->display_name );
		if ( '' !== $display && ! str_contains( $display, '@' ) ) {
			return $display;
		}
	}
	if ( $person ) {
		return trim( $tidy( (string) ( $person->first_name ?? '' ) ) . ' ' . $tidy( (string) ( $person->last_name ?? '' ) ) );
	}
	return '';
}

add_shortcode(
	'emerson_visitors_only',
	static function ( $atts, ?string $content = null ): string {
		if ( is_user_logged_in() ) {
			return '';
		}
		// The login form carries a nonce that expires, so a cached copy would eventually refuse every login.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		return do_shortcode( (string) $content );
	}
);

add_shortcode(
	'emerson_members_only',
	static function ( $atts, ?string $content = null ): string {
		return is_user_logged_in() ? do_shortcode( (string) $content ) : '';
	}
);

/**
 * Whether Church Admin → Settings → Permissions lists this user for any area (Directory, Rota, Giving…).
 * Those users are often plain subscribers who work in Church Admin inside wp-admin.
 */
function emerson_has_church_admin_permission( int $user_id ): bool {
	foreach ( (array) get_option( 'church_admin_user_permissions', array() ) as $ids ) {
		if ( in_array( $user_id, array_map( 'intval', (array) maybe_unserialize( $ids ) ), true ) ) {
			return true;
		}
	}
	return false;
}

add_shortcode(
	'emerson_member_links',
	static function (): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user  = wp_get_current_user();
		$label = emerson_account_name( $user );
		$links = array(
			sprintf( '<a href="%s">Edit my profile or password</a>', esc_url( home_url( '/edit-profile/' ) ) ),
		);
		if ( current_user_can( 'edit_posts' ) ) {
			$links[] = sprintf( '<a href="%s">WordPress dashboard</a>', esc_url( admin_url() ) );
		}
		if ( current_user_can( 'manage_options' ) || emerson_has_church_admin_permission( $user->ID ) ) {
			$links[] = sprintf( '<a href="%s">Church Admin</a>', esc_url( admin_url( 'admin.php?page=premium_church_admin' ) ) );
		}
		$links[] = sprintf( '<a href="%s">Log out</a>', esc_url( wp_logout_url( home_url( '/' ) ) ) );

		return sprintf(
			'<p class="emerson-member-links">Signed in as <strong>%1$s</strong> · %2$s</p>',
			esc_html( $label !== '' ? $label : $user->display_name ),
			implode( ' · ', $links )
		);
	}
);

// Church Admin 5.7's [church_admin type="my-rota"] ends with an extra </div> when the login isn't linked to a
// directory entry, which closes the page's content column early. Drop any closing tags that have no opening tag.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		return 'church_admin' === $tag && is_string( $output ) ? force_balance_tags( $output ) : $output;
	},
	10,
	2
);

// "Dates I can't serve" Save button: give it the theme's button-primary-calendar-save class (styled in custom.css).
// Church Admin prints it as a plain class="button", right after its hidden "not-available" field.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'church_admin' !== $tag || ! is_string( $output ) ) {
			return $output;
		}
		return str_replace(
			'<input type="hidden" name="not-available" value="yes" /><input type="submit" class="button"',
			'<input type="hidden" name="not-available" value="yes" /><input type="submit" class="button button-primary-calendar-save"',
			$output
		);
	},
	11,
	2
);

// Logins made straight in WordPress (rather than through the Members page sign-up) have no Church Admin directory
// entry, so serving features stop with a terse plugin message. Tell the member who can fix it.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'church_admin' !== $tag || ! is_string( $output ) || false === strpos( $output, 'Your login is not connected to a directory entry' ) ) {
			return $output;
		}
		$message = 'Your login isn&#8217;t linked to a directory entry yet, so serving dates can&#8217;t be shown. Contact the office at <a href="mailto:office@emersonuuchapel.org">office@emersonuuchapel.org</a> and they&#8217;ll connect it.';
		$output  = str_replace( '<p>Your login is not connected to a directory entry</p>', '<p class="emerson-unlinked-login">' . $message . '</p>', $output );
		return str_replace( 'Your login is not connected to a directory entry', '<p class="emerson-unlinked-login">' . $message . '</p>', $output );
	},
	12,
	2
);

// Member directory search box: "Member Name" placeholder and a real accessible name (placeholders aren't labels).
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'church_admin' !== $tag || ! is_string( $output ) || false === strpos( $output, 'ca-search-field' ) ) {
			return $output;
		}
		$output = str_replace(
			'class="ca-search-field" type="text" placeholder="Search"',
			'class="ca-search-field" type="text" placeholder="Member Name" aria-label="Search the member directory by name"',
			$output
		);

		// Searching reloads the page; bring the visitor back down to the directory instead of the top.
		$directory = get_permalink() . '#member-directory';
		$output    = str_replace(
			'<form name="church_admin_search" action="' . esc_url( get_permalink() ) . '"',
			'<form name="church_admin_search" action="' . esc_url( $directory ) . '"',
			$output
		);

		// After a search, offer a way back to the full list (Church Admin shows only the results or "not found").
		// The search page's URL already ends in #member-directory, so the link needs a query string or the
		// browser treats it as a jump within the same page and never reloads.
		if ( ! empty( $_POST['church_admin_search'] ) ) {
			$full   = add_query_arg( 'directory', 'all', get_permalink() ) . '#member-directory';
			$reset  = sprintf( '<p class="emerson-directory-reset"><a href="%s">Show the full directory</a></p>', esc_url( $full ) );
			$output = preg_replace( '#(<h2>Your search for .*?</h2>|<p>&quot;.*?&quot; not found</p>|<p>".*?" not found</p>)#s', '$1' . $reset, $output, 1 );
		}
		return $output;
	},
	11,
	2
);

// Church Admin loads Google Maps (for the registration form's address map) even when no API key is set,
// which only produces "no API key" console warnings and an unnecessary request to Google. Skip it until
// a key is entered in Church Admin → Settings; it then loads again automatically.
add_filter(
	'script_loader_tag',
	static function ( $tag, $handle ) {
		if ( 'church_admin_premium_google_maps_api' === $handle && '' === trim( (string) get_option( 'church_admin_google_api_key' ) ) ) {
			return '';
		}
		return $tag;
	},
	10,
	2
);
add_filter(
	'wp_resource_hints',
	static function ( $urls, $relation ) {
		if ( 'dns-prefetch' !== $relation || '' !== trim( (string) get_option( 'church_admin_google_api_key' ) ) ) {
			return $urls;
		}
		return array_values(
			array_filter(
				$urls,
				static fn( $url ) => false === strpos( is_array( $url ) ? (string) ( $url['href'] ?? '' ) : (string) $url, 'maps.googleapis.com' )
			)
		);
	},
	10,
	2
);

// Edit Profile: a link back to the members' area beside "Update". It reads "Cancel" until Profile Builder
// reports a successful save, then "Return"; editing again after a save switches it back to "Cancel".
add_action(
	'wppb_edit_profile_success',
	static function (): void {
		$GLOBALS['emerson_profile_saved'] = true;
	}
);

add_action(
	'wppb_form_after_submit_button',
	static function ( $args ): void {
		if ( 'edit_profile' !== ( $args['form_type'] ?? '' ) ) {
			return;
		}
		$saved = ! empty( $GLOBALS['emerson_profile_saved'] );
		printf(
			'<a class="emerson-profile-back" href="%1$s" data-saved="%2$d">%3$s</a>',
			esc_url( home_url( EMERSON_MEMBERS_PAGE ) ),
			$saved ? 1 : 0,
			$saved ? 'Return' : 'Cancel'
		);
		if ( $saved ) {
			?>
			<script>
			( function () {
				var back = document.querySelector( '.emerson-profile-back[data-saved="1"]' );
				var form = back && back.closest( 'form' );
				if ( ! form ) {
					return;
				}
				form.addEventListener( 'input', function () {
					back.textContent = 'Cancel';
				}, { once: true } );
			}() );
			</script>
			<?php
		}
	}
);

// Church Admin 5.7 appends this sentence to the "user login created" email with a doubled "at at".
add_filter(
	'gettext',
	static function ( $translation, $text, $domain ) {
		if ( 'church-admin' === $domain && 'You can login and view your address details and privacy settings at at %1$s.' === $text ) {
			return '<p>You can log in, see the members’ area and update your address details and privacy settings at %1$s.</p>';
		}
		return $translation;
	},
	10,
	3
);

// Church Admin's register form starts with "Register/Login". Members already has "New to Emerson? Register" above it.
add_filter(
	'do_shortcode_tag',
	static function ( $output, $tag ) {
		if ( 'church_admin_register' !== $tag || ! is_string( $output ) ) {
			return $output;
		}
		return (string) preg_replace( '#<h2>\s*Register/Login\s*</h2>#', '', $output, 1 );
	},
	12,
	2
);

// Members who log in through wp-login.php land on the members' hub instead of their wp-admin profile.
add_filter(
	'login_redirect',
	static function ( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User || user_can( $user, 'edit_posts' ) ) {
			return $redirect_to;
		}
		$requested = (string) $requested;
		if ( '' === $requested || false !== strpos( $requested, '/wp-admin' ) ) {
			return home_url( EMERSON_MEMBERS_PAGE );
		}
		return $redirect_to;
	},
	20,
	3
);

// Old login pages now send visitors to the Members page.
add_action(
	'template_redirect',
	static function (): void {
		if ( ! is_404() && ! is_page( array( 'member-login', 'member-home', 'login', 'log-in', 'edit-profile-2' ) ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
		if ( in_array( $path, array( 'member-login', 'member-home', 'login', 'log-in' ), true ) ) {
			wp_safe_redirect( home_url( EMERSON_MEMBERS_PAGE ), 301 );
			exit;
		}
		if ( 'edit-profile-2' === $path ) {
			wp_safe_redirect( home_url( '/edit-profile/' ), 301 );
			exit;
		}
	}
);
