<?php
/**
 * Plugin Name:       DevOrbit Login Shield
 * Plugin URI:        https://github.com/NiteshPatel-1988
 * Description:       Hides wp-login.php and blocks unauthenticated wp-admin access, serving the login form at a custom URL you choose.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            NitsPatel
 * Author URI:        https://github.com/NiteshPatel-1988
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       devorbit-login-shield
 * Domain Path:       /languages
 *
 * @package DevOrbit_Login_Shield
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DEVORBIT_LOGIN_SHIELD_VERSION', '1.0.3' );
define( 'DEVORBIT_LOGIN_SHIELD_PLUGIN_FILE', __FILE__ );
define( 'DEVORBIT_LOGIN_SHIELD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DEVORBIT_LOGIN_SHIELD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DEVORBIT_LOGIN_SHIELD_PLUGIN_DIR . 'includes/class-devorbit-login-shield.php';

/**
 * Sets sensible defaults on activation without overwriting any existing
 * configuration, so re-activating the plugin never resets a chosen slug.
 */
function devorbit_login_shield_activate() {
	if ( false === get_option( DevOrbit_Login_Shield::OPTION_SLUG, false ) ) {
		add_option( DevOrbit_Login_Shield::OPTION_SLUG, DevOrbit_Login_Shield::DEFAULT_SLUG );
	}

	if ( false === get_option( DevOrbit_Login_Shield::OPTION_REDIRECT_MODE, false ) ) {
		add_option( DevOrbit_Login_Shield::OPTION_REDIRECT_MODE, 'home' );
	}

	if ( false === get_option( DevOrbit_Login_Shield::OPTION_HIDE_LINKS, false ) ) {
		add_option( DevOrbit_Login_Shield::OPTION_HIDE_LINKS, '1' );
	}
}
register_activation_hook( __FILE__, 'devorbit_login_shield_activate' );

/**
 * Boots at include time rather than on plugins_loaded. The class needs to
 * register an init callback of its own to catch requests to wp-login.php and
 * wp-admin before WordPress renders them, and a hook added from inside
 * plugins_loaded would never fire. Nothing runs at this point beyond
 * add_action()/add_filter() calls.
 */
DevOrbit_Login_Shield::instance();
