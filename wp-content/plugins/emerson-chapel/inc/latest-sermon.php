<?php
/**
 * Homepage sermon player.
 *
 * The shortcode stays on the page. Homepage video in the dashboard stores the
 * YouTube address. With no address saved, the player uses the newest sermon
 * in Video Gallery.
 */

declare( strict_types=1 );

const EMERSON_HOMEPAGE_SERMON_OPTION = 'emerson_homepage_sermon_url';

/**
 * Saved watch link, or an empty string when the newest gallery sermon should play.
 */
function emerson_homepage_sermon_url(): string {
	return trim( (string) get_option( EMERSON_HOMEPAGE_SERMON_OPTION, '' ) );
}

/**
 * YouTube video id from a watch, share, embed, or shorts URL.
 */
function emerson_youtube_id( string $url ): string {
	if ( preg_match( '~(?:youtu\.be/|youtube\.com/(?:embed/|shorts/|live/|watch\?(?:.*&)?v=))([A-Za-z0-9_-]{11})~', $url, $match ) ) {
		return $match[1];
	}
	return '';
}

/**
 * Newest published sermon in Video Gallery.
 *
 * @return array{url:string, title:string}
 */
function emerson_latest_gallery_sermon(): array {
	$posts = get_posts(
		array(
			'post_type'      => 'aiovg_videos',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);
	if ( ! $posts ) {
		return array(
			'url'   => '',
			'title' => '',
		);
	}
	$title = trim( wp_strip_all_tags( get_the_title( $posts[0] ) ) );
	return array(
		'url'   => (string) get_post_meta( $posts[0]->ID, 'youtube', true ),
		'title' => $title,
	);
}

/**
 * YouTube poster image for a video id (hqdefault is reliably present).
 */
function emerson_youtube_poster_url( string $id ): string {
	return 'https://i.ytimg.com/vi/' . rawurlencode( $id ) . '/hqdefault.jpg';
}

/**
 * Embed URL for the homepage sermon (autoplay for click-to-load).
 * enablejsapi lets Safari foil resume when the visitor pauses.
 */
function emerson_latest_sermon_embed_src( string $id ): string {
	$args = array(
		'playsinline'  => '1',
		'rel'          => '0',
		'autoplay'     => '1',
		'enablejsapi'  => '1',
	);
	$origin = home_url();
	if ( $origin !== '' ) {
		$args['origin'] = $origin;
	}
	return add_query_arg( $args, 'https://www.youtube.com/embed/' . rawurlencode( $id ) );
}

/**
 * Resolved homepage sermon: YouTube id + title, or empty id when none.
 *
 * @return array{id:string, title:string}
 */
function emerson_latest_sermon_resolved(): array {
	$url   = emerson_homepage_sermon_url();
	$title = 'Sunday service';
	if ( emerson_youtube_id( $url ) === '' ) {
		$sermon = emerson_latest_gallery_sermon();
		$url    = $sermon['url'];
		if ( $sermon['title'] !== '' ) {
			$title = $sermon['title'];
		}
	}
	return array(
		'id'    => emerson_youtube_id( $url ),
		'title' => $title,
	);
}

/**
 * Click-to-load player: poster until play, then YouTube iframe.
 * Keeps Safari foil animating on the homepage until someone starts the video.
 */
function emerson_latest_sermon_html(): string {
	$sermon = emerson_latest_sermon_resolved();
	$id     = $sermon['id'];
	$title  = $sermon['title'];
	if ( $id === '' ) {
		return '';
	}
	$src    = emerson_latest_sermon_embed_src( $id );
	$poster = emerson_youtube_poster_url( $id );
	$label  = sprintf(
		/* translators: %s: sermon title */
		__( 'Play %s', 'emerson-chapel' ),
		$title
	);

	return '<figure class="emerson-latest-sermon"'
		. ' data-embed-src="' . esc_url( $src ) . '"'
		. ' data-embed-title="' . esc_attr( $title ) . '">'
		. '<button type="button" class="emerson-latest-sermon__play" aria-label="' . esc_attr( $label ) . '">'
		. '<img class="emerson-latest-sermon__poster" src="' . esc_url( $poster ) . '" alt="" width="480" height="360" decoding="async" fetchpriority="high">'
		. '<span class="emerson-latest-sermon__play-icon" aria-hidden="true"></span>'
		. '</button>'
		. '</figure>';
}

/**
 * @param array<string,string>|string $attributes
 */
function emerson_latest_sermon_shortcode( $attributes = array() ): string {
	unset( $attributes );
	return emerson_latest_sermon_html();
}
add_shortcode( 'emerson_latest_sermon', 'emerson_latest_sermon_shortcode' );

/**
 * A pasted watch link, or the address inside an AIOVG shortcode.
 */
function emerson_homepage_sermon_clean( string $raw ): string {
	$raw = trim( $raw );
	if ( preg_match( '/\byoutube\s*=\s*["\']([^"\']+)["\']/', $raw, $match ) ) {
		$raw = $match[1];
	}
	return esc_url_raw( $raw );
}

add_action(
	'admin_menu',
	static function (): void {
		add_menu_page(
			'Homepage video',
			'Homepage video',
			'edit_pages',
			'emerson-homepage-sermon',
			'emerson_homepage_sermon_admin_page',
			'dashicons-video-alt3',
			62
		);
	}
);

function emerson_homepage_sermon_admin_save(): ?string {
	if ( ! current_user_can( 'edit_pages' ) || empty( $_POST['emerson_homepage_sermon_save'] ) ) {
		return null;
	}
	check_admin_referer( 'emerson_homepage_sermon_save' );
	$raw = isset( $_POST['sermon_url'] ) ? wp_unslash( (string) $_POST['sermon_url'] ) : '';
	$url = emerson_homepage_sermon_clean( $raw );
	if ( $url === '' ) {
		delete_option( EMERSON_HOMEPAGE_SERMON_OPTION );
		return 'Saved. The homepage will play the newest sermon in Video Gallery.';
	}
	if ( emerson_youtube_id( $url ) === '' ) {
		return 'That does not look like a YouTube link. Paste the address from the browser while the video is open.';
	}
	update_option( EMERSON_HOMEPAGE_SERMON_OPTION, $url, false );
	return 'Saved. The homepage will play this video.';
}

function emerson_homepage_sermon_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to edit the homepage video.' ) );
	}
	$notice = emerson_homepage_sermon_admin_save();
	echo '<div class="wrap"><h1>Homepage video</h1>';
	if ( $notice ) {
		$bad = 0 !== strpos( $notice, 'Saved.' );
		echo '<div class="notice ' . ( $bad ? 'notice-error' : 'notice-success' ) . ' is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>The homepage plays this video between the two lines under <strong>Sign up for our Newsletter!</strong>. The player stays the same size. Change the address here instead of editing the shortcode on the page.</p>';
	echo '<p>Paste the address from the browser while the video is open. It looks like <code>https://www.youtube.com/watch?v=&hellip;</code>. Clear the field and save to use the newest sermon in <strong>Video Gallery</strong> again.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'emerson_homepage_sermon_save' );
	echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="emerson-homepage-sermon">YouTube URL</label></th><td>';
	echo '<input name="sermon_url" id="emerson-homepage-sermon" type="url" class="large-text" value="' . esc_attr( emerson_homepage_sermon_url() ) . '">';
	echo '</td></tr></table>';
	echo '<p class="submit"><button type="submit" name="emerson_homepage_sermon_save" value="1" class="button button-primary">Save</button></p>';
	echo '</form></div>';
}
