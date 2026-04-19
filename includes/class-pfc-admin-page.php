<?php
/**
 * Standalone WordPress admin page for PFC Object Cache.
 *
 * Registers a top-level menu, handles form submissions with nonce
 * verification, and renders the full settings + diagnostics UI.
 *
 * @package PFC_Object_Cache
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin page controller.
 */
class PFC_Admin_Page {

	/**
	 * Admin page hook suffix returned by add_menu_page().
	 *
	 * @var string
	 */
	protected string $page_hook = '';

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'pfc-object-cache';

	/**
	 * Option key for plugin settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'pfc_cache_settings';

	/**
	 * Nonce action for flush operations.
	 *
	 * @var string
	 */
	const NONCE_FLUSH = 'pfc_flush_cache';

	/**
	 * Nonce action for settings save.
	 *
	 * @var string
	 */
	const NONCE_SETTINGS = 'pfc_save_settings';

	/**
	 * Register WP hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu',            array( $this, 'add_menu' ) );
		add_action( 'admin_init',            array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	/**
	 * Registers the top-level admin menu item.
	 *
	 * Only users with manage_options capability will see this menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$this->page_hook = add_menu_page(
			esc_html__( 'Object Cache', 'pfc-object-cache' ),
			esc_html__( 'Object Cache', 'pfc-object-cache' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-database',
			80
		);
	}

	// ── Styles ────────────────────────────────────────────────────────────────

	/**
	 * Enqueues styles for the admin page.
	 *
	 * @param  string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		if ( $hook !== $this->page_hook ) {
			return;
		}

		wp_enqueue_style(
			'pfc-admin',
			PFC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			PFC_PLUGIN_VERSION
		);
	}

	// ── Actions ───────────────────────────────────────────────────────────────

	/**
	 * Handles POST actions (flush, settings save).
	 *
	 * Verifies nonces and capabilities before taking any action.
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified below per action.
		$action = sanitize_key( $_POST['pfc_action'] ?? '' );
		// phpcs:enable

		if ( empty( $action ) ) {
			return;
		}

		switch ( $action ) {
			case 'flush_all':
				check_admin_referer( self::NONCE_FLUSH );
				$result = PFC_Cache_Manager::flush_all();
				$this->redirect_with_notice(
					$result['success'] ? 'flushed' : 'flush_failed',
					$result['message']
				);
				break;

			case 'flush_group':
				check_admin_referer( self::NONCE_FLUSH );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
				$group  = sanitize_key( $_POST['pfc_group'] ?? 'default' );
				$result = PFC_Cache_Manager::flush_group( $group );
				$this->redirect_with_notice(
					$result['success'] ? 'flushed' : 'flush_failed',
					$result['message']
				);
				break;

			case 'save_settings':
				check_admin_referer( self::NONCE_SETTINGS );
				$this->save_settings();
				$this->redirect_with_notice(
					'settings_saved',
					esc_html__( 'Settings saved.', 'pfc-object-cache' )
				);
				break;
		}
	}

	/**
	 * Persists the settings form to wp_options.
	 *
	 * @return void
	 */
	protected function save_settings(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_actions().
		$raw = (array) ( $_POST['pfc_settings'] ?? array() );
		// phpcs:enable

		$sanitized = array(
			'driver'         => sanitize_key( $raw['driver'] ?? 'Files' ),
			'redis_host'     => sanitize_text_field( $raw['redis_host'] ?? '127.0.0.1' ),
			'redis_port'     => absint( $raw['redis_port'] ?? 6379 ),
			'redis_password' => sanitize_text_field( $raw['redis_password'] ?? '' ),
			'redis_database' => absint( $raw['redis_database'] ?? 0 ),
			'redis_timeout'  => absint( $raw['redis_timeout'] ?? 5 ),
			'memcached_host' => sanitize_text_field( $raw['memcached_host'] ?? '127.0.0.1' ),
			'memcached_port' => absint( $raw['memcached_port'] ?? 11211 ),
		);

		update_option( self::OPTION_KEY, $sanitized, false );
	}

	/**
	 * Redirects back to the plugin page with a status query arg.
	 *
	 * @param  string $status  Status slug (e.g. 'flushed').
	 * @param  string $message Human-readable notice message.
	 * @return void
	 */
	protected function redirect_with_notice( string $status, string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::PAGE_SLUG,
					'pfc_status'  => rawurlencode( $status ),
					'pfc_message' => rawurlencode( $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// ── Render ────────────────────────────────────────────────────────────────

	/**
	 * Renders the full admin page. Called by WP when the menu item is opened.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'pfc-object-cache' ) );
		}

		$stats    = PFC_Cache_Manager::get_stats();
		$settings = (array) get_option( self::OPTION_KEY, array() );
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$status  = sanitize_key( $_GET['pfc_status']  ?? '' );
		$message = sanitize_text_field( rawurldecode( $_GET['pfc_message'] ?? '' ) );
		// phpcs:enable
		$driver  = sanitize_key( $settings['driver'] ?? 'Files' );

		include PFC_PLUGIN_DIR . 'templates/admin-page.php';
	}
}
