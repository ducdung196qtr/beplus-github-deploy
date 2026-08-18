<?php

namespace BeplusManager\Deploy;

use BeplusManager\GitHub\GitHubClient;
use BeplusManager\Backup\BackupManager;

/**
 * Orchestrates a deploy: backup current version → download zipball → install.
 */
class Deployer {

	const PROGRESS_NS = 'beplus_manager_progress_';

	/** @var GitHubClient */
	private $github;

	/** @var BackupManager */
	private $backup;

	public function __construct( GitHubClient $github, BackupManager $backup ) {
		$this->github = $github;
		$this->backup = $backup;
	}

	/**
	 * Store deploy progress for a slug (shown as a progress bar in the UI).
	 */
	public function set_progress( string $slug, int $percent, string $message, bool $error = false ): void {
		set_transient( self::PROGRESS_NS . $slug, array(
			'percent' => max( 0, min( 100, $percent ) ),
			'message' => $message,
			'done'    => $percent >= 100 || $error,
			'error'   => $error,
		), 120 );
	}

	/**
	 * Read current progress for a slug.
	 *
	 * @return array
	 */
	public function get_progress( string $slug ): array {
		$data = get_transient( self::PROGRESS_NS . $slug );
		if ( ! is_array( $data ) ) {
			return array( 'percent' => 0, 'message' => '', 'done' => false, 'error' => false );
		}
		return $data;
	}

	public function clear_progress( string $slug ): void {
		delete_transient( self::PROGRESS_NS . $slug );
	}

	/**
	 * Deploy a repository to a plugin/theme directory.
	 *
	 * @param array $pkg { slug, type, repository, branch, token, subdirectory? }
	 * @return array{ok:bool,message:string,backup?:string}
	 */
	public function deploy( array $pkg ): array {
		$slug        = sanitize_file_name( $pkg['slug'] ?? '' );
		$type        = in_array( $pkg['type'] ?? '', array( 'plugin', 'theme' ), true ) ? $pkg['type'] : 'plugin';
		$repository  = sanitize_text_field( $pkg['repository'] ?? '' );
		$branch      = sanitize_text_field( $pkg['branch'] ?? 'main' );
		$token       = (string) ( $pkg['token'] ?? '' );
		$subdir      = $pkg['subdirectory'] ?? null;

		if ( ! $slug || ! $repository ) {
			return array( 'ok' => false, 'message' => 'Missing slug or repository.' );
		}

		$target = $this->backup->package_dir( $type, $slug );

		// Step 1: backup current version (rolling, keeps only one).
		$backup_result = null;
		if ( is_dir( $target ) ) {
			$this->set_progress( $slug, 10, 'Backing up current version…' );
			$backup_result = $this->backup->backup_package( $type, $slug );
			if ( is_wp_error( $backup_result ) ) {
				$this->set_progress( $slug, 0, 'Backup failed: ' . $backup_result->get_error_message(), true );
				return array( 'ok' => false, 'message' => 'Backup failed: ' . $backup_result->get_error_message() );
			}
		}

		// Step 2: download source from GitHub.
		$this->set_progress( $slug, 30, 'Downloading from GitHub…' );
		$zip = $this->github->download_zipball( $repository, $branch, $token );
		if ( is_wp_error( $zip ) ) {
			$this->set_progress( $slug, 0, 'Download failed: ' . $zip->get_error_message(), true );
			return array( 'ok' => false, 'message' => $zip->get_error_message() );
		}
		$this->set_progress( $slug, 45, 'Checking package type…' );

		// Step 2.5: verify the repository really IS the declared type
		// (theme vs plugin). Prevents installing a theme into plugins/
		// or a plugin into themes/ when the user picked the wrong type.
		$detected = $this->github->detect_package_info( $repository, $branch, $token );
		if ( ! empty( $detected['ok'] ) && ! empty( $detected['type'] ) ) {
			if ( $detected['type'] !== $type ) {
				$this->set_progress( $slug, 0, "Type mismatch: this repository is a {$detected['type']}, not a {$type}.", true );
				return array(
					'ok'      => false,
					'message' => "This repository is a {$detected['type']}, not a {$type}. Please edit the package and change its type to {$detected['type']}.",
				);
			}
		}
		// If detection failed, fall through (don't block the deploy — the
		// repo may simply lack detectable headers).

		$this->set_progress( $slug, 60, 'Downloaded — extracting…' );

		// Step 3: extract and install.
		$install = $this->install_from_zip( $zip, $type, $slug, $subdir );
		@unlink( $zip );
		if ( is_wp_error( $install ) ) {
			$this->set_progress( $slug, 0, 'Install failed: ' . $install->get_error_message(), true );
			return array( 'ok' => false, 'message' => $install->get_error_message() );
		}
		$this->set_progress( $slug, 100, 'Deploy complete!' );

		beplus_manager_log( "Deployed {$type} {$slug} from {$repository} ({$branch})", 'info' );
		return array(
			'ok'      => true,
			'message' => "Deployed {$type} {$slug} successfully.",
			'backup'  => $backup_result ? basename( $backup_result ) : '',
		);
	}

	/**
	 * Extract the zipball into the target directory.
	 *
	 * GitHub zipballs have a top-level folder: <owner>-<repo>-<sha>/
	 * which is stripped. An optional subdirectory can be selected.
	 *
	 * @return true|\WP_Error
	 */
	private function install_from_zip( string $zip, string $type, string $slug, $subdir ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'beplus_manager_zip', 'ZipArchive extension is not available.' );
		}
		$za = new \ZipArchive();
		$res = $za->open( $zip );
		if ( true !== $res ) {
			return new \WP_Error( 'beplus_manager_zip_open', 'Could not open downloaded archive.' );
		}

		// Find the single top-level folder.
		$root = null;
		for ( $i = 0; $i < $za->numFiles; $i++ ) {
			$name = $za->getNameIndex( $i );
			if ( '/' === substr( $name, -1 ) ) {
				$parts = explode( '/', trim( $name, '/' ) );
				if ( count( $parts ) === 1 ) {
					$root = $parts[0];
					break;
				}
			}
		}
		if ( ! $root ) {
			$za->close();
			return new \WP_Error( 'beplus_manager_zip_root', 'Archive has no root folder.' );
		}

		$target = $this->backup->package_dir( $type, $slug );

		// Clear the target so we get a clean install.
		if ( is_dir( $target ) ) {
			$this->rrmdir( $target );
		}
		if ( ! wp_mkdir_p( $target ) ) {
			$za->close();
			return new \WP_Error( 'beplus_manager_mkdir', 'Could not create target directory.' );
		}

		$prefix    = $root . '/';
		$subprefix = null;
		if ( $subdir ) {
			$subprefix = $root . '/' . trim( $subdir, '/' ) . '/';
		}

		for ( $i = 0; $i < $za->numFiles; $i++ ) {
			$name = $za->getNameIndex( $i );
			if ( 0 !== strpos( $name, $prefix ) ) {
				continue;
			}
			$rel = substr( $name, strlen( $prefix ) );
			if ( '' === $rel || '/' === $rel ) {
				continue;
			}
			if ( $subprefix && 0 !== strpos( $name, $subprefix ) ) {
				continue;
			}
			if ( $subprefix ) {
				$rel = substr( $name, strlen( $subprefix ) );
			}
			$dest = $target . '/' . $rel;
			if ( '/' === substr( $name, -1 ) ) {
				wp_mkdir_p( $dest );
			} else {
				$dir = dirname( $dest );
				if ( ! is_dir( $dir ) ) {
					wp_mkdir_p( $dir );
				}
				$content = $za->getFromIndex( $i );
				if ( false !== $content ) {
					file_put_contents( $dest, $content );
				} else {
					// Binary/streamed entry: extract to a temp file then move.
					$stream = $za->getStream( $name );
					if ( $stream ) {
						$fp = fopen( $dest, 'wb' );
						if ( $fp ) {
							while ( ! feof( $stream ) ) {
								fwrite( $fp, fread( $stream, 8192 ) );
							}
							fclose( $fp );
						}
						fclose( $stream );
					}
				}
			}
		}
		$za->close();
		return true;
	}

	/**
	 * Recursively delete a directory.
	 */
	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
		}
		@rmdir( $dir );
	}
}
