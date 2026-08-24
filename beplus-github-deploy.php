<?php

/**
 * Plugin Name:       Beplus GitHub Deploy
 * Plugin URI:        https://beplusthemes.com
 * Description:       Deploy WordPress plugins and themes directly from GitHub repositories (public or private) with automatic one-shot backup and rollback support.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Beplus
 * Author URI:        https://beplusthemes.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       beplus-github-deploy
 * Domain Path:       /languages.
 *
 * @package BeplusManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BEPLUS_MANAGER_VERSION', '1.0.0' );
define( 'BEPLUS_MANAGER_FILE', __FILE__ );
define( 'BEPLUS_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
define( 'BEPLUS_MANAGER_URL', plugin_dir_url( __FILE__ ) );
define( 'BEPLUS_MANAGER_BASENAME', plugin_basename( __FILE__ ) );
define( 'BEPLUS_MANAGER_NAMESPACE', 'BeplusManager' );

// Simple PSR-4 fallback autoloader (no Composer required).
spl_autoload_register( static function ( $class ) {
	$prefix = 'BeplusManager\\';
	if ( 0 !== strpos( $class, $prefix ) ) {
		return;
	}
	$relative = substr( $class, strlen( $prefix ) );
	$file     = BEPLUS_MANAGER_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( is_file( $file ) ) {
		require $file;
	}
} );

require_once BEPLUS_MANAGER_DIR . 'includes/helpers.php';

/**
 * Boot the plugin.
 *
 * @return BeplusManager\Core\Plugin
 */
function beplus_manager_boot() {
	static $booted = null;
	if ( null !== $booted ) {
		return $booted;
	}
	$booted = BeplusManager\Core\Plugin::instance();
	$booted->boot();
	return $booted;
}

add_action( 'plugins_loaded', static function () {
	beplus_manager_boot();
} );

register_activation_hook( __FILE__, static function () {
	require_once BEPLUS_MANAGER_DIR . 'includes/helpers.php';
	BeplusManager\Core\Plugin::instance()->activate();
} );

register_deactivation_hook( __FILE__, static function () {
	BeplusManager\Core\Plugin::instance()->deactivate();
} );
