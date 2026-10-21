<?php
/**
 * Cookie notice and the pages it links to.
 *
 * Login cookies are required for member pages. The homepage newsletter invitation
 * remembers "closed" or "subscribed" in local storage. There are no advertising cookies.
 */

declare( strict_types=1 );

add_action( 'wp_footer', 'emerson_cookie_notice' );

function emerson_cookie_notice(): void {
	if ( is_admin() ) {
		return;
	}
	$privacy = get_privacy_policy_url();
	if ( ! is_string( $privacy ) || $privacy === '' ) {
		$privacy = home_url( '/privacy-policy/' );
	}
	$cookies = home_url( '/cookies/' );
	$terms   = home_url( '/terms-of-use/' );
	?>
	<div class="emerson-cookie-notice" hidden>
		<h2>How Emerson Chapel uses cookies</h2>
		<p>
			We use cookies and similar storage to run this site: to keep you signed in, to protect the login, and to remember if you closed the newsletter invitation. We do not use advertising cookies. By using this site, you agree to this, as well as our
			<a href="<?php echo esc_url( $privacy ); ?>">Privacy Policy</a>,
			<a href="<?php echo esc_url( $cookies ); ?>">Cookies</a>, and
			<a href="<?php echo esc_url( $terms ); ?>">Terms of Use</a>.
			Manage your preferences <a href="<?php echo esc_url( $cookies . '#preferences' ); ?>">here</a>.
		</p>
		<button type="button" class="emerson-cookie-notice__ok">Okay</button>
	</div>
	<?php
}
