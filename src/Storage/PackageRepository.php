<?php

namespace BeplusManager\Storage;

/**
 * Persists package definitions and settings in a single WP option.
 */
class PackageRepository {

	/** @var array<string,mixed> */
	private $settings;

	public function __construct() {
		$this->settings = beplus_manager_get_settings();
	}

	/**
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function get_setting( string $key, $default = '' ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * @param mixed $value
	 */
	public function set_setting( string $key, $value ): void {
		$this->settings[ $key ] = $value;
		beplus_manager_update_settings( $this->settings );
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function all_packages(): array {
		$packages = $this->settings['packages'] ?? array();
		return is_array( $packages ) ? $packages : array();
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function get_package( string $slug ): ?array {
		$packages = $this->all_packages();
		return $packages[ $slug ] ?? null;
	}

	/**
	 * Upsert a package by its slug.
	 *
	 * @param array<string,mixed> $package
	 */
	public function save_package( array $package ): void {
		$packages           = $this->all_packages();
		$slug               = $package['slug'] ?? '';
		if ( ! $slug ) {
			return;
		}
		$package['updated_at'] = current_time( 'mysql' );
		$packages[ $slug ]     = $package;
		$this->settings['packages'] = $packages;
		beplus_manager_update_settings( $this->settings );
	}

	public function delete_package( string $slug ): void {
		$packages = $this->all_packages();
		unset( $packages[ $slug ] );
		$this->settings['packages'] = $packages;
		beplus_manager_update_settings( $this->settings );
	}
}
