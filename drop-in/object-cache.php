<?php
/**
 * PHPFastCache WordPress Object Cache Drop-In.
 *
 * This file is copied to wp-content/object-cache.php by the
 * PFC Object Cache plugin on activation. Do not edit directly.
 *
 * @package PFC_Object_Cache
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * PHPFastCache vendor autoloader.
 * Resolved at install-time so the drop-in is self-contained.
 */
$pfc_autoloader = WP_CONTENT_DIR . '/plugins/pfc-object-cache/vendor/autoload.php';

if ( ! file_exists( $pfc_autoloader ) ) {
	// Fall back to WordPress core in-memory cache — do not fatal.
	return;
}

require_once $pfc_autoloader;

use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;

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
function wp_cache_set_multiple(
	array $data,
	string $group = 'default',
	int $expire = 0
): array {
	global $wp_object_cache;
	$results = array();
	foreach ( $data as $key => $value ) {
		$results[ $key ] = $wp_object_cache->set( $key, $value, $group, $expire );
	}
	return $results;
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
	$results = array();
	foreach ( $keys as $key ) {
		$results[ $key ] = $wp_object_cache->get( $key, $group, $force );
	}
	return $results;
}

/**
 * Deletes multiple values from the cache in one call.
 *
 * @param array  $keys  Array of cache keys.
 * @param string $group Optional. Cache group. Default 'default'.
 */
function wp_cache_delete_multiple( array $keys, string $group = 'default' ): array {
	global $wp_object_cache;
	$results = array();
	foreach ( $keys as $key ) {
		$results[ $key ] = $wp_object_cache->delete( $key, $group );
	}
	return $results;
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
// WP_Object_Cache class
// ============================================================

/**
 * Core class for WordPress object cache backed by PHPFastCache.
 *
 * @package PFC_Object_Cache
 */
class WP_Object_Cache { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

	/**
	 * PHPFastCache pool instance.
	 *
	 * @var \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
	 */
	protected \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface $driver;

	/**
	 * In-memory (runtime) cache to avoid redundant driver calls within a request.
	 *
	 * @var array<string, mixed>
	 */
	protected array $cache = array();

	/**
	 * Groups that should not be persisted across requests.
	 *
	 * @var array<string, true>
	 */
	protected array $non_persistent_groups = array();

	/**
	 * Groups that are shared across sites in a Multisite network.
	 *
	 * @var array<string, true>
	 */
	protected array $global_groups = array();

	/**
	 * Current blog ID (Multisite support).
	 *
	 * @var int
	 */
	protected int $blog_prefix = 1;

	/**
	 * Cache hit counter.
	 *
	 * @var int
	 */
	public int $cache_hits = 0;

	/**
	 * Cache miss counter.
	 *
	 * @var int
	 */
	public int $cache_misses = 0;

	/**
	 * Constructor — bootstraps the PHPFastCache driver.
	 */
	public function __construct() {
		$this->blog_prefix = is_multisite() ? get_current_blog_id() : 1;
		$this->driver      = $this->boot_driver();
	}

	// ----------------------------------------------------------
	// Driver bootstrap
	// ----------------------------------------------------------

	/**
	 * Instantiate the correct PHPFastCache driver from wp_options.
	 *
	 * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
	 */
	protected function boot_driver(): \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface {
		$options = get_option( 'pfc_cache_settings', array() );
		$driver  = sanitize_key( $options['driver'] ?? 'Files' );

		$config_array = $this->build_driver_config( $driver, $options );

		try {
			CacheManager::setDefaultConfig( new ConfigurationOption( $config_array ) );
			return CacheManager::getInstance( $driver );
		} catch ( PhpfastcacheDriverException $e ) {
			// Fallback to Files driver on any driver boot failure.
			CacheManager::setDefaultConfig(
				new ConfigurationOption( array( 'path' => $this->get_cache_path() ) )
			);
			return CacheManager::getInstance( 'Files' );
		}
	}

	/**
	 * Build driver-specific configuration array.
	 *
	 * @param  string $driver  Driver name (e.g. 'Redis', 'Files').
	 * @param  array  $options Saved plugin settings.
	 * @return array
	 */
	protected function build_driver_config( string $driver, array $options ): array {
		$base = array();

		switch ( $driver ) {
			case 'Redis':
				$base = array(
					'host'     => sanitize_text_field( $options['redis_host'] ?? '127.0.0.1' ),
					'port'     => absint( $options['redis_port'] ?? 6379 ),
					'password' => $options['redis_password'] ?? '',
					'database' => absint( $options['redis_database'] ?? 0 ),
					'timeout'  => absint( $options['redis_timeout'] ?? 5 ),
				);
				break;

			case 'Memcached':
				$base = array(
					'host' => sanitize_text_field( $options['memcached_host'] ?? '127.0.0.1' ),
					'port' => absint( $options['memcached_port'] ?? 11211 ),
				);
				break;

			case 'Files':
			default:
				$base = array( 'path' => $this->get_cache_path() );
				break;
		}

		return $base;
	}

	/**
	 * Return the filesystem path used for the Files driver.
	 *
	 * @return string
	 */
	protected function get_cache_path(): string {
		$path = WP_CONTENT_DIR . '/cache/pfc-object-cache';
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}
		return $path;
	}

	// ----------------------------------------------------------
	// Cache key helpers
	// ----------------------------------------------------------

	/**
	 * Build a namespaced cache key.
	 *
	 * Non-global groups are prefixed with the blog ID to prevent
	 * cross-site data leakage in Multisite environments.
	 *
	 * @param  int|string $key   Raw cache key.
	 * @param  string     $group Cache group.
	 * @return string
	 */
	protected function build_key( int|string $key, string $group ): string {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$prefix = isset( $this->global_groups[ $group ] )
			? 'global'
			: (string) $this->blog_prefix;

		// PHPFastCache keys must be alphanumeric + underscores/dashes.
		$safe_key   = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', (string) $key );
		$safe_group = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', $group );

		return "{$prefix}_{$safe_group}_{$safe_key}";
	}

	/**
	 * Whether a group is non-persistent (runtime only).
	 *
	 * @param  string $group Group name.
	 * @return bool
	 */
	protected function is_non_persistent( string $group ): bool {
		return isset( $this->non_persistent_groups[ $group ] );
	}

	// ----------------------------------------------------------
	// CRUD operations
	// ----------------------------------------------------------

	/**
	 * Adds data to the cache only if the key does not already exist.
	 *
	 * @param  int|string $key    Cache key.
	 * @param  mixed      $data   Value to cache.
	 * @param  string     $group  Cache group.
	 * @param  int        $expire TTL in seconds.
	 * @return bool
	 */
	public function add( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
		$built = $this->build_key( $key, $group );

		if ( array_key_exists( $built, $this->cache ) ) {
			return false;
		}

		if ( ! $this->is_non_persistent( $group ) ) {
			$item = $this->driver->getItem( $built );
			if ( $item->isHit() ) {
				$this->cache[ $built ] = $item->get();
				return false;
			}
		}

		return $this->set( $key, $data, $group, $expire );
	}

	/**
	 * Saves data to the cache.
	 *
	 * @param  int|string $key    Cache key.
	 * @param  mixed      $data   Value to cache.
	 * @param  string     $group  Cache group.
	 * @param  int        $expire TTL in seconds. 0 = no expiry.
	 * @return bool
	 */
	public function set( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
		$built                 = $this->build_key( $key, $group );
		$this->cache[ $built ] = $data;

		if ( $this->is_non_persistent( $group ) ) {
			return true;
		}

		$item = $this->driver->getItem( $built );
		$item->set( $data );
		$item->addTag( $group );

		if ( $expire > 0 ) {
			$item->expiresAfter( $expire );
		}

		return $this->driver->save( $item );
	}

	/**
	 * Retrieves the cache contents, if it exists.
	 *
	 * @param  int|string  $key    Cache key.
	 * @param  string      $group  Cache group.
	 * @param  bool        $force  Whether to force an update of the local cache.
	 * @param  bool|null   $found  Whether the key was found in the cache.
	 * @return mixed|false
	 */
	public function get(
		int|string $key,
		string $group = 'default',
		bool $force = false,
		?bool &$found = null
	): mixed {
		$built = $this->build_key( $key, $group );

		if ( ! $force && array_key_exists( $built, $this->cache ) ) {
			$found = true;
			++$this->cache_hits;
			return $this->cache[ $built ];
		}

		if ( $this->is_non_persistent( $group ) ) {
			$found = false;
			++$this->cache_misses;
			return false;
		}

		$item = $this->driver->getItem( $built );

		if ( $item->isHit() ) {
			$found                 = true;
			$value                 = $item->get();
			$this->cache[ $built ] = $value;
			++$this->cache_hits;
			return $value;
		}

		$found = false;
		++$this->cache_misses;
		return false;
	}

	/**
	 * Replaces the contents in the cache, if the key already exists.
	 *
	 * @param  int|string $key    Cache key.
	 * @param  mixed      $data   Value to cache.
	 * @param  string     $group  Cache group.
	 * @param  int        $expire TTL in seconds.
	 * @return bool
	 */
	public function replace( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
		$built = $this->build_key( $key, $group );

		if ( ! array_key_exists( $built, $this->cache ) && ! $this->is_non_persistent( $group ) ) {
			$item = $this->driver->getItem( $built );
			if ( ! $item->isHit() ) {
				return false;
			}
		}

		return $this->set( $key, $data, $group, $expire );
	}

	/**
	 * Removes the cache entry matching key and group.
	 *
	 * @param  int|string $key   Cache key.
	 * @param  string     $group Cache group.
	 * @return bool
	 */
	public function delete( int|string $key, string $group = 'default' ): bool {
		$built = $this->build_key( $key, $group );
		unset( $this->cache[ $built ] );

		if ( $this->is_non_persistent( $group ) ) {
			return true;
		}

		return $this->driver->deleteItem( $built );
	}

	/**
	 * Clears the object cache of all data.
	 *
	 * @return bool
	 */
	public function flush(): bool {
		$this->cache = array();
		return $this->driver->clear();
	}

	/**
	 * Removes all cache items in a group using PHPFastCache tag invalidation.
	 *
	 * @param  string $group Cache group name.
	 * @return bool
	 */
	public function flush_group( string $group ): bool {
		// Clear from runtime cache.
		$prefix = $this->build_key( '', $group );
		foreach ( array_keys( $this->cache ) as $key ) {
			if ( str_starts_with( $key, $prefix ) ) {
				unset( $this->cache[ $key ] );
			}
		}

		// Ask PHPFastCache to invalidate by tag.
		try {
			return $this->driver->deleteItemsByTag( $group );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Increments numeric cache item's value.
	 *
	 * @param  int|string $key    Cache key.
	 * @param  int        $offset Amount to increment.
	 * @param  string     $group  Cache group.
	 * @return int|false
	 */
	public function incr( int|string $key, int $offset = 1, string $group = 'default' ): int|false {
		$value = $this->get( $key, $group );
		if ( false === $value || ! is_numeric( $value ) ) {
			return false;
		}
		$new = (int) $value + max( 0, $offset );
		return $this->set( $key, $new, $group ) ? $new : false;
	}

	/**
	 * Decrements numeric cache item's value.
	 *
	 * @param  int|string $key    Cache key.
	 * @param  int        $offset Amount to decrement.
	 * @param  string     $group  Cache group.
	 * @return int|false
	 */
	public function decr( int|string $key, int $offset = 1, string $group = 'default' ): int|false {
		$value = $this->get( $key, $group );
		if ( false === $value || ! is_numeric( $value ) ) {
			return false;
		}
		$new = max( 0, (int) $value - max( 0, $offset ) );
		return $this->set( $key, $new, $group ) ? $new : false;
	}

	// ----------------------------------------------------------
	// Group management
	// ----------------------------------------------------------

	/**
	 * Adds groups to the list of global cache groups.
	 *
	 * @param  string|string[] $groups Group(s) to add.
	 * @return void
	 */
	public function add_global_groups( string|array $groups ): void {
		foreach ( (array) $groups as $group ) {
			$this->global_groups[ (string) $group ] = true;
		}
	}

	/**
	 * Adds groups to the list of non-persistent groups.
	 *
	 * @param  string|string[] $groups Group(s) to add.
	 * @return void
	 */
	public function add_non_persistent_groups( string|array $groups ): void {
		foreach ( (array) $groups as $group ) {
			$this->non_persistent_groups[ (string) $group ] = true;
		}
	}

	/**
	 * Switches the internal blog ID (Multisite).
	 *
	 * @param  int $blog_id Site ID.
	 * @return void
	 */
	public function switch_to_blog( int $blog_id ): void {
		$this->blog_prefix = $blog_id;
	}

	// ----------------------------------------------------------
	// Statistics
	// ----------------------------------------------------------

	/**
	 * Returns cache statistics array for the admin dashboard.
	 *
	 * @return array{hits: int, misses: int, ratio: float, runtime_items: int}
	 */
	public function get_stats(): array {
		$total = $this->cache_hits + $this->cache_misses;
		return array(
			'hits'          => $this->cache_hits,
			'misses'        => $this->cache_misses,
			'ratio'         => $total > 0 ? round( ( $this->cache_hits / $total ) * 100, 1 ) : 0.0,
			'runtime_items' => count( $this->cache ),
		);
	}
}
