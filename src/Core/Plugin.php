<?php

namespace BeplusManager\Core;

/**
 * Main plugin container. Boots modules and wires hooks.
 */
final class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var array<string,mixed> */
	private $services = array();

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		$this->register_core_services();
		$this->boot_registered_modules();

		add_action( 'init', array( $this, 'on_init' ) );
	}

	private function register_core_services(): void {
		$this->services['packages'] = new \BeplusManager\Storage\PackageRepository();
		$this->services['github']   = new \BeplusManager\GitHub\GitHubClient();
		$this->services['backup']   = new \BeplusManager\Backup\BackupManager();
		$this->services['deploy']   = new \BeplusManager\Deploy\Deployer(
			$this->services['github'],
			$this->services['backup']
		);
	}

	private function boot_registered_modules(): void {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Register REST routes on rest_api_init so they are available to the API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes(): void {
		$settings_controller = new \BeplusManager\REST\SettingsController(
			$this->services['packages'],
			$this->services['github'],
			$this->services['deploy'],
			$this->services['backup']
		);
		$settings_controller->register_routes();

		$webhook_controller = new \BeplusManager\REST\WebhookController(
			$this->services['packages'],
			$this->services['deploy']
		);
		$webhook_controller->register_routes();
	}

	public function on_init(): void {
		// Translations are loaded automatically by WordPress from the
		// plugin's languages/ folder (see the Text Domain header).
	}

	public function register_admin_menu(): void {
		add_menu_page(
			__( 'Beplus Manager', 'beplus-manager' ),
			__( 'Beplus Manager', 'beplus-manager' ),
			'manage_options',
			'beplus-manager',
			array( $this, 'render_admin_page' ),
			'dashicons-update',
			58
		);
	}

	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'beplus-manager' ) );
		}
		echo '<div id="beplus-manager-root"></div>';
	}

	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_beplus-manager' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'beplus-manager-admin',
			BEPLUS_MANAGER_URL . 'admin/css/admin.css',
			array(),
			BEPLUS_MANAGER_VERSION
		);
		wp_enqueue_script(
			'beplus-manager-admin',
			BEPLUS_MANAGER_URL . 'admin/js/admin.js',
			array(),
			BEPLUS_MANAGER_VERSION,
			true
		);
		wp_localize_script(
			'beplus-manager-admin',
			'bmApi',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'beplus-manager/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Get a registered service.
	 *
	 * @return mixed|null
	 */
	public function get( string $key ) {
		return $this->services[ $key ] ?? null;
	}

	public function activate(): void {
		$defaults = array(
			'github_token'     => '',
			'github_client_id' => '',
			'webhook_secret'   => wp_generate_password( 32, false ),
			'packages'         => array(),
		);
		add_option( 'beplus_manager_settings', $defaults );
	}

	public function deactivate(): void {
		// Nothing critical to clean up; backups and options are intentionally kept.
	}
}
