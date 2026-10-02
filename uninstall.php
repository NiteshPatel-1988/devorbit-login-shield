<?php
/**
 * Fires when the plugin is deleted from the Plugins screen. WordPress
 * only includes this file after verifying WP_UNINSTALL_PLUGIN is defined,
 * which happens solely on deletion, not on plain deactivation.
 *
 * @package DevOrbit_Login_Shield
 */

// If uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$devorbit_login_shield_options = array(
	'devorbit_login_shield_slug',
	'devorbit_login_shield_redirect_mode',
	'devorbit_login_shield_hide_links',
);

// The settings are stored per site, so on a network they have to be removed
// from every site rather than only the one the deletion was triggered from.
if ( is_multisite() ) {
	$devorbit_login_shield_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $devorbit_login_shield_site_ids as $devorbit_login_shield_site_id ) {
		switch_to_blog( $devorbit_login_shield_site_id );

		foreach ( $devorbit_login_shield_options as $devorbit_login_shield_option ) {
			delete_option( $devorbit_login_shield_option );
		}

		restore_current_blog();
	}

	return;
}

foreach ( $devorbit_login_shield_options as $devorbit_login_shield_option ) {
	delete_option( $devorbit_login_shield_option );
}
