<?php
/**
 * Provides cache management actions (flush, diagnostics).
 *
 * @package PFC_Object_Cache
 */

declare(strict_types=1);

namespace PFC\ObjectCache;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( CacheManager::class ) ) {

	/**
	 * Cache management utilities consumed by the admin UI.
	 */
	class CacheManager {

		/**
		 * Flushes the entire object cache.
		 *
		 * Requires manage_options capability and a valid nonce.
		 *
		 * @return array{success: bool, message: string}
		 */
		public static function flush_all(): array {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return array(
					'success' => false,
					'message' => \esc_html__( 'Permission denied.', 'pfc-object-cache' ),
				);
			}

			$flushed = \wp_cache_flush();

			// Also purge nginx cache on full flush.
			NginxCachePurger::purge_all();

			return array(
				'success' => $flushed,
				'message' => $flushed
					? \esc_html__( 'Object cache flushed successfully.', 'pfc-object-cache' )
					: \esc_html__( 'Cache flush failed. Check server logs for details.', 'pfc-object-cache' ),
			);
		}

		/**
		 * Flushes all items belonging to a single cache group.
		 *
		 * @param  string $group Cache group name.
		 * @return array{success: bool, message: string}
		 */
		public static function flush_group( string $group ): array {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return array(
					'success' => false,
					'message' => \esc_html__( 'Permission denied.', 'pfc-object-cache' ),
				);
			}

			$group   = \sanitize_key( $group );
			$flushed = \wp_cache_flush_group( $group );

			return array(
				'success' => $flushed,
				/* translators: %s: cache group name */
				'message' => $flushed
					? sprintf( \esc_html__( 'Cache group "%s" flushed.', 'pfc-object-cache' ), $group )
					: sprintf( \esc_html__( 'Failed to flush group "%s".', 'pfc-object-cache' ), $group ),
			);
		}

		/**
		 * Returns runtime statistics from the global cache object.
		 *
		 * @return array{hits: int, misses: int, ratio: float, runtime_items: int, drop_in_active: bool}
		 */
		public static function get_stats(): array {
			global $wp_object_cache;

			$base = array(
				'hits'           => 0,
				'misses'         => 0,
				'ratio'          => 0.0,
				'runtime_items'  => 0,
				'drop_in_active' => false,
			);

			if ( $wp_object_cache instanceof \WP_Object_Cache
			&& method_exists( $wp_object_cache, 'get_stats' )
			) {
				return array_merge(
					$base,
					$wp_object_cache->get_stats(),
					array( 'drop_in_active' => true )
				);
			}

			return $base;
		}

		/**
		 * Purges the nginx cache directory.
		 *
		 * @return array{success: bool, message: string}
		 */
		public static function purge_nginx(): array {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return array(
					'success' => false,
					'message' => \esc_html__( 'Permission denied.', 'pfc-object-cache' ),
				);
			}

			if ( ! NginxCachePurger::is_enabled() ) {
				return array(
					'success' => false,
					'message' => \esc_html__( 'Nginx cache purging is not enabled. Configure a cache path in settings.', 'pfc-object-cache' ),
				);
			}

			$purged = NginxCachePurger::purge_all();

			return array(
				'success' => $purged,
				'message' => $purged
					? \esc_html__( 'Nginx cache purged successfully.', 'pfc-object-cache' )
					: \esc_html__( 'Nginx cache purge failed. Check the cache path and permissions.', 'pfc-object-cache' ),
			);
		}
	}

} // end class_exists check
