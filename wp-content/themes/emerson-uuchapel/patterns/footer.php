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
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/EmersonUUchapelLogoUpscale.png' ) ); ?>" alt="Emerson Unitarian Universalist Chapel — Home" width="699" height="624" loading="lazy" />
			</a>
			<p>A liberal, welcoming religious community in St. Charles County, transforming ourselves, our community, and our world since 1984.</p>
			<ul class="emerson-footer__social">
				<li>
					<a href="https://www.facebook.com/emersonuuchapel" target="_blank" rel="noreferrer noopener" aria-label="Emerson Chapel on Facebook">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-7H8.1V12h2.34V9.8c0-2.3 1.37-3.57 3.47-3.57.99 0 2.03.18 2.03.18v2.23h-1.14c-1.13 0-1.48.7-1.48 1.42V12h2.52l-.4 2.88h-2.12v7A10 10 0 0 0 22 12"/></svg>
					</a>
				</li>
				<li>
					<a href="https://www.instagram.com/emersonuucommunity" target="_blank" rel="noreferrer noopener" aria-label="Emerson Chapel on Instagram">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 3h10a4 4 0 0 1 4 4v10a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V7a4 4 0 0 1 4-4zm10 1.8H7A2.2 2.2 0 0 0 4.8 7v10A2.2 2.2 0 0 0 7 19.2h10A2.2 2.2 0 0 0 19.2 17V7A2.2 2.2 0 0 0 17 4.8zM12 8.1A3.9 3.9 0 1 1 8.1 12 3.9 3.9 0 0 1 12 8.1zm0 1.7A2.2 2.2 0 1 0 14.2 12 2.2 2.2 0 0 0 12 9.8zm4.35-3.15a.9.9 0 1 1-.9.9.9.9 0 0 1 .9-.9z"/></svg>
					</a>
				</li>
				<li>
					<a href="https://www.youtube.com/@emersonunitarianuniversali1222" target="_blank" rel="noreferrer noopener" aria-label="Emerson Chapel on YouTube">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M23 12.2s0-3.2-.4-4.6c-.22-.86-.9-1.53-1.76-1.76C19.5 5.4 12 5.4 12 5.4s-7.5 0-8.84.44c-.86.23-1.54.9-1.76 1.76C1 9 1 12.2 1 12.2s0 3.2.4 4.6c.22.86.9 1.53 1.76 1.76C4.5 19 12 19 12 19s7.5 0 8.84-.44c.86-.23 1.54-.9 1.76-1.76.4-1.4.4-4.6.4-4.6zM9.75 15.02V9.38L15.5 12.2l-5.75 2.82z"/></svg>
					</a>
				</li>
				<li>
					<a href="https://groupme.com/join_group/32110283/HqzkKf" target="_blank" rel="noreferrer noopener" aria-label="Join Emerson Chapel on GroupMe">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.1597 6.57419H12.8398V8.16898H11.1597V6.57419ZM18.1997 0H5.80011C3.14898 0 1 2.03979 1 4.55577V16.3243C1 18.8402 3.14898 20.88 5.80011 20.88H9.92715L11.9786 24L14.0306 20.88H18.1997C20.8506 20.88 23 18.8402 23 16.3243V4.55574C23 2.03976 20.8506 0 18.1997 0ZM7.56833 8.16895H9.34755V6.57416H7.56833V4.85447H9.34755V3.16587H11.1597V4.85447H12.8398V3.16587H14.6519V4.85447H16.4308V6.57416H14.6519V8.16895H16.4308V9.88852H14.6519V11.5772H12.8398V9.88852H11.1597V11.5772H9.34755V9.88852H7.56833V8.16895ZM20.3122 13.4321C20.3122 13.4321 17.9202 17.708 12.2406 17.708C12.1619 17.708 12.0843 17.707 12.007 17.7057C11.9299 17.707 11.8522 17.708 11.7737 17.708C6.09416 17.708 3.70211 13.4321 3.70211 13.4321C3.70211 13.4321 3.54729 13.1536 3.54729 12.8534C3.53754 12.6368 3.64915 12.3263 3.9421 12.1433C4.105 12.0417 4.259 11.9914 4.40179 11.9757C5.08566 11.9069 5.48202 12.3295 5.80794 12.8121C6.16788 13.3447 8.24445 15.678 12.007 15.7672C15.7698 15.678 17.8464 13.3447 18.2063 12.8121C18.5322 12.3295 18.9429 11.9062 19.6125 11.9757C19.7553 11.9914 19.9094 12.0417 20.0722 12.1433C20.3652 12.3263 20.4792 12.5839 20.469 12.8532C20.446 13.2494 20.3122 13.4321 20.3122 13.4321Z"/></svg>
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
