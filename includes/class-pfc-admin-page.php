<?php
/**
 * Standalone WordPress admin page for PFC Object Cache.
 *
 * Registers a top-level menu, handles form submissions with nonce
 * verification, and renders the full settings + diagnostics UI.
 *
 * @package PFC_Object_Cache
 */

declare(strict_types=1);

namespace PFC\ObjectCache;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( AdminPage::class ) ) {
	/**
	 * Admin page controller.
	 */
	class AdminPage {

		/**
		 * Admin page hook suffix returned by add_menu_page().
		 *
		 * @var string
		 */
		private static string $page_hook = '';

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
		public static function register(): void {
			\add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
			\add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
			\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		}

		// ── Menu ──────────────────────────────────────────────────────────────────

		/**
		 * Registers the top-level admin menu item.
		 *
		 * Only users with manage_options capability will see this menu.
		 *
		 * @return void
		 */
		public static function add_menu(): void {
			self::$page_hook = add_menu_page(
				\esc_html__( 'Object Cache', 'pfc-object-cache' ),
				\esc_html__( 'Object Cache', 'pfc-object-cache' ),
				'manage_options',
				self::PAGE_SLUG,
				array( __CLASS__, 'render_page' ),
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
		public static function enqueue_styles( string $hook ): void {
			if ( $hook !== self::$page_hook ) {
				return;
			}

			\wp_enqueue_style(
				'pfc-admin',
				PFC_PLUGIN_URL . 'css/admin.css',
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
		public static function handle_actions(): void {
			if ( ! \is_admin() || ! \current_user_can( 'manage_options' ) ) {
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
					\check_admin_referer( self::NONCE_FLUSH );
					$result = CacheManager::flush_all();
					self::redirect_with_notice(
						$result['success'] ? 'flushed' : 'flush_failed',
						$result['message']
					);
					break;

				case 'flush_group':
					\check_admin_referer( self::NONCE_FLUSH );
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
					$group  = sanitize_key( $_POST['pfc_group'] ?? 'default' );
					$result = CacheManager::flush_group( $group );
					self::redirect_with_notice(
						$result['success'] ? 'flushed' : 'flush_failed',
						$result['message']
					);
					break;

				case 'save_settings':
					\check_admin_referer( self::NONCE_SETTINGS );
					self::save_settings();
					self::redirect_with_notice(
						'settings_saved',
						\esc_html__( 'Settings saved.', 'pfc-object-cache' )
					);
					break;

				case 'purge_nginx':
					\check_admin_referer( self::NONCE_FLUSH );
					$result = CacheManager::purge_nginx();
					self::redirect_with_notice(
						$result['success'] ? 'flushed' : 'flush_failed',
						$result['message']
					);
					break;
			}
		}

		/**
		 * Persists the settings form to wp_options and writes the config file
		 * for the drop-in (which cannot use get_option without recursion).
		 *
		 * @return void
		 */
		protected static function save_settings(): void {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_actions().
			$raw = (array) ( $_POST['pfc_settings'] ?? array() );
			// phpcs:enable

			$sanitized = array(
				'driver'                 => \sanitize_key( $raw['driver'] ?? 'Files' ),
				'redis_host'             => \sanitize_text_field( $raw['redis_host'] ?? '127.0.0.1' ),
				'redis_port'             => \absint( $raw['redis_port'] ?? 6379 ),
				'redis_password'         => \sanitize_text_field( $raw['redis_password'] ?? '' ),
				'redis_database'         => \absint( $raw['redis_database'] ?? 0 ),
				'redis_timeout'          => \absint( $raw['redis_timeout'] ?? 5 ),
				'memcached_host'         => \sanitize_text_field( $raw['memcached_host'] ?? '127.0.0.1' ),
				'memcached_port'         => \absint( $raw['memcached_port'] ?? 11211 ),
				'nginx_purge_enabled'    => ! empty( $raw['nginx_purge_enabled'] ),
				'nginx_cache_path'       => \sanitize_text_field( $raw['nginx_cache_path'] ?? '' ),
				'nginx_purge_server_url' => \esc_url_raw( $raw['nginx_purge_server_url'] ?? '' ),
				'nginx_auto_purge'       => ! empty( $raw['nginx_auto_purge'] ),
			);

			\update_option( self::OPTION_KEY, $sanitized, false );
			self::write_config_file( $sanitized );
		}

		/**
		 * Writes the settings to a static PHP config file that the drop-in can
		 * include directly (avoiding get_option / object-cache recursion).
		 *
		 * @param  array $settings Sanitized settings array.
		 * @return bool  Whether the file was written successfully.
		 */
		public static function write_config_file( array $settings ): bool {
			$dir  = self::get_config_dir();
			$path = $dir . '/pfc-config.php';

			// Ensure the directory exists and is hardened.
			self::maybe_create_config_dir( $dir );

			// Include cache_path so the drop-in uses the uploads directory.
			$upload_dir             = \wp_upload_dir( null, false );
			$settings['cache_path'] = $upload_dir['basedir'] . '/pfc-object-cache/cache';

			$export = var_export( $settings, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

			$content  = "<?php\n";
			$content .= "/**\n * PFC Object Cache — auto-generated config. Do not edit.\n * @generated\n */\n";
			$content .= "defined( 'ABSPATH' ) || exit;\n\n";
			$content .= "return {$export};\n";

			// Use file_put_contents so this works even outside admin context.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false !== file_put_contents( $path, $content, LOCK_EX );
		}

		/**
		 * Returns the directory path used for the config file.
		 *
		 * Can be overridden via the PFC_CONFIG_DIR constant in wp-config.php.
		 *
		 * @return string
		 */
		public static function get_config_dir(): string {
			if ( defined( 'PFC_CONFIG_DIR' ) ) {
				return PFC_CONFIG_DIR;
			}

			$upload_dir = \wp_upload_dir( null, false );
			return $upload_dir['basedir'] . '/pfc-object-cache';
		}

		/**
		 * Creates the config directory and adds hardening files.
		 *
		 * - .htaccess denying all HTTP access (Apache / LiteSpeed).
		 * - index.php preventing directory listing on servers that ignore .htaccess.
		 *
		 * @param string $dir Absolute path to the config directory.
		 * @return void
		 */
		protected static function maybe_create_config_dir( string $dir ): void {
			if ( is_dir( $dir ) ) {
				return;
			}

			\wp_mkdir_p( $dir );

			// Deny direct HTTP access (Apache / LiteSpeed).
			$htaccess = $dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $htaccess, "Deny from all\n" );
			}

			// Silence directory index on any server.
			$index = $dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $index, "<?php\n// Silence is golden.\n" );
			}
		}

		/**
		 * Redirects back to the plugin page with a status query arg.
		 *
		 * @param  string $status  Status slug (e.g. 'flushed').
		 * @param  string $message Human-readable notice message.
		 * @return void
		 */
		protected static function redirect_with_notice( string $status, string $message ): void {
			\wp_safe_redirect(
				\add_query_arg(
					array(
						'page'        => self::PAGE_SLUG,
						'pfc_status'  => rawurlencode( $status ),
						'pfc_message' => rawurlencode( $message ),
					),
					\admin_url( 'admin.php' )
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
		public static function render_page(): void {
			if ( ! \current_user_can( 'manage_options' ) ) {
				\wp_die( \esc_html__( 'You do not have permission to access this page.', 'pfc-object-cache' ) );
			}

			$stats    = CacheManager::get_stats();
			$settings = (array) \get_option( self::OPTION_KEY, array() );
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			$status  = \sanitize_key( $_GET['pfc_status'] ?? '' );
			$message = \sanitize_text_field( \rawurldecode( $_GET['pfc_message'] ?? '' ) );
			// phpcs:enable
			$driver = \sanitize_key( $settings['driver'] ?? 'Files' );

			include PFC_PLUGIN_DIR . 'templates/admin-page.php';
		}
	}
}
