<?php
/**
 * Site hardening kept in Emerson (not upstream plugins).
 *
 * Tracked in SECURITY-AUDIT.md — one change at a time.
 */

declare( strict_types=1 );

/**
 * Audit #1: Zephyr Manager / Administrator must not hold WordPress manage_options.
 * That cap is for site admins only; Zephyr’s own zpm_* caps are enough for the plugin.
 */
function emerson_security_strip_zephyr_manage_options(): void {
	foreach ( array( 'zpm_manager', 'zpm_admin' ) as $slug ) {
		$role = get_role( $slug );
		if ( $role && $role->has_cap( 'manage_options' ) ) {
			$role->remove_cap( 'manage_options' );
		}
	}
}
add_action( 'init', 'emerson_security_strip_zephyr_manage_options', 20 );

/**
 * Audit #2: All-in-One Video Gallery must not give Subscribers edit/delete video caps.
 * Stock Subscriber is read-only; those caps were an unsafe plugin grant.
 */
function emerson_security_strip_subscriber_aiovg_caps(): void {
	$role = get_role( 'subscriber' );
	if ( ! $role ) {
		return;
	}
	foreach (
		array(
			'edit_aiovg_videos',
			'edit_published_aiovg_videos',
			'delete_aiovg_videos',
			'delete_published_aiovg_videos',
		) as $cap
	) {
		if ( $role->has_cap( $cap ) ) {
			$role->remove_cap( $cap );
		}
	}
}
add_action( 'init', 'emerson_security_strip_subscriber_aiovg_caps', 20 );

/**
 * Audit #8: Authors must not manage TablePress tables site-wide.
 * Church answer (2026-11-10): No — only Administrators and Editors keep tablepress_*.
 */
function emerson_security_strip_author_tablepress_caps(): void {
	$role = get_role( 'author' );
	if ( ! $role ) {
		return;
	}
	foreach (
		array(
			'tablepress_access_about_screen',
			'tablepress_access_options_screen',
			'tablepress_add_tables',
			'tablepress_copy_tables',
			'tablepress_delete_tables',
			'tablepress_edit_tables',
			'tablepress_export_tables',
			'tablepress_import_tables',
			'tablepress_list_tables',
		) as $cap
	) {
		if ( $role->has_cap( $cap ) ) {
			$role->remove_cap( $cap );
		}
	}
}
add_action( 'init', 'emerson_security_strip_author_tablepress_caps', 20 );

/**
 * Audit #3: Turn off XML-RPC.
 * Why: xmlrpc.php is a common brute-force and multicall amplification target.
 * This chapel does not need the old remote-publish / Jetpack-style XML-RPC API.
 * Filters alone still leave system.listMethods; exit as soon as an XML-RPC boot is detected.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', static fn(): array => array() );
add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Content-Type-Options: nosniff' );
		echo 'XML-RPC is disabled on this site.';
		exit;
	},
	1
);

/**
 * Audit #4: Hide the Theme/Plugin File Editor in wp-admin.
 * Why: if an admin account is stolen, the attacker cannot edit PHP from the dashboard.
 * (Full read-only disk on live is still a host setting — see SECURITY-AUDIT.md #11.)
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

/**
 * Audit #5: Drop unused Zephyr Administrator role when nobody has it.
 * Why: unused high-privilege-shaped roles clutter Users and confuse audits.
 */
function emerson_security_remove_unused_zephyr_admin_role(): void {
	if ( ! get_role( 'zpm_admin' ) ) {
		return;
	}
	$holders = get_users(
		array(
			'role'   => 'zpm_admin',
			'number' => 1,
			'fields' => 'ID',
		)
	);
	if ( $holders ) {
		return;
	}
	remove_role( 'zpm_admin' );
}
add_action( 'init', 'emerson_security_remove_unused_zephyr_admin_role', 30 );

/**
 * Audit #9: Stop advertising the REST API on the public front end.
 * Why: Site Health flags auto-discovery (HTML <link>, HTTP Link header, RSD).
 * What we do NOT do: disable /wp-json/ or block logged-in REST — Gutenberg, the
 * Site Editor, and many plugins need those. Church Admin front AJAX is admin-ajax.
 *
 * Also: anonymous /wp/v2/users was listing author profiles (user enumeration).
 * Guests get 401 on user routes; logged-in users keep normal REST access.
 */
function emerson_security_remove_rest_discovery(): void {
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
	// RSD still points at xmlrpc.php; XML-RPC is already disabled (#3).
	remove_action( 'wp_head', 'rsd_link' );
}
add_action( 'init', 'emerson_security_remove_rest_discovery', 20 );

/**
 * @param mixed           $result Response to replace, or null to continue.
 * @param WP_REST_Server  $server Server instance.
 * @param WP_REST_Request $request Request used to generate the response.
 * @return mixed
 */
function emerson_security_block_anonymous_rest_users( $result, $server, $request ) {
	unset( $server );
	if ( null !== $result ) {
		return $result;
	}
	if ( is_user_logged_in() ) {
		return $result;
	}
	$route = (string) $request->get_route();
	if ( ! str_starts_with( $route, '/wp/v2/users' ) ) {
		return $result;
	}
	return new WP_Error(
		'rest_forbidden',
		__( 'You are not currently logged in.' ),
		array( 'status' => 401 )
	);
}
add_filter( 'rest_pre_dispatch', 'emerson_security_block_anonymous_rest_users', 10, 3 );

/**
 * Audit #10: Remove leftover NextGEN Gallery capabilities.
 * Why: NextGEN is not installed; those oddly named caps (spaces, “NextGEN …”) only
 * clutter Administrator and confuse security scanners. Renaming them would be pointless
 * without the plugin — dropping the orphans is the safe fix.
 */
function emerson_security_remove_orphan_nextgen_caps(): void {
	$roles = wp_roles();
	if ( ! $roles instanceof WP_Roles ) {
		return;
	}
	foreach ( array_keys( $roles->roles ) as $slug ) {
		$role = get_role( (string) $slug );
		if ( ! $role ) {
			continue;
		}
		foreach ( array_keys( $role->capabilities ) as $cap ) {
			if ( str_starts_with( (string) $cap, 'NextGEN ' ) ) {
				$role->remove_cap( (string) $cap );
			}
		}
	}
}
add_action( 'init', 'emerson_security_remove_orphan_nextgen_caps', 25 );
