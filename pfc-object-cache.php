<?php
/**
 * Plugin Name:       PFC Object Cache
 * Plugin URI:        https://0-day-analytics.com/
 * Description:       Persistent WordPress object cache drop-in powered by PHPFastCache. Supports Redis, Memcached, APCu, Files and more.
 * Version:           1.0.0
 * Author:            0-day Analytics
 * Author URI:        https://0-day-analytics.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pfc-object-cache
 * Domain Path:       /languages
 * Requires PHP:      8.1
 * Requires at least: 6.4
 *
 * @package PFC_Object_Cache
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ── Constants ─────────────────────────────────────────────────────────────────

define( 'PFC_PLUGIN_VERSION', '1.0.0' );
define( 'PFC_PLUGIN_FILE', __FILE__ );
define( 'PFC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PFC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PFC_DROP_IN_SOURCE', PFC_PLUGIN_DIR . 'drop-in/object-cache.php' );
define( 'PFC_DROP_IN_DEST', WP_CONTENT_DIR . '/object-cache.php' );

// ── Autoloader ────────────────────────────────────────────────────────────────

$pfc_autoloader = PFC_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $pfc_autoloader ) ) {
	\add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				\esc_html__( 'PFC Object Cache: Composer dependencies are missing. Run `composer install` inside the plugin directory.', 'pfc-object-cache' )
			);
		}
	);
	return;
}
require_once $pfc_autoloader;

use PFC\ObjectCache\DropInInstaller;
use PFC\ObjectCache\AdminPage;
use PFC\ObjectCache\NginxCachePurger;

// ── Activation / Deactivation ─────────────────────────────────────────────────

\register_activation_hook(
	PFC_PLUGIN_FILE,
	array( DropInInstaller::class, 'install' )
);

\register_deactivation_hook(
	PFC_PLUGIN_FILE,
	array( DropInInstaller::class, 'uninstall' )
);

// ── Bootstrap ─────────────────────────────────────────────────────────────────

\add_action(
	'plugins_loaded',
	static function (): void {
		if ( \is_admin() ) {
			AdminPage::register();
		}

		// Bootstrap nginx auto-purge hooks (runs on both admin and front-end).
		$settings = (array) \get_option( AdminPage::OPTION_KEY, array() );
		NginxCachePurger::init( $settings );
		NginxCachePurger::register_hooks();
	},
	10
);
