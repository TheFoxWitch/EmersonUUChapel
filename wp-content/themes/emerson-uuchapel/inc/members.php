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

add_shortcode(
	'emerson_member_links',
	static function (): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}
		$user = wp_get_current_user();
		return sprintf(
			'<p class="emerson-member-links">Signed in as <strong>%1$s</strong> · <a href="%2$s">Edit my profile or password</a> · <a href="%3$s">Log out</a></p>',
			esc_html( $user->display_name ),
			esc_url( home_url( '/edit-profile/' ) ),
			esc_url( wp_logout_url( home_url( '/' ) ) )
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
