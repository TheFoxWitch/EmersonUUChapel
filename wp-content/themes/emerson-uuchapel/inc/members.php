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
			esc_html( $user->display_name ),
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
