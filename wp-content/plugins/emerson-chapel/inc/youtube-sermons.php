<?php
/**
 * YouTube sermon sync: homepage tracks the newest channel upload;
 * Video Gallery imports from both chapel channels for the archive.
 *
 * Uses public channel Atom feeds (no API key). Titles like "2026 07 19 …"
 * set the gallery publish date to that Sunday at 10:00.
 */

declare( strict_types=1 );

/** Official / older channel (archive). */
const EMERSON_YOUTUBE_CHANNEL_OFFICIAL = 'UCn3aTRih5xMb2FHwblL1rtQ';

/** Newer uploads channel (homepage prefers whichever feed has the latest sermon date). */
const EMERSON_YOUTUBE_CHANNEL_RECENT = 'UCuuBuu3otSMIuKVvSwjbFtA';

const EMERSON_YOUTUBE_HOMEPAGE_OPTION = 'emerson_youtube_homepage_latest';
const EMERSON_YOUTUBE_SYNC_LOG_OPTION = 'emerson_youtube_sermons_sync_log';
const EMERSON_YOUTUBE_CRON_HOOK       = 'emerson_youtube_sermons_sync';

/**
 * Both channels to sync into Video Gallery.
 *
 * @return array<string, string> channel_id => label
 */
function emerson_youtube_sermon_channels(): array {
	return array(
		EMERSON_YOUTUBE_CHANNEL_RECENT   => 'EmersonUUChapel',
		EMERSON_YOUTUBE_CHANNEL_OFFICIAL => 'Emerson Unitarian Universalist Chapel',
	);
}

/**
 * Sunday (or service) date from a YouTube title, or null.
 */
function emerson_youtube_sermon_date_from_title( string $title ): ?string {
	if ( preg_match( '/\b(20\d{2})[-\s.\/](\d{1,2})[-\s.\/](\d{1,2})\b/', $title, $match ) ) {
		$y = (int) $match[1];
		$m = (int) $match[2];
		$d = (int) $match[3];
		if ( checkdate( $m, $d, $y ) ) {
			return sprintf( '%04d-%02d-%02d', $y, $m, $d );
		}
	}
	return null;
}

/**
 * @return list<array{id:string,title:string,published:string,thumbnail:string,channel_id:string}>
 */
function emerson_youtube_fetch_channel_feed( string $channel_id ): array {
	$channel_id = trim( $channel_id );
	if ( ! preg_match( '/^UC[A-Za-z0-9_-]{22}$/', $channel_id ) ) {
		return array();
	}
	$url  = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode( $channel_id );
	$res  = wp_remote_get(
		$url,
		array(
			'timeout' => 20,
			'headers' => array( 'Accept' => 'application/atom+xml,application/xml,text/xml' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return array();
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = (string) wp_remote_retrieve_body( $res );
	if ( $code < 200 || $code >= 300 || $body === '' ) {
		return array();
	}

	$prev = libxml_use_internal_errors( true );
	$xml  = simplexml_load_string( $body );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $xml ) {
		return array();
	}

	$xml->registerXPathNamespace( 'atom', 'http://www.w3.org/2005/Atom' );
	$xml->registerXPathNamespace( 'yt', 'http://www.youtube.com/xml/schemas/2015' );
	$xml->registerXPathNamespace( 'media', 'http://search.yahoo.com/mrss/' );

	$entries = $xml->xpath( '//atom:entry' );
	if ( ! is_array( $entries ) ) {
		return array();
	}

	$out = array();
	foreach ( $entries as $entry ) {
		$entry->registerXPathNamespace( 'atom', 'http://www.w3.org/2005/Atom' );
		$entry->registerXPathNamespace( 'yt', 'http://www.youtube.com/xml/schemas/2015' );
		$entry->registerXPathNamespace( 'media', 'http://search.yahoo.com/mrss/' );

		$id_nodes = $entry->xpath( './yt:videoId' );
		$id       = ( is_array( $id_nodes ) && isset( $id_nodes[0] ) ) ? trim( (string) $id_nodes[0] ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ) {
			continue;
		}
		$title_nodes = $entry->xpath( './atom:title' );
		$title       = ( is_array( $title_nodes ) && isset( $title_nodes[0] ) ) ? trim( (string) $title_nodes[0] ) : $id;
		$pub_nodes   = $entry->xpath( './atom:published' );
		$published   = ( is_array( $pub_nodes ) && isset( $pub_nodes[0] ) ) ? trim( (string) $pub_nodes[0] ) : '';
		$thumb_nodes = $entry->xpath( './media:group/media:thumbnail/@url' );
		$thumbnail   = ( is_array( $thumb_nodes ) && isset( $thumb_nodes[0] ) ) ? trim( (string) $thumb_nodes[0] ) : emerson_youtube_poster_url( $id );

		$out[] = array(
			'id'         => $id,
			'title'      => $title,
			'published'  => $published,
			'thumbnail'  => $thumbnail,
			'channel_id' => $channel_id,
		);
	}
	return $out;
}

/**
 * Sort key: sermon date from title (preferred), else YouTube published time.
 *
 * @param array{id:string,title:string,published:string} $video
 */
function emerson_youtube_video_sort_key( array $video ): string {
	$sermon = emerson_youtube_sermon_date_from_title( $video['title'] );
	if ( $sermon !== null ) {
		return $sermon . 'T10:00:00+00:00';
	}
	$published = trim( $video['published'] );
	return $published !== '' ? $published : '1970-01-01T00:00:00+00:00';
}

/**
 * Newest dated sermon across the feeds (homepage source).
 *
 * @param list<array{id:string,title:string,published:string,thumbnail:string,channel_id:string}> $videos
 * @return array{id:string,title:string,published:string,thumbnail:string,channel_id:string}|null
 */
function emerson_youtube_pick_homepage_video( array $videos ): ?array {
	$best = null;
	$best_key = '';
	foreach ( $videos as $video ) {
		/* Prefer titles that look like Sunday services. */
		if ( emerson_youtube_sermon_date_from_title( $video['title'] ) === null ) {
			continue;
		}
		$key = emerson_youtube_video_sort_key( $video );
		if ( $best === null || $key > $best_key ) {
			$best     = $video;
			$best_key = $key;
		}
	}
	if ( $best !== null ) {
		return $best;
	}
	foreach ( $videos as $video ) {
		$key = emerson_youtube_video_sort_key( $video );
		if ( $best === null || $key > $best_key ) {
			$best     = $video;
			$best_key = $key;
		}
	}
	return $best;
}

/**
 * YouTube video ids already in Video Gallery.
 *
 * @return array<string, int> video_id => post_id
 */
function emerson_youtube_gallery_index(): array {
	$posts = get_posts(
		array(
			'post_type'      => 'aiovg_videos',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		)
	);
	$index = array();
	foreach ( $posts as $post ) {
		$url = (string) get_post_meta( $post->ID, 'youtube', true );
		$id  = function_exists( 'emerson_youtube_id' ) ? emerson_youtube_id( $url ) : '';
		if ( $id === '' && preg_match( '/([A-Za-z0-9_-]{11})/', $url, $m ) ) {
			$id = $m[1];
		}
		if ( $id !== '' ) {
			$index[ $id ] = (int) $post->ID;
		}
	}
	return $index;
}

/**
 * Create one Video Gallery post for a YouTube sermon.
 *
 * @param array{id:string,title:string,published:string,thumbnail:string,channel_id:string} $video
 */
function emerson_youtube_import_gallery_video( array $video ): int {
	$id    = $video['id'];
	$title = trim( $video['title'] ) !== '' ? trim( $video['title'] ) : $id;
	$sermon_day = emerson_youtube_sermon_date_from_title( $title );
	if ( $sermon_day !== null ) {
		$local = $sermon_day . ' 10:00:00';
	} else {
		$ts = strtotime( $video['published'] );
		$local = $ts ? wp_date( 'Y-m-d H:i:s', $ts ) : current_time( 'mysql' );
	}
	$gmt = get_gmt_from_date( $local );

	$post_id = wp_insert_post(
		array(
			'post_type'     => 'aiovg_videos',
			'post_status'   => 'publish',
			'post_title'    => $title,
			'post_content'  => '',
			'post_date'     => $local,
			'post_date_gmt' => $gmt,
		),
		true
	);
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 0;
	}

	$watch = 'https://youtu.be/' . $id;
	$image = $video['thumbnail'] !== '' ? $video['thumbnail'] : ( 'https://img.youtube.com/vi/' . $id . '/mqdefault.jpg' );

	update_post_meta( $post_id, 'type', 'youtube' );
	update_post_meta( $post_id, 'youtube', esc_url_raw( $watch ) );
	update_post_meta( $post_id, 'image', esc_url_raw( $image ) );
	update_post_meta( $post_id, 'image_id', 0 );
	update_post_meta( $post_id, 'image_alt', '' );
	update_post_meta( $post_id, 'featured', 0 );
	update_post_meta( $post_id, 'mp4', '' );
	update_post_meta( $post_id, 'mp4_id', 0 );
	update_post_meta( $post_id, 'has_webm', 0 );
	update_post_meta( $post_id, 'webm', '' );
	update_post_meta( $post_id, 'webm_id', 0 );
	update_post_meta( $post_id, 'has_ogv', 0 );
	update_post_meta( $post_id, 'ogv', '' );
	update_post_meta( $post_id, 'ogv_id', 0 );
	update_post_meta( $post_id, 'quality_level', '' );
	update_post_meta( $post_id, 'hls', '' );
	update_post_meta( $post_id, 'dash', '' );
	update_post_meta( $post_id, 'vimeo', '' );
	update_post_meta( $post_id, 'dailymotion', '' );
	update_post_meta( $post_id, 'rumble', '' );
	update_post_meta( $post_id, 'facebook', '' );
	update_post_meta( $post_id, 'embedcode', '' );
	update_post_meta( $post_id, 'duration', '' );
	update_post_meta( $post_id, 'views', 0 );
	update_post_meta( $post_id, 'likes', 0 );
	update_post_meta( $post_id, 'dislikes', 0 );
	update_post_meta( $post_id, 'download', 1 );
	update_post_meta( $post_id, 'bunny_stream_video_id', 0 );

	return (int) $post_id;
}

/**
 * Fetch both channels, refresh homepage cache, import missing gallery rows.
 *
 * @return array{homepage:?array{id:string,title:string}, imported:int, seen:int, errors:list<string>}
 */
function emerson_youtube_sermons_sync(): array {
	$all      = array();
	$errors   = array();
	$channels = emerson_youtube_sermon_channels();

	foreach ( $channels as $channel_id => $label ) {
		$feed = emerson_youtube_fetch_channel_feed( $channel_id );
		if ( ! $feed ) {
			$errors[] = 'Could not read feed for ' . $label . '.';
			continue;
		}
		foreach ( $feed as $video ) {
			$all[] = $video;
		}
	}

	$homepage = emerson_youtube_pick_homepage_video( $all );
	if ( $homepage ) {
		update_option(
			EMERSON_YOUTUBE_HOMEPAGE_OPTION,
			array(
				'id'         => $homepage['id'],
				'title'      => $homepage['title'],
				'channel_id' => $homepage['channel_id'],
				'fetched_at' => time(),
			),
			false
		);
	}

	$index    = emerson_youtube_gallery_index();
	$imported = 0;
	foreach ( $all as $video ) {
		if ( isset( $index[ $video['id'] ] ) ) {
			continue;
		}
		/* Archive import: only dated Sunday-style titles (skips random channel clips). */
		if ( emerson_youtube_sermon_date_from_title( $video['title'] ) === null ) {
			continue;
		}
		$new_id = emerson_youtube_import_gallery_video( $video );
		if ( $new_id > 0 ) {
			$index[ $video['id'] ] = $new_id;
			++$imported;
		} else {
			$errors[] = 'Failed to import ' . $video['id'] . '.';
		}
	}

	$result = array(
		'homepage' => $homepage
			? array(
				'id'    => $homepage['id'],
				'title' => $homepage['title'],
			)
			: null,
		'imported' => $imported,
		'seen'     => count( $all ),
		'errors'   => $errors,
		'at'       => time(),
	);
	update_option( EMERSON_YOUTUBE_SYNC_LOG_OPTION, $result, false );
	return $result;
}

/**
 * Cached homepage YouTube id from the last successful sync.
 *
 * @return array{id:string,title:string}
 */
function emerson_youtube_homepage_cached(): array {
	$raw = get_option( EMERSON_YOUTUBE_HOMEPAGE_OPTION, array() );
	if ( ! is_array( $raw ) ) {
		return array( 'id' => '', 'title' => '' );
	}
	$id = isset( $raw['id'] ) ? (string) $raw['id'] : '';
	if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ) {
		return array( 'id' => '', 'title' => '' );
	}
	return array(
		'id'    => $id,
		'title' => isset( $raw['title'] ) ? (string) $raw['title'] : 'Sunday service',
	);
}

add_action(
	EMERSON_YOUTUBE_CRON_HOOK,
	static function (): void {
		emerson_youtube_sermons_sync();
	}
);

add_action(
	'init',
	static function (): void {
		if ( ! wp_next_scheduled( EMERSON_YOUTUBE_CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', EMERSON_YOUTUBE_CRON_HOOK );
		}
	}
);

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'emerson-homepage-sermon',
			'YouTube sync',
			'YouTube sync',
			'edit_pages',
			'emerson-youtube-sermons',
			'emerson_youtube_sermons_admin_page'
		);
	},
	20
);

function emerson_youtube_sermons_admin_page(): void {
	if ( ! current_user_can( 'edit_pages' ) ) {
		wp_die( esc_html__( 'You do not have permission to sync YouTube sermons.' ) );
	}

	$notice = '';
	if ( ! empty( $_POST['emerson_youtube_sermons_sync'] ) ) {
		check_admin_referer( 'emerson_youtube_sermons_sync' );
		$result = emerson_youtube_sermons_sync();
		$notice = sprintf(
			'Sync finished. Saw %d feed videos, imported %d new gallery sermon(s).',
			(int) $result['seen'],
			(int) $result['imported']
		);
		if ( $result['homepage'] ) {
			$notice .= ' Homepage latest: ' . $result['homepage']['title'] . ' (' . $result['homepage']['id'] . ').';
		}
		if ( $result['errors'] ) {
			$notice .= ' Notes: ' . implode( ' ', $result['errors'] );
		}
	}

	$home = emerson_youtube_homepage_cached();
	$log  = get_option( EMERSON_YOUTUBE_SYNC_LOG_OPTION, array() );
	$next = wp_next_scheduled( EMERSON_YOUTUBE_CRON_HOOK );

	echo '<div class="wrap"><h1>YouTube sermon sync</h1>';
	if ( $notice !== '' ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
	}
	echo '<p>The <strong>homepage</strong> player follows the newest dated sermon across both chapel channels (currently preferring whichever feed has the latest Sunday in the title). It does not add extra blocks on the homepage — it only swaps that one video.</p>';
	echo '<p><strong>Past Sermons</strong> / Sunday Services stay on Video Gallery. Sync imports missing dated sermons from both channels into the gallery (each gets its own <code>/video/…</code> page). Undated channel clips are skipped.</p>';
	echo '<ul>';
	foreach ( emerson_youtube_sermon_channels() as $id => $label ) {
		echo '<li>' . esc_html( $label ) . ' — <code>' . esc_html( $id ) . '</code></li>';
	}
	echo '</ul>';
	if ( $home['id'] !== '' ) {
		echo '<p><strong>Cached homepage video:</strong> ' . esc_html( $home['title'] ) . ' (<code>' . esc_html( $home['id'] ) . '</code>). A URL under <strong>Homepage video</strong> still overrides this.</p>';
	} else {
		echo '<p><strong>Cached homepage video:</strong> none yet — run a sync.</p>';
	}
	if ( is_array( $log ) && ! empty( $log['at'] ) ) {
		echo '<p>Last sync: ' . esc_html( wp_date( 'Y-m-d H:i', (int) $log['at'] ) ) . ' (imported ' . (int) ( $log['imported'] ?? 0 ) . ').</p>';
	}
	if ( $next ) {
		echo '<p>Next automatic sync: ' . esc_html( wp_date( 'Y-m-d H:i', (int) $next ) ) . ' (twice daily).</p>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'emerson_youtube_sermons_sync' );
	echo '<p><button type="submit" name="emerson_youtube_sermons_sync" value="1" class="button button-primary">Sync now</button></p>';
	echo '</form></div>';
}
