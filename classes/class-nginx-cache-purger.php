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

		/**
		 * Server URL to send PURGE requests to (e.g. http://127.0.0.1:80).
		 *
		 * @var string
		 */
		private static string $purge_server_url = '';

		/**
		 * Whether auto-purge on content changes is enabled.
		 *
		 * @var bool
		 */
		private static bool $auto_purge = false;

		// ----------------------------------------------------------
		// Setup
		// ----------------------------------------------------------

		/**
		 * Initialise from the plugin config array.
		 *
		 * @param  array $config Plugin config (from CacheEngine::get_config()).
		 */
		public static function init( array $config = array() ): void {
			self::$enabled          = ! empty( $config['nginx_purge_enabled'] );
			self::$cache_path       = trim( (string) ( $config['nginx_cache_path'] ?? '' ) );
			self::$purge_server_url = trim( (string) ( $config['nginx_purge_server_url'] ?? '' ) );
			self::$auto_purge       = ! empty( $config['nginx_auto_purge'] );
		}

		/**
		 * Whether nginx purging is enabled and the path is configured.
		 */
		public static function is_enabled(): bool {
			return self::$enabled && '' !== self::$cache_path;
		}

		/**
		 * Whether auto-purge on content changes is active.
		 */
		public static function is_auto_purge_enabled(): bool {
			return self::$enabled && self::$auto_purge && '' !== self::$purge_server_url;
		}

		/**
		 * Returns the configured purge server URL.
		 */
		public static function get_purge_server_url(): string {
			return self::$purge_server_url;
		}

		// ----------------------------------------------------------
		// Auto-purge WordPress hooks
		// ----------------------------------------------------------

		/**
		 * Registers WordPress hooks for automatic cache purging.
		 */
		public static function register_hooks(): void {
			if ( ! self::is_auto_purge_enabled() ) {
				return;
			}

			// Post create / update / delete.
			\add_action( 'save_post', array( __CLASS__, 'on_post_changed' ), 10, 1 );
			\add_action( 'delete_post', array( __CLASS__, 'on_post_changed' ), 10, 1 );
			\add_action( 'trash_post', array( __CLASS__, 'on_post_changed' ), 10, 1 );

			// Comment changes.
			\add_action( 'comment_post', array( __CLASS__, 'on_comment_changed' ), 10, 1 );
			\add_action( 'edit_comment', array( __CLASS__, 'on_comment_changed' ), 10, 1 );
			\add_action( 'delete_comment', array( __CLASS__, 'on_comment_changed' ), 10, 1 );
			\add_action( 'wp_set_comment_status', array( __CLASS__, 'on_comment_changed' ), 10, 1 );

			// Term (category / tag) changes.
			\add_action( 'edited_term', array( __CLASS__, 'on_term_changed' ), 10, 3 );
			\add_action( 'delete_term', array( __CLASS__, 'on_term_changed' ), 10, 3 );
		}

		/**
		 * Callback when a post is created, updated, trashed, or deleted.
		 *
		 * @param int $post_id Post ID.
		 */
		public static function on_post_changed( int $post_id ): void {
			if ( \wp_is_post_revision( $post_id ) || \wp_is_post_autosave( $post_id ) ) {
				return;
			}

			$post = \get_post( $post_id );
			if ( ! $post || ! \is_post_type_viewable( $post->post_type ) ) {
				return;
			}

			$urls = self::get_purge_urls_for_post( $post_id );
			self::purge_urls( $urls );
		}

		/**
		 * Callback when a comment is created, edited, deleted, or status changed.
		 *
		 * @param int $comment_id Comment ID.
		 */
		public static function on_comment_changed( int $comment_id ): void {
			$comment = \get_comment( $comment_id );
			if ( ! $comment || empty( $comment->comment_post_ID ) ) {
				return;
			}

			$urls = self::get_purge_urls_for_post( (int) $comment->comment_post_ID );
			self::purge_urls( $urls );
		}

		/**
		 * Callback when a term is edited or deleted.
		 *
		 * @param int    $term_id  Term ID.
		 * @param int    $tt_id    Term taxonomy ID.
		 * @param string $taxonomy Taxonomy slug.
		 */
		public static function on_term_changed( int $term_id, int $tt_id, string $taxonomy ): void {
			$link = \get_term_link( $term_id, $taxonomy );
			if ( \is_wp_error( $link ) ) {
				return;
			}

			$urls   = array( $link );
			$urls[] = \home_url( '/' );

			self::purge_urls( $urls );
		}

		/**
		 * Collects all URLs that should be purged when a post changes.
		 *
		 * @param  int $post_id Post ID.
		 * @return string[] Array of full URLs to purge.
		 */
		private static function get_purge_urls_for_post( int $post_id ): array {
			$urls = array();

			$permalink = \get_permalink( $post_id );
			if ( $permalink ) {
				$urls[] = $permalink;
			}

			// Home / front page.
			$urls[] = \home_url( '/' );

			// Post type archive.
			$post_type = \get_post_type( $post_id );
			if ( $post_type ) {
				$archive_link = \get_post_type_archive_link( $post_type );
				if ( $archive_link ) {
					$urls[] = $archive_link;
				}
			}

			// Category and tag archives for this post.
			foreach ( array( 'category', 'post_tag' ) as $tax ) {
				$terms = \get_the_terms( $post_id, $tax );
				if ( $terms && ! \is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_link = \get_term_link( $term );
						if ( ! \is_wp_error( $term_link ) ) {
							$urls[] = $term_link;
						}
					}
				}
			}

			// Feed URLs.
			$urls[] = \get_feed_link();

			return array_unique( array_filter( $urls ) );
		}

		/**
		 * Send PURGE requests for an array of public URLs.
		 *
		 * Rewrites each URL to target the configured purge server while
		 * preserving the original Host header.
		 *
		 * @param  string[] $urls Array of public URLs.
		 * @return void
		 */
		private static function purge_urls( array $urls ): void {
			if ( ! self::is_auto_purge_enabled() || empty( $urls ) ) {
				return;
			}

			foreach ( $urls as $url ) {
				self::purge_url_via_server( $url );
			}
		}

		/**
		 * Send a PURGE request for a public URL via the configured purge server.
		 *
		 * Uses the nginx fastcgi_cache_purge /purge/ location prefix pattern:
		 * a GET request to server/purge/original-path with the original Host
		 * header. The request scheme is taken from the public URL so the nginx
		 * cache key ($scheme component) matches the cached entry.
		 *
		 * @param  string $public_url The original public URL to purge.
		 * @return bool
		 */
		public static function purge_url_via_server( string $public_url ): bool {
			if ( ! self::is_auto_purge_enabled() || empty( $public_url ) ) {
				return false;
			}

			$parsed = wp_parse_url( $public_url );
			if ( empty( $parsed['host'] ) ) {
				return false;
			}

			$original_host   = $parsed['host'];
			$original_scheme = $parsed['scheme'] ?? 'https';
			$path            = $parsed['path'] ?? '/';
			$query           = ! empty( $parsed['query'] ) ? '?' . $parsed['query'] : '';

			// Extract host + port from the configured server URL.
			$server_parsed = wp_parse_url( self::$purge_server_url );
			$server_host   = $server_parsed['host'] ?? '127.0.0.1';
			$server_port   = isset( $server_parsed['port'] ) ? ':' . $server_parsed['port'] : '';

			// Use the original URL scheme so $scheme in the nginx cache key matches.
			$purge_url = $original_scheme . '://' . $server_host . $server_port . '/purge/' . ltrim( $path, '/' ) . $query;
			$purge_url = \esc_url_raw( $purge_url );

			if ( empty( $purge_url ) ) {
				return false;
			}

			$response = \wp_remote_get(
				$purge_url,
				array(
					'timeout'   => 5,
					'sslverify' => false,
					'headers'   => array(
						'Host' => $original_host,
					),
				)
			);

			if ( \is_wp_error( $response ) ) {
				return false;
			}

			$code = \wp_remote_retrieve_response_code( $response );
			return $code >= 200 && $code < 300;
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

			$url = \esc_url_raw( $url );

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
