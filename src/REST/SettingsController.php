<?php

namespace BeplusManager\REST;

use BeplusManager\Backup\BackupManager;
use BeplusManager\Deploy\Deployer;
use BeplusManager\GitHub\GitHubClient;
use BeplusManager\Storage\PackageRepository;

/**
 * Admin REST endpoints for settings, packages, deploy and rollback.
 *
 * File operations below (rename/readfile/unlink) are part of the
 * deploy/backup feature which WP_Filesystem does not cover.
 * phpcs:disable WordPress.WP.AlternativeFunctions
 */
class SettingsController {

	const NS = 'beplus-github-deploy/v1';

	/** @var PackageRepository */
	private $packages;

	/** @var GitHubClient */
	private $github;

	/** @var Deployer */
	private $deployer;

	/** @var BackupManager */
	private $backup;

	public function __construct( PackageRepository $packages, GitHubClient $github, Deployer $deployer, BackupManager $backup ) {
		$this->packages = $packages;
		$this->github   = $github;
		$this->deployer = $deployer;
		$this->backup   = $backup;
	}

	public function register_routes(): void {
		register_rest_route( self::NS, '/settings', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_settings' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/settings', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_settings' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/github/test', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'test_github' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/github/device/start', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'device_start' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/github/device/poll', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'device_poll' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/github/repos', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_repos' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/github/detect', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'detect_package' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/packages', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'save_package' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/packages/(?P<slug>[a-zA-Z0-9-_]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_package' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/deploy', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'deploy' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/rollback', array(
			'methods'             => \WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'rollback' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/logs', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_logs' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/logs', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'clear_logs' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/backups', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_backups' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/backups/import', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'import_backup' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/backups/restore', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'restore_from_upload' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/backups/(?P<name>[a-zA-Z0-9-_]+)', array(
			'methods'             => \WP_REST_Server::DELETABLE,
			'callback'            => array( $this, 'delete_backup' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/backups/(?P<name>[a-zA-Z0-9-_]+)/restore', array(
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'restore_backup' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/backups/(?P<name>[a-zA-Z0-9-_]+)/download', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'download_backup' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
		register_rest_route( self::NS, '/progress/(?P<slug>[a-zA-Z0-9_-]+)', array(
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_progress' ),
			'permission_callback' => array( $this, 'check_admin' ),
		) );
	}

	public function check_admin(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_settings(): \WP_REST_Response {
		$settings                  = beplus_manager_get_settings();
		$settings['github_client_id'] = (string) ( $settings['github_client_id'] ?? '' );
		$settings['github_login']  = (string) ( $settings['github_login'] ?? '' );
		$settings['repo_mode']     = (string) ( $settings['repo_mode'] ?? 'auto' );
		$settings['token']         = $settings['github_token'] ? 'set' : '';
		unset( $settings['github_token'] );

		$packages = $this->packages->all_packages();
		// Filter: only show packages belonging to the currently connected GitHub account.
		$login    = (string) $settings['github_login'];
		if ( $login ) {
			$packages = array_filter( $packages, function ( $pkg ) use ( $login ) {
				return isset( $pkg['github_login'] ) && $pkg['github_login'] === $login;
			} );
		}

		// Enrich each package with rollback availability (does a backup file exist?).
		foreach ( $packages as &$pkg ) {
			$type            = $pkg['type'] ?? 'plugin';
			$slug            = $pkg['slug'] ?? '';
			$latest          = $slug ? $this->backup->latest_backup( $type, $slug ) : null;
			$pkg['has_backup'] = $latest ? true : false;
			$pkg['backup_dir'] = $latest ? str_replace( ABSPATH, '', $latest ) : '';
			$pkg['backup_time'] = $latest ? wp_date( 'Y-m-d H:i:s', (int) filemtime( $latest ) ) : '';
		}
		unset( $pkg );

		return new \WP_REST_Response( array(
			'settings' => $settings,
			'packages' => $packages,
			'log'      => get_option( 'beplus_manager_log', array() ),
		) );
	}

	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$token     = sanitize_text_field( (string) $request->get_param( 'github_token' ) );
		$secret    = sanitize_text_field( (string) $request->get_param( 'webhook_secret' ) );
		$client_id = sanitize_text_field( (string) $request->get_param( 'github_client_id' ) );
		$clear     = (bool) $request->get_param( 'github_token_clear' );
		$repo_mode = sanitize_text_field( (string) $request->get_param( 'repo_mode' ) );

		if ( $clear ) {
			$this->packages->set_setting( 'github_token', '' );
		}
		if ( $token ) {
			$this->packages->set_setting( 'github_token', $token );
			$check = $this->github->test_token( $token );
			if ( ! empty( $check['ok'] ) && ! empty( $check['login'] ) ) {
				$this->packages->set_setting( 'github_login', $check['login'] );
			}
			// The repo-loading preference is locked in at token save time.
			if ( 'manual' === $repo_mode ) {
				$this->packages->set_setting( 'repo_mode', 'manual' );
			} else {
				$this->packages->set_setting( 'repo_mode', 'auto' );
			}
		}
		if ( $secret ) {
			$this->packages->set_setting( 'webhook_secret', $secret );
		}
		if ( $client_id ) {
			$this->packages->set_setting( 'github_client_id', $client_id );
		}
		return new \WP_REST_Response( array( 'ok' => true ) );
	}

	public function test_github( \WP_REST_Request $request ): \WP_REST_Response {
		$token = sanitize_text_field( (string) $request->get_param( 'token' ) );
		if ( ! $token ) {
			$token = (string) $this->packages->get_setting( 'github_token', '' );
		}
		if ( ! $token ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'No token provided.' ), 400 );
		}
		$result = $this->github->test_token( $token );
		if ( ! empty( $result['ok'] ) ) {
			$this->packages->set_setting( 'github_token', $token );
			if ( ! empty( $result['login'] ) ) {
				$this->packages->set_setting( 'github_login', $result['login'] );
			}
		}
		return new \WP_REST_Response( $result );
	}

	public function device_start(): \WP_REST_Response {
		$client_id = $this->resolve_client_id();
		if ( ! $client_id ) {
			return new \WP_REST_Response(
				array( 'ok' => false, 'message' => 'GitHub OAuth App Client ID is not configured. Contact the plugin developer.' ),
				400
			);
		}
		return new \WP_REST_Response( $this->github->start_device_flow( $client_id ) );
	}

	public function device_poll(): \WP_REST_Response {
		$client_id = $this->resolve_client_id();
		if ( ! $client_id ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'GitHub OAuth App Client ID is not configured.' ), 400 );
		}
		$result = $this->github->poll_device_token( $client_id );
		if ( ! empty( $result['ok'] ) && ! empty( $result['token'] ) ) {
			$this->packages->set_setting( 'github_token', $result['token'] );
		}
		return new \WP_REST_Response( $result );
	}

	/**
	 * Client ID = stored override (advanced) or the constant shipped with the plugin.
	 */
	private function resolve_client_id(): string {
		$stored = (string) $this->packages->get_setting( 'github_client_id', '' );
		if ( $stored ) {
			return $stored;
		}
		return defined( 'BEPLUS_MANAGER_GITHUB_CLIENT_ID' ) ? (string) BEPLUS_MANAGER_GITHUB_CLIENT_ID : '';
	}

	public function list_repos( \WP_REST_Request $request ): \WP_REST_Response {
		$token = (string) $this->packages->get_setting( 'github_token', '' );
		$query = sanitize_text_field( (string) $request->get_param( 'q' ) );
		return new \WP_REST_Response( $this->github->list_repositories( $token, $query ) );
	}

	public function detect_package( \WP_REST_Request $request ): \WP_REST_Response {
		$token      = (string) $this->packages->get_setting( 'github_token', '' );
		$repository = sanitize_text_field( (string) $request->get_param( 'repo' ) );
		$branch     = sanitize_text_field( (string) $request->get_param( 'branch' ) );
		if ( ! $repository || ! $branch ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'repo and branch are required.' ), 400 );
		}
		return new \WP_REST_Response( $this->github->detect_package_info( $repository, $branch, $token ) );
	}

	public function save_package( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$slug   = sanitize_file_name( $params['slug'] ?? '' );
		$type   = in_array( $params['type'] ?? '', array( 'plugin', 'theme' ), true ) ? $params['type'] : 'plugin';

		if ( ! $slug ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Slug is required.' ), 400 );
		}
		$package = array(
			'slug'         => $slug,
			'type'         => $type,
			'repository'   => sanitize_text_field( $params['repository'] ?? '' ),
			'branch'       => sanitize_text_field( $params['branch'] ?? 'main' ),
			'subdirectory' => sanitize_text_field( $params['subdirectory'] ?? '' ),
			'webhook'      => ! empty( $params['webhook'] ),
			'github_login' => (string) $this->packages->get_setting( 'github_login', '' ),
		);
		if ( empty( $package['repository'] ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Repository is required.' ), 400 );
		}

		// Sync the GitHub webhook: AUTO on → create/update hook; off → remove it.
		$old = $this->packages->get_package( $slug );
		$was = $old ? ! empty( $old['webhook'] ) : false;
		$now = $package['webhook'];
		if ( $now !== $was ) {
			$token  = (string) $this->packages->get_setting( 'github_token', '' );
			$secret = (string) $this->packages->get_setting( 'webhook_secret', '' );
			if ( ! $secret ) {
				$secret = wp_generate_password( 32, false );
				$this->packages->set_setting( 'webhook_secret', $secret );
			}
			if ( $now ) {
				$hook = $this->github->ensure_webhook( $package['repository'], $secret, $token );
				if ( ! $hook['ok'] ) {
					return new \WP_REST_Response( array(
						'ok'      => false,
						'message' => 'Package saved but webhook failed: ' . $hook['message'],
						'package' => $package,
					), 400 );
				}
			} else {
				$this->github->delete_webhook( $package['repository'], $token );
			}
		}

		$this->packages->save_package( $package );
		return new \WP_REST_Response( array( 'ok' => true, 'package' => $package ) );
	}

	public function delete_package( \WP_REST_Request $request ): \WP_REST_Response {
		$slug = sanitize_file_name( $request['slug'] );
		$pkg  = $this->packages->get_package( $slug );

		// Note: we deliberately DO NOT delete the plugin/theme files from disk.
		// Removing a package only unlinks it from Beplus GitHub Deploy, so the files
		// stay live on the site (e.g. the deployed theme/plugin keeps working).
		$this->packages->delete_package( $slug );
		beplus_manager_log( "Removed package {$slug} (files left in place).", 'info' );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => "Package {$slug} removed. Files were left in place." ) );
	}

	public function deploy( \WP_REST_Request $request ): \WP_REST_Response {
		$slug = sanitize_file_name( $request->get_param( 'slug' ) );
		$pkg  = $this->packages->get_package( $slug );
		if ( ! $pkg ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Package not found.' ), 404 );
		}
		$pkg['token'] = (string) $this->packages->get_setting( 'github_token', '' );
		$result       = $this->deployer->deploy( $pkg );
		$status       = $result['ok'] ? 200 : 500;
		return new \WP_REST_Response( $result, $status );
	}

	public function rollback( \WP_REST_Request $request ): \WP_REST_Response {
		$slug = sanitize_file_name( $request->get_param( 'slug' ) );
		$type = sanitize_text_field( $request->get_param( 'type' ) );
		$pkg  = $this->packages->get_package( $slug );
		if ( $pkg ) {
			$type = $pkg['type'];
		}
		$this->deployer->set_progress( $slug, 20, 'Locating backup…' );
		$result = $this->backup->restore_package( $type, $slug );
		if ( is_wp_error( $result ) ) {
			$this->deployer->set_progress( $slug, 0, 'Rollback failed: ' . $result->get_error_message(), true );
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 500 );
		}
		$this->deployer->set_progress( $slug, 100, 'Rollback complete!' );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => 'Rollback complete.' ) );
	}

	public function get_progress( \WP_REST_Request $request ): \WP_REST_Response {
		$slug = sanitize_file_name( $request['slug'] );
		return new \WP_REST_Response( $this->deployer->get_progress( $slug ) );
	}

	public function get_logs(): \WP_REST_Response {
		return new \WP_REST_Response( array( 'log' => get_option( 'beplus_manager_log', array() ) ) );
	}

	public function clear_logs(): \WP_REST_Response {
		delete_option( 'beplus_manager_log' );
		beplus_manager_log( 'Activity log cleared.', 'info' );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => 'Activity log cleared.' ) );
	}

	public function list_backups(): \WP_REST_Response {
		$root    = beplus_manager_backup_dir();
		$backups = array();
		if ( is_dir( $root ) ) {
			foreach ( glob( $root . '/*' ) ?: array() as $dir ) {
				if ( ! is_dir( $dir ) || 'trash-' === basename( $dir ) || 0 === strpos( basename( $dir ), 'trash-' ) ) {
					continue;
				}
				$backups[] = array(
					'name' => basename( $dir ),
					'path' => str_replace( ABSPATH, '', $dir ),
					'size' => $this->dir_size( $dir ),
					'time' => wp_date( 'Y-m-d H:i:s', (int) filemtime( $dir ) ),
				);
			}
			usort( $backups, static function ( $a, $b ) {
				return strcmp( $b['name'], $a['name'] );
			} );
		}
		return new \WP_REST_Response( array( 'backups' => $backups ) );
	}

	public function delete_backup( \WP_REST_Request $request ): \WP_REST_Response {
		$name = sanitize_file_name( $request['name'] );
		if ( ! preg_match( '/^(plugin|theme)-[a-zA-Z0-9_-]+-\d{14}$/', $name ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid backup name.' ), 400 );
		}
		$root = beplus_manager_backup_dir();
		$dir  = $root . '/' . $name;
		if ( ! is_dir( $dir ) || 0 === strpos( $name, 'trash-' ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Backup not found.' ), 404 );
		}
		$this->backup->rrmdir( $dir );
		beplus_manager_log( "Deleted backup {$name} manually.", 'info' );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => "Backup {$name} deleted." ) );
	}

	/**
	 * Restore a SPECIFIC backup (already on disk) into the live package dir.
	 * The current live version is snapshotted first so the user can roll back
	 * again after the restore.
	 */
	public function restore_backup( \WP_REST_Request $request ): \WP_REST_Response {
		$name = sanitize_file_name( $request['name'] );
		if ( ! preg_match( '/^(plugin|theme)-(.+?)-\d{14}$/', $name, $m ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid backup name.' ), 400 );
		}
		$type = $m[1];
		$slug = $m[2];

		$root = beplus_manager_backup_dir();
		if ( ! is_dir( $root . '/' . $name ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Backup not found.' ), 404 );
		}

		// Snapshot current live version (keeps existing backups intact).
		$current = $this->backup->package_dir( $type, $slug );
		if ( is_dir( $current ) ) {
			$snap = $this->backup->snapshot_package( $type, $slug );
			if ( is_wp_error( $snap ) ) {
				return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Could not snapshot current version: ' . $snap->get_error_message() ), 400 );
			}
		}

		$ok = $this->backup->restore_specific_backup( $type, $slug, $name );
		if ( is_wp_error( $ok ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $ok->get_error_message() ), 400 );
		}

		beplus_manager_log( "Restored {$type} {$slug} from backup {$name}.", 'info' );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => "Restored {$type} {$slug} from backup {$name}." ) );
	}

	/**
	 * Import a backup ZIP uploaded by the user. The archive must contain a
	 * single top-level folder named like a backup dir (type-slug-timestamp),
	 * or a single top-level folder (which will be renamed to that pattern).
	 */
	public function import_backup( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->extract_backup_zip( $request );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}
		beplus_manager_log( "Imported backup {$result} from ZIP.", 'info' );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => "Backup {$result} imported." ) );
	}

	/**
	 * Upload a backup ZIP and restore (activate) that version immediately:
	 * import the archive, then roll the target plugin/theme directory back
	 * to the imported backup.
	 */
	public function restore_from_upload( \WP_REST_Request $request ): \WP_REST_Response {
		// Backup the current live version FIRST (so the user can roll back again).
		// Must run before importing, because import + delete_existing_backup would
		// otherwise wipe the freshly imported backup.
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		$type  = null;
		$slug  = null;
		if ( $file && ! empty( $file['name'] ) && preg_match( '/^(plugin|theme)-(.+?)-\d{14}(\.zip)?$/', $file['name'], $m ) ) {
			$type = $m[1];
			$slug = $m[2];
		}

		$had_live = false;
		if ( $type && $slug ) {
			$current  = $this->backup->package_dir( $type, $slug );
			$had_live = is_dir( $current );
			if ( $had_live ) {
				$b = $this->backup->backup_package( $type, $slug );
				if ( is_wp_error( $b ) ) {
					return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Could not back up current version: ' . $b->get_error_message() ), 400 );
				}
			}
		}

		$result = $this->extract_backup_zip( $request );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $result->get_error_message() ), 400 );
		}
		$name = $result;
		if ( ! preg_match( '/^(plugin|theme)-(.+?)-\d{14}$/', $name, $m ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => 'Invalid backup name.' ), 400 );
		}
		$type = $m[1];
		$slug = $m[2];

		// Swap the imported backup into the live directory.
		$ok = $this->backup->restore_specific_backup( $type, $slug, $name );
		if ( is_wp_error( $ok ) ) {
			return new \WP_REST_Response( array( 'ok' => false, 'message' => $ok->get_error_message() ), 400 );
		}

		beplus_manager_log( "Restored {$type} {$slug} from uploaded backup {$name}.", 'info' );
		return new \WP_REST_Response( array( 'ok' => true, 'message' => "Restored {$type} {$slug} from uploaded backup {$name}." ) );
	}

	/**
	 * Validate the uploaded ZIP and extract it into the backup root.
	 *
	 * @return string|\WP_Error Backup folder name.
	 */
	private function extract_backup_zip( \WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! $file || empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
			return new \WP_Error( 'beplus_manager_no_file', 'No file uploaded.' );
		}
		$tmp_name = $file['tmp_name'];
		$root     = beplus_manager_backup_dir();

		$tmp = $root . '/import-' . wp_generate_password( 8, false );
		wp_mkdir_p( $tmp );

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp_name ) ) {
			$this->backup->rrmdir( $tmp );
			return new \WP_Error( 'beplus_manager_bad_zip', 'Not a valid ZIP file.' );
		}
		$zip->extractTo( $tmp );
		$zip->close();

		// Find the single top-level folder.
		$entries = glob( $tmp . '/*' );
		if ( count( $entries ) !== 1 || ! is_dir( $entries[0] ) ) {
			$this->backup->rrmdir( $tmp );
			return new \WP_Error( 'beplus_manager_zip_structure', 'ZIP must contain exactly one top-level folder.' );
		}
		$inner = basename( $entries[0] );
		if ( ! preg_match( '/^(plugin|theme)-[a-zA-Z0-9_-]+-\d{14}$/', $inner ) ) {
			$this->backup->rrmdir( $tmp );
			return new \WP_Error( 'beplus_manager_zip_name', 'Folder name must look like plugin-slug-20260101120000 or theme-slug-20260101120000.' );
		}
		$dest = $root . '/' . $inner;
		if ( is_dir( $dest ) ) {
			$this->backup->rrmdir( $dest );
		}
		rename( $entries[0], $dest );
		$this->backup->rrmdir( $tmp );

		return $inner;
	}

	/**
	 * Serve a backup directory as a ZIP download.
	 */
	public function download_backup( \WP_REST_Request $request ): void {
		$name = sanitize_file_name( $request['name'] );
		if ( ! preg_match( '/^(plugin|theme)-[a-zA-Z0-9_-]+-\d{14}$/', $name ) ) {
			wp_die( 'Invalid backup name.' );
		}
		$root = beplus_manager_backup_dir();
		$dir  = $root . '/' . $name;
		if ( ! is_dir( $dir ) ) {
			wp_die( 'Backup not found.' );
		}

		$zip_path = $root . '/' . $name . '.zip';
		$zip      = new \ZipArchive();
		if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			wp_die( 'Could not create ZIP.' );
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $it as $item ) {
			$local = $name . '/' . substr( $item->getPathname(), strlen( $dir ) + 1 );
			if ( $item->isDir() ) {
				$zip->addEmptyDir( $local );
			} else {
				$zip->addFile( $item->getPathname(), $local );
			}
		}
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.zip"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		readfile( $zip_path );
		@unlink( $zip_path );
		exit;
	}

	private function dir_size( string $dir ): string {
		$bytes = 0;
		$it    = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $it as $item ) {
			$bytes += $item->getSize();
		}
		return size_format( $bytes );
	}
}
