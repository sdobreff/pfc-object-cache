<?php
/**
 * Nginx FastCGI / proxy cache purger.
 *
 * Recursively deletes the contents of the configured nginx cache
 * directory when the object cache is flushed.
 *
 * @package PFC_Object_Cache
 */

declare(strict_types=1);

namespace PFC\ObjectCache;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( NginxCachePurger::class ) ) {

	/**
	 * Handles nginx cache purging.
	 */
	class NginxCachePurger {

		/**
		 * Configured nginx cache directory.
		 *
		 * @var string
		 */
		private static string $cache_path = '';

		/**
		 * Whether purging is enabled.
		 *
		 * @var bool
		 */
		private static bool $enabled = false;

		// ----------------------------------------------------------
		// Setup
		// ----------------------------------------------------------

		/**
		 * Initialise from the plugin config array.
		 *
		 * @param  array $config Plugin config (from CacheEngine::get_config()).
		 */
		public static function init( array $config = array() ): void {
			self::$enabled    = ! empty( $config['nginx_purge_enabled'] );
			self::$cache_path = trim( (string) ( $config['nginx_cache_path'] ?? '' ) );
		}

		/**
		 * Whether nginx purging is enabled and the path is configured.
		 */
		public static function is_enabled(): bool {
			return self::$enabled && '' !== self::$cache_path;
		}

		// ----------------------------------------------------------
		// Purge operations
		// ----------------------------------------------------------

		/**
		 * Purge the entire nginx cache directory.
		 *
		 * @return bool True on success, false when disabled or on failure.
		 */
		public static function purge_all(): bool {
			if ( ! self::is_enabled() ) {
				return false;
			}

			$path = realpath( self::$cache_path );

			if ( false === $path || ! is_dir( $path ) ) {
				return false;
			}

			// Reject symlinks to prevent symlink-based path traversal.
			if ( is_link( self::$cache_path ) ) {
				return false;
			}

			if ( ! self::is_safe_path( $path ) ) {
				return false;
			}

			return self::recursive_delete_contents( $path );
		}

		/**
		 * Attempt to purge a single URL via the nginx ngx_cache_purge
		 * module (sends an HTTP PURGE request).
		 *
		 * Requires the nginx purge module to be compiled and configured.
		 * Falls back gracefully when the module is absent.
		 *
		 * @param  string $url Full URL to purge.
		 * @return bool
		 */
		public static function purge_url( string $url ): bool {
			if ( ! self::is_enabled() || empty( $url ) ) {
				return false;
			}

			$url = esc_url_raw( $url );

			if ( empty( $url ) ) {
				return false;
			}

			$response = \wp_remote_request(
				$url,
				array(
					'method'    => 'PURGE',
					'timeout'   => 5,
					'sslverify' => true,
				)
			);

			if ( \is_wp_error( $response ) ) {
				return false;
			}

			$code = \wp_remote_retrieve_response_code( $response );
			return $code >= 200 && $code < 300;
		}

		// ----------------------------------------------------------
		// Helpers
		// ----------------------------------------------------------

		/**
		 * Safety check to prevent accidental deletion of critical directories.
		 *
		 * @param  string $path Resolved absolute path.
		 * @return bool
		 */
		private static function is_safe_path( string $path ): bool {
			$dangerous = array(
				'/',
				'/etc',
				'/var',
				'/usr',
				'/home',
				'/root',
				'/tmp',
				'/bin',
				'/sbin',
				'/lib',
				'/sys',
				'/proc',
				'/dev',
				'/boot',
				'/opt',
				'/srv',
				'/run',
				'/mnt',
				'/media',
			);

			if ( in_array( $path, $dangerous, true ) ) {
				return false;
			}

			// Reject paths shorter than 8 chars to require meaningful depth.
			if ( strlen( $path ) < 8 ) {
				return false;
			}

			// Must be at least 3 levels deep (e.g. /var/run/cache).
			if ( substr_count( trim( $path, '/' ), '/' ) < 2 ) {
				return false;
			}

			return true;
		}

		/**
		 * Recursively delete the *contents* of a directory (not the dir itself).
		 *
		 * @param  string $dir Absolute directory path.
		 * @return bool
		 */
		private static function recursive_delete_contents( string $dir, int $depth = 0 ): bool {
			if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
				return false;
			}

			// Prevent excessive recursion.
			if ( $depth > 20 ) {
				return false;
			}

			$items   = new \DirectoryIterator( $dir );
			$success = true;

			foreach ( $items as $item ) {
				if ( $item->isDot() ) {
					continue;
				}

				$path = $item->getPathname();

				if ( $item->isDir() ) {
					self::recursive_delete_contents( $path, $depth + 1 );
					if ( ! @rmdir( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
						$success = false;
					}
				} elseif ( ! @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$success = false;
				}
			}

			return $success;
		}
	}
}
