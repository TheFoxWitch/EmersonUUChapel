<?php
/**
 * Place resized pastor photos on recommended pages.
 * Run once via: wp eval-file /var/www/html/wp-content/backups-place.php
 * (copied into container path below)
 */
global $wpdb;

function emerson_place_update_page( $id, $content ) {
	global $wpdb;
	$ok = $wpdb->update(
		$wpdb->posts,
		array( 'post_content' => $content ),
		array( 'ID' => $id ),
		array( '%s' ),
		array( '%d' )
	);
	clean_post_cache( $id );
	return false !== $ok;
}

$who_we_are_photo = <<<'HTML'
<!-- wp:image {"id":1043,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/whoWeAre-1024x768.jpg" alt="Members and friends of Emerson UU Chapel gathered around a fire circle" class="wp-image-1043"/></figure>
<!-- /wp:image -->

HTML;

$service_hero = <<<'HTML'
<!-- wp:image {"id":1042,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/service2-1024x768.jpg" alt="Sunday service at Emerson UU Chapel, with the pastor standing at an altar decorated with a tree quilt" class="wp-image-1042"/></figure>
<!-- /wp:image -->

HTML;

$service_pair = <<<'HTML'
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"id":1063,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/service1.jpg" alt="The pastor speaking during a Sunday service, arms open, wearing a yellow scarf" class="wp-image-1063"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"id":1047,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/service3-768x1024.jpg" alt="A congregation member speaking from the podium during Sunday service" class="wp-image-1047"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

HTML;

$congregation_photos = <<<'HTML'
<!-- wp:image {"id":1041,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/community3-1024x768.jpg" alt="An indoor gathering of Emerson UU Chapel members, friends, and children" class="wp-image-1041"/></figure>
<!-- /wp:image -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"id":1046,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/community1-1024x768.jpg" alt="Emerson UU Chapel community picnic in an open field" class="wp-image-1046"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"id":1044,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/gameDay1-768x1024.jpg" alt="Members playing chess together on game day" class="wp-image-1044"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
HTML;

$testimonial_letters = <<<'HTML'
<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"id":1060,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/letter1-1-768x1024.jpg" alt="Handwritten note beginning To whoever reads this, with a heart drawn around the text" class="wp-image-1060"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"id":1061,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/letter2-1-768x1024.jpg" alt="Handwritten love letter to Emerson UU Chapel" class="wp-image-1061"/></figure>
<!-- /wp:image --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
HTML;

$re_drawing = <<<'HTML'
<!-- wp:image {"id":1036,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/religiousEducation1-542x1024.jpeg" alt="Child drawing that says I heart Emerson" class="wp-image-1036"/></figure>
<!-- /wp:image -->
HTML;

// Who Are We — fire circle after the intro.
$p190 = get_post( 190 );
$needle190 = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\"><strong>Our Values</strong></h2>";
if ( false === strpos( $p190->post_content, 'wp-image-1043' ) ) {
	if ( false === strpos( $p190->post_content, $needle190 ) ) {
		WP_CLI::error( 'Who Are We insert point not found' );
	}
	$new190 = str_replace( $needle190, $who_we_are_photo . $needle190, $p190->post_content );
	emerson_place_update_page( 190, $new190 );
	WP_CLI::success( 'Who Are We: added whoWeAre' );
} else {
	WP_CLI::log( 'Who Are We: already has whoWeAre' );
}

// Sunday Services — service2 after worship overview; service1+3 before Order of Service.
$p64 = get_post( 64 );
$c64 = $p64->post_content;
if ( false === strpos( $c64, 'wp-image-1042' ) ) {
	$needle64a = "<!-- wp:heading {\"level\":1} -->\n<h1>Our Congregation</h1>";
	if ( false === strpos( $c64, $needle64a ) ) {
		WP_CLI::error( 'Sunday Services first insert point not found' );
	}
	$c64 = str_replace( $needle64a, $service_hero . $needle64a, $c64 );
}
if ( false === strpos( $c64, 'wp-image-1063' ) ) {
	$needle64b = "<!-- wp:heading -->\n<h2>Order of Service</h2>";
	if ( false === strpos( $c64, $needle64b ) ) {
		WP_CLI::error( 'Sunday Services second insert point not found' );
	}
	$c64 = str_replace( $needle64b, $service_pair . $needle64b, $c64 );
}
if ( $c64 !== $p64->post_content ) {
	emerson_place_update_page( 64, $c64 );
	WP_CLI::success( 'Sunday Services: added service photos' );
} else {
	WP_CLI::log( 'Sunday Services: no change' );
}

// Our Congregation — replace misplaced service1 + empty image.
$p209 = get_post( 209 );
$c209 = $p209->post_content;
$old209 = <<<'HTML'
<!-- wp:image {"id":1029,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="http://localhost:8080/wp-content/uploads/2026/10/service1.jpeg" alt="" class="wp-image-1029"/></figure>
<!-- /wp:image -->

<!-- wp:image -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
HTML;
if ( false !== strpos( $c209, $old209 ) ) {
	$c209 = str_replace( $old209, $congregation_photos, $c209 );
	emerson_place_update_page( 209, $c209 );
	WP_CLI::success( 'Our Congregation: community3, community1, gameDay1' );
} elseif ( false === strpos( $c209, 'wp-image-1041' ) ) {
	emerson_place_update_page( 209, rtrim( $c209 ) . "\n\n" . $congregation_photos );
	WP_CLI::success( 'Our Congregation: appended community photos' );
} else {
	WP_CLI::log( 'Our Congregation: already has community3' );
}

// Testimonials — resized letters side by side; move RE drawing off this page.
$p425 = get_post( 425 );
$c425 = $p425->post_content;
$old425 = <<<'HTML'
<!-- wp:image {"id":1034,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/letter1-768x1024.jpg" alt="" class="wp-image-1034"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":1035,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/letter2-768x1024.jpg" alt="" class="wp-image-1035"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":1036,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="http://localhost:8080/wp-content/uploads/2026/10/religiousEducation1-542x1024.jpeg" alt="" class="wp-image-1036"/></figure>
<!-- /wp:image -->
HTML;
if ( false !== strpos( $c425, $old425 ) ) {
	$c425 = str_replace( $old425, $testimonial_letters, $c425 );
	emerson_place_update_page( 425, $c425 );
	WP_CLI::success( 'Testimonials: resized letters; RE drawing removed' );
} elseif ( false === strpos( $c425, 'wp-image-1060' ) ) {
	WP_CLI::warning( 'Testimonials: expected old letter blocks not found; leaving page as-is' );
} else {
	WP_CLI::log( 'Testimonials: already has resized letters' );
}

// Religious Education — child's drawing after the last paragraph.
$p203 = get_post( 203 );
if ( false === strpos( $p203->post_content, 'wp-image-1036' ) ) {
	emerson_place_update_page( 203, rtrim( $p203->post_content ) . "\n\n" . $re_drawing . "\n" );
	WP_CLI::success( 'Religious Education: added drawing' );
} else {
	WP_CLI::log( 'Religious Education: already has drawing' );
}
