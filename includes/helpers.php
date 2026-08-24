<?php

/**
 * Global helper functions for Beplus Manager.
 *
 * @package BeplusManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'beplus_manager_get_settings' ) ) {
	/**
	 * Get the plugin settings array.
	 *
	 * @return array<string,mixed>
	 */
	function beplus_manager_get_settings(): array {
		$defaults = array(
			'github_token'     => '',
			'github_client_id' => '',
			'webhook_secret'   => '',
			'packages'         => array(),
		);
		$saved = get_option( 'beplus_manager_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}
}

if ( ! function_exists( 'beplus_manager_update_settings' ) ) {
	/**
	 * Persist the plugin settings array.
	 *
	 * @param array<string,mixed> $settings
	 */
	function beplus_manager_update_settings( array $settings ): void {
		update_option( 'beplus_manager_settings', $settings );
	}
}

if ( ! function_exists( 'beplus_manager_backup_dir' ) ) {
	/**
	 * Absolute path to the backups directory.
	 */
	function beplus_manager_backup_dir(): string {
		$dir = WP_CONTENT_DIR . '/beplus-manager-backups';
		wp_mkdir_p( $dir );
		return $dir;
	}
}

if ( ! function_exists( 'beplus_manager_log' ) ) {
	/**
	 * Append a line to the plugin activity log.
	 */
	function beplus_manager_log( string $message, string $level = 'info' ): void {
		$entry = array(
			'time'  => current_time( 'mysql' ),
			'level' => $level,
			'msg'   => $message,
		);
		$log = get_option( 'beplus_manager_log', array() );
		array_unshift( $log, $entry );
		$log = array_slice( $log, 0, 100 );
		update_option( 'beplus_manager_log', $log );
	}
}
