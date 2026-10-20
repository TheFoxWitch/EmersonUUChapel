<?php
/**
 * Title: Site footer
 * Slug: emerson-uuchapel/footer
 * Inserter: no
 */

$privacy_url = get_privacy_policy_url();
?>
<!-- wp:html -->
<div class="emerson-footer">
	<div class="emerson-footer__columns">
		<section class="emerson-footer__col">
			<h2 class="emerson-footer__heading">Join Us Sundays</h2>
			<p>
				<strong>10:00 AM</strong>, in person and online<br />
				St. Charles YMCA<br />
				3900 Shady Springs Ln<br />
				St Peters, MO 63376
			</p>
			<ul class="emerson-footer__links">
				<li><a href="http://zoom.us/j/92538603507?pwd=K3NJWVE4ZnhvWkJWK1Nmbm8zOXhQUT09">Join on Zoom</a></li>
				<li><a href="<?php echo esc_url( home_url( '/worship/' ) ); ?>">Sunday Services</a></li>
				<li><a href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>">Calendar</a></li>
			</ul>
		</section>

		<section class="emerson-footer__col">
			<h2 class="emerson-footer__heading">Contact</h2>
			<p>
				<a href="mailto:office@emersonuuchapel.org">office@emersonuuchapel.org</a>
			</p>
			<p>
				<strong>Mailing address</strong><br />
				PO Box 175<br />
				Saint Charles, MO 63302
			</p>
			<ul class="emerson-footer__links">
				<li><a href="<?php echo esc_url( home_url( '/contact-newcomer-information/' ) ); ?>">Newcomer Information</a></li>
				<li><a href="<?php echo esc_url( home_url( '/make-a-contribution/' ) ); ?>">Giving</a></li>
			</ul>
		</section>

		<section class="emerson-footer__col">
			<a class="emerson-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/EmersonUUChapelLogo_original.png' ) ); ?>" alt="Emerson Unitarian Universalist Chapel — Home" width="401" height="360" loading="lazy" />
			</a>
			<p>A liberal, welcoming religious community in St. Charles County, transforming ourselves, our community, and our world since 1984.</p>
		</section>
	</div>

	<div class="emerson-footer__bottom">
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Emerson Unitarian Universalist Chapel</p>
		<p class="emerson-footer__legal">
			<?php if ( $privacy_url ) : ?>
				<a href="<?php echo esc_url( $privacy_url ); ?>">Privacy Policy</a>
				<span aria-hidden="true"> · </span>
			<?php endif; ?>
			<a href="<?php echo esc_url( home_url( '/cookies/' ) ); ?>">Cookies</a>
			<span aria-hidden="true"> · </span>
			<a href="<?php echo esc_url( home_url( '/terms-of-use/' ) ); ?>">Terms of Use</a>
		</p>
	</div>
</div>
<!-- /wp:html -->
