<?php
/**
 * Manages copying and removing the object-cache.php drop-in.
 *
 * @package PFC_Object_Cache
 */

declare(strict_types=1);

namespace PFC\ObjectCache;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( DropInInstaller::class ) ) {

	/**
	 * Handles drop-in file lifecycle.
	 */
	class DropInInstaller {

		/**
		 * Copies the drop-in template to wp-content/object-cache.php.
		 *
		 * Called on plugin activation. No direct output — uses WP_Filesystem.
		 *
		 * @return void
		 */
		public static function install(): void {
			if ( ! \current_user_can( 'activate_plugins' ) ) {
				return;
			}

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();
			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				return;
			}

			// Do not overwrite a drop-in installed by another plugin.
			if ( $wp_filesystem->exists( PFC_DROP_IN_DEST ) ) {
				$existing = $wp_filesystem->get_contents( PFC_DROP_IN_DEST );
				if ( false === strpos( $existing, 'PHPFastCache WordPress Object Cache Drop-In' ) ) {
					// Foreign drop-in present — bail out safely.
					\add_action(
						'admin_notices',
						static function (): void {
							printf(
								'<div class="notice notice-warning"><p>%s</p></div>',
								esc_html__( 'PFC Object Cache: An existing object-cache.php drop-in was found and left untouched. Deactivate the conflicting plugin first.', 'pfc-object-cache' )
							);
						}
					);
					return;
				}
			}

			$wp_filesystem->copy( PFC_DROP_IN_SOURCE, PFC_DROP_IN_DEST, true );
			\update_option( 'pfc_drop_in_installed', true, false );

			// Write initial config file so the drop-in can boot without get_option().
			$settings = (array) \get_option( AdminPage::OPTION_KEY, array() );
			AdminPage::write_config_file( $settings );
		}

		/**
		 * Removes the drop-in from wp-content/ on plugin deactivation.
		 *
		 * @return void
		 */
		public static function uninstall(): void {
			if ( ! \current_user_can( 'activate_plugins' ) ) {
				return;
			}

			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();
			global $wp_filesystem;

			if ( $wp_filesystem && $wp_filesystem->exists( PFC_DROP_IN_DEST ) ) {
				$contents = $wp_filesystem->get_contents( PFC_DROP_IN_DEST );
				// Only remove the drop-in if it belongs to this plugin.
				if ( false !== strpos( $contents, 'PHPFastCache WordPress Object Cache Drop-In' ) ) {
					$wp_filesystem->delete( PFC_DROP_IN_DEST );
				}
			}

			\delete_option( 'pfc_drop_in_installed' );

			// Remove the config directory used by the drop-in.
			$config_dir = AdminPage::get_config_dir();
			if ( $wp_filesystem && $wp_filesystem->exists( $config_dir ) ) {
				$wp_filesystem->delete( $config_dir, true );
			}
		}
	}

} // end class_exists check
