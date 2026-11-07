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
