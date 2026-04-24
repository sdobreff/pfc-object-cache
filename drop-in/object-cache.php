<?php
/**
 * PHPFastCache WordPress Object Cache Drop-In.
 *
 * This file is copied to wp-content/object-cache.php by the
 * PFC Object Cache plugin on activation. Do not edit directly.
 *
 * The heavy lifting is handled by PFC\ObjectCache\CacheEngine (static).
 * This file provides the global wp_cache_*() functions and a thin
 * WP_Object_Cache wrapper that WordPress core expects.
 *
 * @package PFC_Object_Cache
 * @version 1.1.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * PHPFastCache vendor autoloader.
 * Also registers the PFC\ObjectCache namespace classes via classmap.
 */
$pfc_autoloader = WP_CONTENT_DIR . '/plugins/pfc-object-cache/vendor/autoload.php';

if ( ! file_exists( $pfc_autoloader ) ) {
	// Fall back to WordPress core in-memory cache — do not fatal.
	return;
}

require_once $pfc_autoloader;

use PFC\ObjectCache\CacheEngine;
use PFC\ObjectCache\NginxCachePurger;

// ============================================================
// Global wrapper functions required by WordPress core.
// ============================================================

/**
 * Initialise the global cache object.
 *
 * WordPress calls wp_cache_init() from wp-settings.php.
 */
function wp_cache_init(): void {
	global $wp_object_cache;
	$wp_object_cache = new WP_Object_Cache();
}

/**
 * Closes the cache.
 *
 * Called on 'shutdown' by WordPress.
 */
function wp_cache_close(): bool {
	return true;
}

/**
 * Adds data to the cache if the cache key does not already exist.
 *
 * @param int|string $key    The cache key.
 * @param mixed      $data   The data to add.
 * @param string     $group  Optional. Cache group. Default 'default'.
 * @param int        $expire Optional. TTL in seconds. 0 = no expiration.
 */
function wp_cache_add(
	int|string $key,
	mixed $data,
	string $group = 'default',
	int $expire = 0
): bool {
	global $wp_object_cache;
	return $wp_object_cache->add( $key, $data, $group, $expire );
}

/**
 * Retrieves the cache contents by key and group.
 *
 * @param int|string $key    The cache key.
 * @param string     $group  Optional. Cache group. Default 'default'.
 * @param bool       $force  Optional. Force cache update. Default false.
 * @param bool|null  $found  Optional. Whether the key was found in the cache.
 */
function wp_cache_get(
	int|string $key,
	string $group = 'default',
	bool $force = false,
	?bool &$found = null
): mixed {
	global $wp_object_cache;
	return $wp_object_cache->get( $key, $group, $force, $found );
}

/**
 * Saves data to the cache.
 *
 * @param int|string $key    The cache key.
 * @param mixed      $data   The data to cache.
 * @param string     $group  Optional. Cache group. Default 'default'.
 * @param int        $expire Optional. TTL in seconds. 0 = no expiration.
 */
function wp_cache_set(
	int|string $key,
	mixed $data,
	string $group = 'default',
	int $expire = 0
): bool {
	global $wp_object_cache;
	return $wp_object_cache->set( $key, $data, $group, $expire );
}

/**
 * Replaces the contents of the cache with new data.
 *
 * @param int|string $key    The cache key.
 * @param mixed      $data   The replacement data.
 * @param string     $group  Optional. Cache group. Default 'default'.
 * @param int        $expire Optional. TTL in seconds. 0 = no expiration.
 */
function wp_cache_replace(
	int|string $key,
	mixed $data,
	string $group = 'default',
	int $expire = 0
): bool {
	global $wp_object_cache;
	return $wp_object_cache->replace( $key, $data, $group, $expire );
}

/**
 * Removes the cache contents matching key and group.
 *
 * @param int|string $key   The cache key.
 * @param string     $group Optional. Cache group. Default 'default'.
 */
function wp_cache_delete( int|string $key, string $group = 'default' ): bool {
	global $wp_object_cache;
	return $wp_object_cache->delete( $key, $group );
}

/**
 * Removes all cache items.
 */
function wp_cache_flush(): bool {
	global $wp_object_cache;
	return $wp_object_cache->flush();
}

/**
 * Removes all cache items in a group.
 *
 * @param string $group Cache group name.
 */
function wp_cache_flush_group( string $group ): bool {
	global $wp_object_cache;
	return $wp_object_cache->flush_group( $group );
}

/**
 * Clears only the in-memory (runtime) cache.
 */
function wp_cache_flush_runtime(): bool {
	global $wp_object_cache;
	return $wp_object_cache->flush_runtime();
}

/**
 * Increments numeric cache item's value.
 *
 * @param int|string $key    The cache key.
 * @param int        $offset Optional. Increment amount. Default 1.
 * @param string     $group  Optional. Cache group. Default 'default'.
 */
function wp_cache_incr(
	int|string $key,
	int $offset = 1,
	string $group = 'default'
): int|false {
	global $wp_object_cache;
	return $wp_object_cache->incr( $key, $offset, $group );
}

/**
 * Decrements numeric cache item's value.
 *
 * @param int|string $key    The cache key.
 * @param int        $offset Optional. Decrement amount. Default 1.
 * @param string     $group  Optional. Cache group. Default 'default'.
 */
function wp_cache_decr(
	int|string $key,
	int $offset = 1,
	string $group = 'default'
): int|false {
	global $wp_object_cache;
	return $wp_object_cache->decr( $key, $offset, $group );
}

/**
 * Adds multiple values to the cache in one call.
 *
 * @param array  $data   Array of key => value pairs.
 * @param string $group  Optional. Cache group. Default 'default'.
 * @param int    $expire Optional. TTL in seconds.
 */
function wp_cache_add_multiple(
	array $data,
	string $group = 'default',
	int $expire = 0
): array {
	global $wp_object_cache;
	return $wp_object_cache->add_multiple( $data, $group, $expire );
}

/**
 * Adds multiple values to the cache in one call.
 *
 * @param array  $data   Array of key => value pairs.
 * @param string $group  Optional. Cache group. Default 'default'.
 * @param int    $expire Optional. TTL in seconds.
 */
function wp_cache_set_multiple(
	array $data,
	string $group = 'default',
	int $expire = 0
): array {
	global $wp_object_cache;
	return $wp_object_cache->set_multiple( $data, $group, $expire );
}

/**
 * Retrieves multiple values from the cache by their keys.
 *
 * @param array  $keys  Array of cache keys.
 * @param string $group Optional. Cache group. Default 'default'.
 * @param bool   $force Optional. Force cache update.
 */
function wp_cache_get_multiple(
	array $keys,
	string $group = 'default',
	bool $force = false
): array {
	global $wp_object_cache;
	return $wp_object_cache->get_multiple( $keys, $group, $force );
}

/**
 * Deletes multiple values from the cache in one call.
 *
 * @param array  $keys  Array of cache keys.
 * @param string $group Optional. Cache group. Default 'default'.
 */
function wp_cache_delete_multiple( array $keys, string $group = 'default' ): array {
	global $wp_object_cache;
	return $wp_object_cache->delete_multiple( $keys, $group );
}

/**
 * Adds a group or set of groups to the list of global groups.
 *
 * @param string|string[] $groups Group(s) to add to the global list.
 */
function wp_cache_add_global_groups( string|array $groups ): void {
	global $wp_object_cache;
	$wp_object_cache->add_global_groups( $groups );
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|string[] $groups Group(s) to add to the non-persistent list.
 */
function wp_cache_add_non_persistent_groups( string|array $groups ): void {
	global $wp_object_cache;
	$wp_object_cache->add_non_persistent_groups( $groups );
}

/**
 * Switches the internal blog ID (Multisite).
 *
 * @param int $blog_id Site ID.
 */
function wp_cache_switch_to_blog( int $blog_id ): void {
	global $wp_object_cache;
	$wp_object_cache->switch_to_blog( $blog_id );
}

/**
 * Determines whether the object cache implementation supports a particular feature.
 *
 * @param string $feature Feature name.
 */
function wp_cache_supports( string $feature ): bool {
	return match ( $feature ) {
		'add_multiple',
		'set_multiple',
		'get_multiple',
		'delete_multiple',
		'flush_runtime',
		'flush_group' => true,
		default       => false,
	};
}

// ============================================================
// WP_Object_Cache class — thin wrapper around CacheEngine.
// ============================================================

if ( ! class_exists( 'WP_Object_Cache' ) ) {

	/**
	 * WordPress object cache backed by PFC\ObjectCache\CacheEngine.
	 *
	 * All persistent cache logic lives in CacheEngine (static).
	 * This class fulfils the contract WordPress expects from the
	 * global $wp_object_cache instance.
	 *
	 * @package PFC_Object_Cache
	 */
	class WP_Object_Cache {
	 // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

		/**
		 * Cache hit counter (kept in sync with CacheEngine).
		 *
		 * @var int
		 */
		public int $cache_hits = 0;

		/**
		 * Cache miss counter (kept in sync with CacheEngine).
		 *
		 * @var int
		 */
		public int $cache_misses = 0;

		/**
		 * Constructor — bootstraps the static CacheEngine.
		 */
		public function __construct() {
			CacheEngine::init();
			NginxCachePurger::init( CacheEngine::get_config() );
		}

		/**
		 * Copy hit/miss counters from the static engine.
		 */
		private function sync_stats(): void {
			$this->cache_hits   = CacheEngine::$cache_hits;
			$this->cache_misses = CacheEngine::$cache_misses;
		}

		// ----------------------------------------------------------
		// CRUD — delegates to CacheEngine
		// ----------------------------------------------------------

		public function add( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
			$result = CacheEngine::add( $key, $data, $group, $expire );
			$this->sync_stats();
			return $result;
		}

		public function set( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
			$result = CacheEngine::set( $key, $data, $group, $expire );
			$this->sync_stats();
			return $result;
		}

		public function get( int|string $key, string $group = 'default', bool $force = false, ?bool &$found = null ): mixed {
			$result = CacheEngine::get( $key, $group, $force, $found );
			$this->sync_stats();
			return $result;
		}

		public function replace( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
			$result = CacheEngine::replace( $key, $data, $group, $expire );
			$this->sync_stats();
			return $result;
		}

		public function delete( int|string $key, string $group = 'default' ): bool {
			$result = CacheEngine::delete( $key, $group );
			$this->sync_stats();
			return $result;
		}

		// ----------------------------------------------------------
		// Batch
		// ----------------------------------------------------------

		public function add_multiple( array $data, string $group = 'default', int $expire = 0 ): array {
			$result = CacheEngine::add_multiple( $data, $group, $expire );
			$this->sync_stats();
			return $result;
		}

		public function set_multiple( array $data, string $group = 'default', int $expire = 0 ): array {
			$result = CacheEngine::set_multiple( $data, $group, $expire );
			$this->sync_stats();
			return $result;
		}

		public function get_multiple( array $keys, string $group = 'default', bool $force = false ): array {
			$result = CacheEngine::get_multiple( $keys, $group, $force );
			$this->sync_stats();
			return $result;
		}

		public function delete_multiple( array $keys, string $group = 'default' ): array {
			$result = CacheEngine::delete_multiple( $keys, $group );
			$this->sync_stats();
			return $result;
		}

		// ----------------------------------------------------------
		// Flush
		// ----------------------------------------------------------

		public function flush(): bool {
			return CacheEngine::flush();
		}

		public function flush_group( string $group ): bool {
			return CacheEngine::flush_group( $group );
		}

		public function flush_runtime(): bool {
			return CacheEngine::flush_runtime();
		}

		// ----------------------------------------------------------
		// Increment / Decrement
		// ----------------------------------------------------------

		public function incr( int|string $key, int $offset = 1, string $group = 'default' ): int|false {
			$result = CacheEngine::incr( $key, $offset, $group );
			$this->sync_stats();
			return $result;
		}

		public function decr( int|string $key, int $offset = 1, string $group = 'default' ): int|false {
			$result = CacheEngine::decr( $key, $offset, $group );
			$this->sync_stats();
			return $result;
		}

		// ----------------------------------------------------------
		// Group management
		// ----------------------------------------------------------

		public function add_global_groups( string|array $groups ): void {
			CacheEngine::add_global_groups( $groups );
		}

		public function add_non_persistent_groups( string|array $groups ): void {
			CacheEngine::add_non_persistent_groups( $groups );
		}

		public function switch_to_blog( int $blog_id ): void {
			CacheEngine::switch_to_blog( $blog_id );
		}

		// ----------------------------------------------------------
		// Statistics
		// ----------------------------------------------------------

		/**
		 * Return statistics array for the admin dashboard.
		 *
		 * @return array{hits: int, misses: int, ratio: float, runtime_items: int}
		 */
		public function get_stats(): array {
			return CacheEngine::get_stats();
		}
	}

} // end class_exists check
