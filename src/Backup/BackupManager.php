<?php

namespace BeplusManager\Backup;

/**
 * Creates a single rolling backup of a plugin/theme directory before deploys.
 * Keeps only the most recent backup (previous version) and supports rollback.
 */
class BackupManager {

	// This is a deploy/backup tool: it needs atomic directory moves
	// (rename), ownership fixes (chown) and recursive deletes that the
	// WP_Filesystem API does not provide. Direct calls are intentional.
	// phpcs:disable WordPress.WP.AlternativeFunctions

	/** @var string */
	private $backup_root;

	public function __construct() {
		$this->backup_root = beplus_manager_backup_dir();
	}

	/**
	 * Backup a plugin/theme directory before deploying a new version.
	 *
	 * @param string $type 'plugin'|'theme'
	 * @param string $slug Directory slug of the package.
	 *
	 * @return string|\WP_Error Backup directory path.
	 */
	public function backup_package( string $type, string $slug ) {
		$source = $this->package_dir( $type, $slug );
		if ( ! is_dir( $source ) ) {
			return new \WP_Error( 'beplus_manager_no_source', 'Source directory does not exist: ' . $source );
		}
		$this->delete_existing_backup( $type, $slug );

		$dest = $this->backup_root . '/' . $type . '-' . $slug . '-' . current_time( 'YmdHis' );
		if ( ! wp_mkdir_p( $dest ) ) {
			return new \WP_Error( 'beplus_manager_mkdir', 'Could not create backup directory.' );
		}
		$copied = $this->copy_dir( $source, $dest );
		if ( is_wp_error( $copied ) ) {
			return $copied;
		}
		// Ensure the web server user can manage the backup (rollback renames it).
		$this->fix_permissions( $dest );
		beplus_manager_log( "Backup created: {$type} {$slug} → {$dest}", 'info' );
		return $dest;
	}

	/**
	 * Restore the most recent backup for a package.
	 *
	 * @return true|\WP_Error
	 */
	public function restore_package( string $type, string $slug ) {
		$latest = $this->latest_backup( $type, $slug );
		if ( ! $latest ) {
			return new \WP_Error( 'beplus_manager_no_backup', 'No backup found for this package.' );
		}
		$target = $this->package_dir( $type, $slug );
		if ( ! is_dir( $target ) ) {
			return new \WP_Error( 'beplus_manager_no_target', 'Target directory does not exist.' );
		}
		// The live directory may have been created by a different user (e.g.
		// CLI/root). Make sure the web server user can rename it.
		$this->fix_permissions( $target );
		$this->fix_permissions( $latest );
		// Move current version aside, then restore the backup.
		$trash = $this->backup_root . '/trash-' . $type . '-' . $slug . '-' . current_time( 'YmdHis' );
		rename( $target, $trash );
		$ok = rename( $latest, $target );
		if ( ! $ok ) {
			// Attempt to recover: move the live version back if it still exists.
			if ( is_dir( $trash ) ) {
				rename( $trash, $target );
			}
			return new \WP_Error( 'beplus_manager_restore', 'Restore failed; original left in place.' );
		}
		$this->rrmdir( $trash );
		beplus_manager_log( "Restored {$type} {$slug} from backup.", 'info' );
		return true;
	}

	/**
	 * Create a NEW snapshot backup of the current live directory WITHOUT
	 * deleting any existing backups (unlike backup_package which keeps only
	 * one rolling backup). Used before restoring a specific backup so the
	 * user can roll back again after a restore.
	 *
	 * @return string|\WP_Error Backup directory path.
	 */
	public function snapshot_package( string $type, string $slug ) {
		$source = $this->package_dir( $type, $slug );
		if ( ! is_dir( $source ) ) {
			return new \WP_Error( 'beplus_manager_no_source', 'Source directory does not exist: ' . $source );
		}
		$dest = $this->backup_root . '/' . $type . '-' . $slug . '-' . current_time( 'YmdHis' );
		if ( ! wp_mkdir_p( $dest ) ) {
			return new \WP_Error( 'beplus_manager_mkdir', 'Could not create backup directory.' );
		}
		$copied = $this->copy_dir( $source, $dest );
		if ( is_wp_error( $copied ) ) {
			return $copied;
		}
		$this->fix_permissions( $dest );
		beplus_manager_log( "Snapshot created: {$type} {$slug} → {$dest}", 'info' );
		return $dest;
	}

	/**
	 * Restore a SPECIFIC backup directory (by name) into the live package dir.
	 * Used by "restore from uploaded file".
	 *
	 * @return true|\WP_Error
	 */
	public function restore_specific_backup( string $type, string $slug, string $backup_name ) {
		$backup_dir = $this->backup_root . '/' . $backup_name;
		if ( ! is_dir( $backup_dir ) ) {
			return new \WP_Error( 'beplus_manager_no_backup', 'Backup not found on disk.' );
		}
		$target = $this->package_dir( $type, $slug );
		// Snapshot of current live is taken by the caller; here we replace the
		// live directory. Use copy instead of rename so a target owned by a
		// different user (e.g. root from CLI) can still be replaced as long as
		// the parent dir is writable.
		if ( is_dir( $target ) ) {
			$this->rrmdir( $target );
		}
		$copied = $this->copy_dir( $backup_dir, $target );
		if ( is_wp_error( $copied ) ) {
			return new \WP_Error( 'beplus_manager_restore', 'Restore failed: ' . $copied->get_error_message() );
		}
		$this->fix_permissions( $target );
		beplus_manager_log( "Restored {$type} {$slug} from backup {$backup_name}.", 'info' );
		return true;
	}

	/**
	 * Best-effort: make a backup dir tree writable by the web server user.
	 * This guards against backups created by a different user (e.g. CLI/root)
	 * which would otherwise break rollback (rename needs write permission).
	 */
	private function fix_permissions( string $dir ): void {
		if ( ! function_exists( 'posix_geteuid' ) ) {
			return;
		}
		$uid = posix_geteuid();
		@chown( $dir, $uid );
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $it as $item ) {
			@chown( $item->getPathname(), $uid );
		}
	}

	/**
	 * Delete ALL backups for a package (used when the package is removed).
	 */
	public function delete_backups_for( string $type, string $slug ): void {
		$found = glob( $this->backup_root . '/' . $type . '-' . $slug . '-*' );
		foreach ( $found ?: array() as $dir ) {
			if ( is_dir( $dir ) ) {
				$this->rrmdir( $dir );
			}
		}
	}

	/**
	 * Find the most recent backup directory for a package.
	 */
	public function latest_backup( string $type, string $slug ): ?string {
		$pattern = $this->backup_root . '/' . $type . '-' . $slug . '-*';
		$found   = glob( $pattern );
		if ( ! $found ) {
			return null;
		}
		usort( $found, static function ( $a, $b ) {
			return strcmp( $b, $a );
		} );
		return $found[0];
	}

	/**
	 * Remove any existing backup(s) for a package (keep only one).
	 */
	private function delete_existing_backup( string $type, string $slug ): void {
		$found = glob( $this->backup_root . '/' . $type . '-' . $slug . '-*' );
		foreach ( (array) $found as $dir ) {
			if ( 0 === strpos( basename( $dir ), 'trash-' ) ) {
				continue;
			}
			$this->rrmdir( $dir );
		}
	}

	public function package_dir( string $type, string $slug ): string {
		if ( 'theme' === $type ) {
			return WP_CONTENT_DIR . '/themes/' . $slug;
		}
		return WP_CONTENT_DIR . '/plugins/' . $slug;
	}

	/**
	 * Recursively copy a directory.
	 *
	 * @return true|\WP_Error
	 */
	private function copy_dir( string $source, string $dest ) {
		if ( ! wp_mkdir_p( $dest ) ) {
			return new \WP_Error( 'beplus_manager_mkdir', 'Could not create ' . $dest );
		}
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $it as $item ) {
			$target = $dest . '/' . $it->getSubPathname();
			if ( $item->isDir() ) {
				if ( ! wp_mkdir_p( $target ) ) {
					return new \WP_Error( 'beplus_manager_mkdir', 'Could not create ' . $target );
				}
			} elseif ( $item->isFile() || $item->isLink() ) {
				if ( ! copy( $item->getPathname(), $target ) ) {
					return new \WP_Error( 'beplus_manager_copy', 'Could not copy ' . $item->getPathname() );
				}
			}
		}
		return true;
	}

	/**
	 * Recursively delete a directory.
	 */
	public function rrmdir( string $dir ): void {
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
