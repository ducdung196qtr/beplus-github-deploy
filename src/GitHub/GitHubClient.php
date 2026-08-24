<?php

namespace BeplusManager\GitHub;

/**
 * Thin client for the GitHub REST API.
 */
class GitHubClient {

	// Used to fetch repo source (zipball/raw) which lives on third-party
	// hosts by design (this is a deploy tool, not offloading site assets).
	// phpcs:disable WordPress.WP.AlternativeFunctions,PluginCheck.CodeAnalysis.Offloading

	public function __construct() {
	}

	/**
	 * Verify a GitHub token by fetching the authenticated user.
	 *
	 * @return array{ok:bool,login?:string,message:string}
	 */
	public function test_token( string $token ): array {
		$response = $this->request( 'https://api.github.com/user', array(), $token );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return array(
				'ok'      => false,
				'message' => $body['message'] ?? 'Token invalid (HTTP ' . $code . ')',
			);
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array(
			'ok'      => true,
			'login'   => $body['login'] ?? '',
			'message' => 'Token valid — authenticated as @' . ( $body['login'] ?? 'unknown' ),
		);
	}

	/**
	 * Create or update a GitHub repo webhook that points back to this site.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function ensure_webhook( string $repository, string $secret, string $token ): array {
		$url = 'https://api.github.com/repos/' . $repository . '/hooks';
		$callback = rest_url( 'beplus-github-deploy/v1/webhook' );

		// List existing hooks.
		$resp = $this->request( $url, array(), $token );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'message' => $resp->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( 200 !== $code && 404 !== $code ) {
			return array( 'ok' => false, 'message' => 'Cannot list webhooks (HTTP ' . $code . '). Token needs "admin:repo_hook" scope.' );
		}
		$hooks = json_decode( wp_remote_retrieve_body( $resp ), true );
		$hook_id = 0;
		if ( is_array( $hooks ) ) {
			foreach ( $hooks as $hook ) {
				if ( ( $hook['config']['url'] ?? '' ) === $callback ) {
					$hook_id = (int) ( $hook['id'] ?? 0 );
					break;
				}
			}
		}

		$payload = wp_json_encode( array(
			'name'   => 'web',
			'active' => true,
			'events' => array( 'push' ),
			'config' => array(
				'url'          => $callback,
				'content_type' => 'json',
				'secret'       => $secret,
			),
		) );

		if ( $hook_id ) {
			$resp = $this->request( $url . '/' . $hook_id, array( 'method' => 'PATCH', 'body' => $payload ), $token );
		} else {
			$resp = $this->request( $url, array( 'method' => 'POST', 'body' => $payload ), $token );
		}
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'message' => $resp->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		if ( 201 !== $code && 200 !== $code ) {
			$body = json_decode( wp_remote_retrieve_body( $resp ), true );
			return array(
				'ok'      => false,
				'message' => 'Could not create webhook (HTTP ' . $code . '): ' . ( $body['message'] ?? 'unknown error' ),
			);
		}
		return array( 'ok' => true, 'message' => $hook_id ? 'Webhook updated.' : 'Webhook created on GitHub.' );
	}

	/**
	 * Remove the Beplus GitHub Deploy webhook from a GitHub repo.
	 *
	 * @return array{ok:bool,message:string}
	 */
	public function delete_webhook( string $repository, string $token ): array {
		$url      = 'https://api.github.com/repos/' . $repository . '/hooks';
		$callback = rest_url( 'beplus-github-deploy/v1/webhook' );
		$resp     = $this->request( $url, array(), $token );
		if ( is_wp_error( $resp ) ) {
			return array( 'ok' => false, 'message' => $resp->get_error_message() );
		}
		$hooks = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( is_array( $hooks ) ) {
			foreach ( $hooks as $hook ) {
				if ( ( $hook['config']['url'] ?? '' ) === $callback ) {
					$this->request( $url . '/' . (int) $hook['id'], array( 'method' => 'DELETE' ), $token );
					break;
				}
			}
		}
		return array( 'ok' => true, 'message' => 'Webhook removed.' );
	}

	/**
	 * List repositories for the authenticated user (requires token).
	 *
	 * @return array{ok:bool,message:string,repos?:array<int,array<string,string|bool>>}
	 */
	public function list_repositories( string $token, string $query = '' ): array {
		$url = 'https://api.github.com/user/repos?per_page=100&sort=updated';
		$response = $this->request( $url, array(), $token );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message(), 'repos' => array() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array(
				'ok'      => false,
				'message' => 'GitHub API error (HTTP ' . $code . ')',
				'repos'   => array(),
			);
		}
		$body  = json_decode( wp_remote_retrieve_body( $response ), true );
		$repos = array();
		if ( is_array( $body ) ) {
			foreach ( $body as $repo ) {
				$name = $repo['full_name'] ?? '';
				if ( $query && stripos( $name, $query ) === false ) {
					continue;
				}
				$repos[] = array(
					'name'    => $name,
					'private' => ! empty( $repo['private'] ),
					'branch'  => $repo['default_branch'] ?? 'main',
				);
			}
		}
		return array( 'ok' => true, 'message' => '', 'repos' => $repos );
	}

	/**
	 * Download a repository as a tarball zip via the GitHub codeload endpoint.
	 * Works for public repos without a token; private repos require a token.
	 *
	 * @return string|\WP_Error Path to the downloaded file.
	 */
	public function download_zipball( string $repository, string $branch, string $token ) {
		$url = 'https://codeload.github.com/' . $repository . '/zip/refs/heads/' . rawurlencode( $branch );
		$response = $this->request( $url, array( 'timeout' => 120 ), $token );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'beplus_manager_download',
				'Failed to download repository (HTTP ' . $code . '). Check branch name and token permissions.'
			);
		}
		$body   = wp_remote_retrieve_body( $response );
		$tmp    = $this->temp_file();
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		$result = file_put_contents( $tmp, $body );
		if ( false === $result ) {
			return new \WP_Error( 'beplus_manager_write', 'Could not write temporary archive.' );
		}
		return $tmp;
	}

	/**
	 * Create a unique temp file path without relying on wp_tempnam().
	 *
	 * @return string|\WP_Error
	 */
	private function temp_file() {
		$dir = wp_upload_dir();
		if ( is_wp_error( $dir ) || empty( $dir['path'] ) || ! is_writable( $dir['path'] ) ) {
			$fallback = sys_get_temp_dir();
			if ( ! $fallback || ! is_writable( $fallback ) ) {
				return new \WP_Error( 'beplus_manager_tmp', 'No writable temporary directory.' );
			}
			$dir = array( 'path' => $fallback );
		}
		$file = $dir['path'] . '/beplus-github-deploy-' . wp_generate_password( 12, false ) . '.zip';
		return $file;
	}

	/**
	 * Start the GitHub Device Authorization Flow.
	 *
	 * @param string $client_id OAuth App client ID.
	 *
	 * @return array{ok:bool,message?:string,user_code?:string,verification_uri?:string,verification_uri_complete?:string,device_code?:string,expires_in?:int}
	 */
	public function start_device_flow( string $client_id ): array {
		$response = wp_remote_post( 'https://github.com/login/device/code', array(
			'timeout' => 30,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'Beplus-Manager',
			),
			'body' => array(
				'client_id' => $client_id,
				'scope'     => 'repo',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || empty( $body['device_code'] ) ) {
			return array(
				'ok'      => false,
				'message' => $body['error_description'] ?? ( $body['error'] ?? 'Device flow failed (HTTP ' . $code . ')' ),
			);
		}
		// Keep the device code in a transient while the user approves.
		set_transient( 'beplus_manager_device_code', $body['device_code'], (int) ( $body['expires_in'] ?? 900 ) );
		return array(
			'ok'                       => true,
			'user_code'                => $body['user_code'] ?? '',
			'verification_uri'         => $body['verification_uri'] ?? 'https://github.com/login/device',
			'verification_uri_complete'=> $body['verification_uri_complete'] ?? '',
			'expires_in'               => (int) ( $body['expires_in'] ?? 900 ),
		);
	}

	/**
	 * Poll GitHub for the access token after the user approves.
	 *
	 * @param string $client_id OAuth App client ID.
	 *
	 * @return array{ok:bool,message?:string,token?:string}
	 */
	public function poll_device_token( string $client_id ): array {
		$device_code = get_transient( 'beplus_manager_device_code' );
		if ( ! $device_code ) {
			return array( 'ok' => false, 'message' => 'Device flow expired. Please start again.' );
		}
		$response = wp_remote_post( 'https://github.com/login/oauth/access_token', array(
			'timeout' => 30,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'Beplus-Manager',
			),
			'body' => array(
				'client_id'   => $client_id,
				'device_code' => $device_code,
				'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Still waiting for the user.
		if ( isset( $body['error'] ) && 'authorization_pending' === $body['error'] ) {
			return array( 'ok' => false, 'pending' => true, 'message' => 'Waiting for approval…' );
		}
		if ( isset( $body['error'] ) ) {
			return array( 'ok' => false, 'message' => $body['error_description'] ?? $body['error'] );
		}
		if ( empty( $body['access_token'] ) ) {
			return array( 'ok' => false, 'message' => 'No token received.' );
		}

		$token = $body['access_token'];
		// Validate the token before storing it.
		$check = $this->test_token( $token );
		if ( ! $check['ok'] ) {
			return array( 'ok' => false, 'message' => 'Received token is not valid: ' . $check['message'] );
		}

		delete_transient( 'beplus_manager_device_code' );
		return array( 'ok' => true, 'token' => $token, 'login' => $check['login'] ?? '' );
	}

	/**
	 * Detect package type + slug + subdirectory from a repository's file tree.
	 * Uses the git trees API (one call) then reads header files via raw URLs.
	 *
	 * @return array{ok:bool,type:string,slug:string,subdirectory:string,branch:string,message?:string}
	 */
	public function detect_package_info( string $repository, string $branch, string $token ): array {
		$url = 'https://api.github.com/repos/' . $repository . '/git/trees/' . rawurlencode( $branch ) . '?recursive=1';
		$response = $this->request( $url, array(), $token );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'type' => '', 'slug' => '', 'subdirectory' => '', 'branch' => $branch, 'message' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array( 'ok' => false, 'type' => '', 'slug' => '', 'subdirectory' => '', 'branch' => $branch, 'message' => 'GitHub API error (HTTP ' . $code . ')' );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$paths = array();
		foreach ( (array) ( $body['tree'] ?? array() ) as $entry ) {
			if ( 'blob' === ( $entry['type'] ?? '' ) && ! empty( $entry['path'] ) ) {
				$paths[] = $entry['path'];
			}
		}
		if ( empty( $paths ) ) {
			return array( 'ok' => false, 'type' => '', 'slug' => '', 'subdirectory' => '', 'branch' => $branch, 'message' => 'Repository is empty.' );
		}

		// A single top-level directory? Treat it as the package root (subdirectory).
		$top_level = array();
		foreach ( $paths as $path ) {
			$first = strtok( $path, '/' );
			if ( false !== $first ) {
				$top_level[ $first ] = true;
			}
		}
		$top_names = array_keys( $top_level );
		$subdir    = '';
		if ( 1 === count( $top_names ) && false === strpos( $top_names[0], '.' ) ) {
			$subdir = $top_names[0];
		}

		$root    = $subdir ? $subdir . '/' : '';
		$is_theme  = false;
		$is_plugin = false;

		// Theme: style.css with a "Theme Name" header in the package root.
		$style_css = $root . 'style.css';
		if ( in_array( $style_css, $paths, true ) ) {
			$head = $this->fetch_raw_head( $repository, $branch, $style_css, $token );
			if ( preg_match( '/Theme Name\s*:/i', $head ) ) {
				$is_theme = true;
			}
		}

		// Plugin: any .php file at the package root with a "Plugin Name" header.
		if ( ! $is_theme ) {
			foreach ( $paths as $path ) {
				if ( $root && 0 !== strpos( $path, $root ) ) {
					continue;
				}
				$rel = $root ? substr( $path, strlen( $root ) ) : $path;
				if ( false !== strpos( $rel, '/' ) || '.php' !== strtolower( substr( $rel, -4 ) ) ) {
					continue;
				}
				$head = $this->fetch_raw_head( $repository, $branch, $path, $token );
				if ( preg_match( '/Plugin Name\s*:/i', $head ) ) {
					$is_plugin = true;
					break;
				}
			}
		}

		$slug = $subdir ? $subdir : basename( $repository );
		if ( $is_theme ) {
			$type = 'theme';
		} elseif ( $is_plugin ) {
			$type = 'plugin';
		} else {
			$type = 'plugin'; // Default fallback.
		}

		return array(
			'ok'           => true,
			'type'         => $type,
			'slug'         => $slug,
			'subdirectory' => $subdir,
			'branch'       => $branch,
			'message'      => $is_theme ? 'Detected WordPress theme.' : ( $is_plugin ? 'Detected WordPress plugin.' : 'Could not detect type — defaulting to plugin.' ),
		);
	}

	/**
	 * Fetch the first ~4KB of a file from the raw GitHub CDN.
	 */
	private function fetch_raw_head( string $repository, string $branch, string $path, string $token ): string {
		$url = 'https://raw.githubusercontent.com/' . $repository . '/' . rawurlencode( $branch ) . '/' . ltrim( $path, '/' );
		$response = $this->request( $url, array( 'timeout' => 30 ), $token );
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body = wp_remote_retrieve_body( $response );
		return substr( $body, 0, 4096 );
	}

	/**
	 * Perform an HTTP request with optional token auth.
	 *
	 * @param array<string,mixed> $args
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	private function request( string $url, array $args = array(), string $token = '' ) {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'Beplus-Manager',
		);
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
			$headers['X-GitHub-Api-Version'] = '2022-11-28';
		}
		$args = wp_parse_args(
			$args,
			array(
				'timeout'     => 30,
				'redirection' => 5,
				'headers'     => $headers,
			)
		);
		$args['headers'] = array_merge( $headers, $args['headers'] ?? array() );
		return wp_remote_get( $url, $args );
	}
}
