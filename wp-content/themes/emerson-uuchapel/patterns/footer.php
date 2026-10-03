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
				<a href="tel:+16367573633">(636) 757-3633</a><br />
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
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/EmersonUUchapel.png' ) ); ?>" alt="Emerson Unitarian Universalist Chapel — Home" width="292" height="250" loading="lazy" />
			</a>
			<p>A liberal, welcoming religious community in St. Charles County, transforming ourselves, our community, and our world since 1984.</p>
			<ul class="emerson-footer__social">
				<li>
					<a href="https://www.facebook.com/emersonuuchapel" target="_blank" rel="noreferrer noopener" aria-label="Emerson Chapel on Facebook">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-7H8.1V12h2.34V9.8c0-2.3 1.37-3.57 3.47-3.57.99 0 2.03.18 2.03.18v2.23h-1.14c-1.13 0-1.48.7-1.48 1.42V12h2.52l-.4 2.88h-2.12v7A10 10 0 0 0 22 12"/></svg>
					</a>
				</li>
				<li>
					<a href="https://www.instagram.com/emersonuuchapel" target="_blank" rel="noreferrer noopener" aria-label="Emerson Chapel on Instagram">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm10 1.8H7A2.2 2.2 0 0 0 4.8 7v10A2.2 2.2 0 0 0 7 19.2h10A2.2 2.2 0 0 0 19.2 17V7A2.2 2.2 0 0 0 17 4.8zM12 8.1A3.9 3.9 0 1 1 8.1 12 3.9 3.9 0 0 1 12 8.1zm0 1.7A2.2 2.2 0 1 0 14.2 12 2.2 2.2 0 0 0 12 9.8zm4.35-3.15a.9.9 0 1 1-.9.9.9.9 0 0 1 .9-.9z"/></svg>
					</a>
				</li>
				<li>
					<a href="https://www.youtube.com/@emersonunitarianuniversali1222" target="_blank" rel="noreferrer noopener" aria-label="Emerson Chapel on YouTube">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M23 12.2s0-3.2-.4-4.6c-.22-.86-.9-1.53-1.76-1.76C19.5 5.4 12 5.4 12 5.4s-7.5 0-8.84.44c-.86.23-1.54.9-1.76 1.76C1 9 1 12.2 1 12.2s0 3.2.4 4.6c.22.86.9 1.53 1.76 1.76C4.5 19 12 19 12 19s7.5 0 8.84-.44c.86-.23 1.54-.9 1.76-1.76.4-1.4.4-4.6.4-4.6zM9.75 15.02V9.38L15.5 12.2l-5.75 2.82z"/></svg>
					</a>
				</li>
			</ul>
		</section>
	</div>

	<div class="emerson-footer__bottom">
		<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Emerson Unitarian Universalist Chapel</p>
		<?php if ( $privacy_url ) : ?>
			<p><a href="<?php echo esc_url( $privacy_url ); ?>">Privacy Policy</a></p>
		<?php endif; ?>
	</div>
</div>
<!-- /wp:html -->
