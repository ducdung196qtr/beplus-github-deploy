<?php

/**
 * PHPStan bootstrap — loads WordPress core so the analyser understands
 * WP functions/classes without a live install.
 *
 * @package BeplusManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'BEPLUS_MANAGER_VERSION' ) ) {
	define( 'BEPLUS_MANAGER_VERSION', '1.0.0' );
}

if ( ! defined( 'BEPLUS_MANAGER_FILE' ) ) {
	define( 'BEPLUS_MANAGER_FILE', __FILE__ );
}

if ( ! defined( 'BEPLUS_MANAGER_DIR' ) ) {
	define( 'BEPLUS_MANAGER_DIR', __DIR__ . '/' );
}

if ( ! defined( 'BEPLUS_MANAGER_URL' ) ) {
	define( 'BEPLUS_MANAGER_URL', 'https://example.test/wp-content/plugins/beplus-github-deploy/' );
}

if ( ! defined( 'BEPLUS_MANAGER_BASENAME' ) ) {
	define( 'BEPLUS_MANAGER_BASENAME', 'beplus-github-deploy/beplus-github-deploy.php' );
}

if ( ! defined( 'BEPLUS_MANAGER_NAMESPACE' ) ) {
	define( 'BEPLUS_MANAGER_NAMESPACE', 'BeplusManager' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );
}

// Minimal WP core functions used by the plugin (for static analysis only).
if ( ! function_exists( 'add_action' ) ) {
	function add_action( ...$args ) {}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( ...$args ) {}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( ...$args ) {}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( ...$args ) {}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( ...$args ) {}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( ...$args ) {}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( ...$args ) {}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( ...$args ) {}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( ...$args ) {}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( ...$args ) {}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( ...$args ) {}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( ...$args ) {}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( ...$args ) {}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( ...$args ) {}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( ...$args ) {}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( ...$args ) {
		return false;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {}
}

if ( ! class_exists( 'WP_REST_Server' ) ) {
	class WP_REST_Server {
		const READABLE   = 'GET';
		const EDITABLE   = 'POST, PUT, PATCH';
		const CREATABLE  = 'POST';
		const DELETABLE  = 'DELETE';
		const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( ...$args ) {}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( ...$args ) {}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( ...$args ) {}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( ...$args ) {}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( ...$args ) {}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( ...$args ) {}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( ...$args ) {}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( ...$args ) {}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( ...$args ) {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( ...$args ) {}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( ...$args ) {}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( ...$args ) {
		return true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( ...$args ) {}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page( ...$args ) {}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( ...$args ) {}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( ...$args ) {}
}

if ( ! function_exists( 'wp_localize_script' ) ) {
	function wp_localize_script( ...$args ) {}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( ...$args ) {}
}

if ( ! function_exists( '__' ) ) {
	function __( ...$args ) {}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( ...$args ) {}
}

if ( ! function_exists( 'wp_handle_upload' ) ) {
	function wp_handle_upload( ...$args ) {}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir( ...$args ) {}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( ...$args ) {}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( ...$args ) {}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( ...$args ) {}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( ...$args ) {}
}
