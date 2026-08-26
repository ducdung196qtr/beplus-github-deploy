<?php

namespace BeplusManager\Update;

/**
 * Self-updater — lets this plugin update itself from GitHub Releases,
 * as WordPress.org no longer accepts storefront / plugin-deploy plugins.
 *
 * It hooks into WordPress' plugin update transient: when a newer release
 * exists on GitHub, it surfaces an update in Plugins → Installed Plugins
 * (and allows one-click update, like a wp.org-hosted plugin).
 *
 * @package BeplusManager
 */
class SelfUpdater {

	/**
	 * GitHub repo that hosts the releases, e.g. `owner/repo`.
	 * Overridable via constant BEPLUS_MANAGER_SELF_UPDATE_REPO.
	 */
	const REPO = 'ducdung196qtr/beplus-github-deploy';

	/**
	 * How long to cache the "latest release" lookup (transient).
	 */
	const CACHE_MINUTES = 30;

	/**
	 * Hook into WordPress to serve update metadata.
	 */
	public function register(): void {
		// Serve update info for the "plugins" list + update check.
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		// Provide the download package when WordPress asks for it.
		add_filter( 'upgrader_package_options', array( $this, 'resolve_package_source' ) );
	}

	/**
	 * Query the GitHub Releases API for the latest release.
	 *
	 * @return array{version?:string,url?:string,zipball?:string,notes?:string}|null
	 */
	private function latest_release() {
		$cache = get_transient( 'beplus_manager_self_update' );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		$token = $this->token();
		$url   = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';
		$args  = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Beplus-Manager',
			),
		);
		if ( $token ) {
			$args['headers']['Authorization']       = 'Bearer ' . $token;
			$args['headers']['X-GitHub-Api-Version'] = '2022-11-28';
		}
		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			// Cache a negative result briefly so we don't hammer GitHub.
			set_transient( 'beplus_manager_self_update', array(), self::CACHE_MINUTES * 60 );
			return null;
		}
		$body  = json_decode( wp_remote_retrieve_body( $res ), true );
		$tag   = $body['tag_name'] ?? '';
		$match = array();
		if ( ! preg_match( '/(\d+\.\d+\.\d+)/', (string) $tag, $match ) ) {
			set_transient( 'beplus_manager_self_update', array(), self::CACHE_MINUTES * 60 );
			return null;
		}
		$data = array(
			'version' => $match[1],          // "1.0.0"
			'url'     => (string) ( $body['html_url'] ?? '' ),
			'notes'   => (string) ( $body['body'] ?? '' ),
			'zipball' => $this->release_zipball( $body ),
		);
		set_transient( 'beplus_manager_self_update', $data, self::CACHE_MINUTES * 60 );
		return $data;
	}

	/**
	 * Build the download URL for a release.
	 *
	 * Prefers a release *asset* zip (the packaged plugin, with the correct
	 * `beplus-github-deploy/` root folder). Falls back to GitHub's codeload
	 * tarball, which WordPress can also install (it strips the owner-repo-sha
	 * wrapper automatically) — but only when no asset zip is attached.
	 *
	 * @param array<string,mixed> $release GitHub release payload.
	 */
	private function release_zipball( array $release ): string {
		$tag = (string) ( $release['tag_name'] ?? '' );
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ! empty( $asset['browser_download_url'] ) && preg_match( '/\.zip$/i', (string) $asset['name'] ) ) {
					return (string) $asset['browser_download_url'];
				}
			}
		}
		return 'https://codeload.github.com/' . self::REPO . '/zip/refs/tags/' . rawurlencode( $tag );
	}

	/**
	 * Inject update info when WordPress checks for plugin updates.
	 *
	 * @param mixed $transient
	 *
	 * @return mixed
	 */
	public function check_update( $transient ) {
		// Only act when WordPress is genuinely checking / has a transient.
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$slug    = basename( BEPLUS_MANAGER_DIR );
		$plugin  = plugin_basename( BEPLUS_MANAGER_FILE );

		$release = $this->latest_release();
		if ( ! $release || empty( $release['version'] ) ) {
			return $transient;
		}

		// Compare against the currently installed version.
		$installed = BEPLUS_MANAGER_VERSION;
		if ( version_compare( $installed, $release['version'], '>=' ) ) {
			// Remove any previously served update so it doesn't linger.
			if ( isset( $transient->no_update ) && isset( $transient->no_update[ $plugin ] ) ) {
				unset( $transient->no_update[ $plugin ] );
			}
			return $transient;
		}

		$update = (object) array(
			'id'          => $slug,
			'slug'        => $slug,
			'plugin'      => $plugin,
			'new_version' => $release['version'],
			'url'         => $release['url'] ?: 'https://github.com/' . self::REPO,
			'package'     => $release['zipball'],
			'icons'       => array(),
			'banners'     => array(),
			'banners_rtl' => array(),
			'requires'    => '6.0',
			'requires_php'=> '7.4',
			'tested'      => '6.6',
		);

		// Expose to the "check for updates" transient so it appears + installs.
		if ( ! isset( $transient->response ) ) {
			$transient->response = array();
		}
		$transient->response[ $plugin ] = $update;

		// Remove from no_update (seen as "current" otherwise).
		if ( isset( $transient->no_update ) && isset( $transient->no_update[ $plugin ] ) ) {
			unset( $transient->no_update[ $plugin ] );
		}

		return $transient;
	}

	/**
	 * GitHub codeload serves the archive as a single `<owner>-<repo>-<sha>/`
	 * root folder. WordPress expects the plugin's main folder (the slug) at
	 * the root of the zip. Wrap it so the update installs into the right
	 * folder. For this repo the bootstrap sits at the repo root, so we leave
	 * the package as-is is fine — but to be robust we keep the upgrader's
	 * default behaviour (no transformation needed for a root-level plugin).
	 *
	 * This hook exists as a safety net; most of the time no change is needed.
	 *
	 * @param array<string,mixed> $options
	 *
	 * @return array<string,mixed>
	 */
	public function resolve_package_source( array $options ): array {
		return $options;
	}

	/**
	 * Stored GitHub token to authenticate private/rate-limited API calls.
	 */
	private function token(): string {
		$settings = get_option( 'beplus_manager_settings', array() );
		return isset( $settings['github_token'] ) ? (string) $settings['github_token'] : '';
	}
}
