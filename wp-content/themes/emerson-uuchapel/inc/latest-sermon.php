<?php
/**
 * Homepage: the newest sermon from Video Gallery, as a YouTube player.
 *
 * Past Sermons and Sunday Services use the same list. A newer published
 * video, dated on its Sunday, replaces this player on the next page view.
 */

declare( strict_types=1 );

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
 * Player for the newest published sermon, or nothing when the gallery is empty.
 */
function emerson_latest_sermon_html(): string {
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
		return '';
	}
	$url = (string) get_post_meta( $posts[0]->ID, 'youtube', true );
	$id  = emerson_youtube_id( $url );
	if ( $id === '' ) {
		return '';
	}
	$title = trim( wp_strip_all_tags( get_the_title( $posts[0] ) ) );
	if ( $title === '' ) {
		$title = 'Latest Sunday service';
	}
	$src = 'https://www.youtube.com/embed/' . rawurlencode( $id );

	return '<figure class="emerson-latest-sermon">'
		. '<iframe title="' . esc_attr( $title ) . '" src="' . esc_url( $src ) . '"'
		. ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"'
		. ' referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>'
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
