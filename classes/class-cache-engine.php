<?php
/**
 * Static cache engine backed by PHPFastCache.
 *
 * All heavy lifting lives here. The WP_Object_Cache class in the
 * drop-in is a thin wrapper that delegates to these static methods.
 *
 * @package PFC_Object_Cache
 */

declare(strict_types=1);

namespace PFC\ObjectCache;

defined( 'ABSPATH' ) || exit;

use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;

if ( ! class_exists( CacheEngine::class ) ) {

	/**
	 * Static cache engine.
	 */
	class CacheEngine {

		/**
		 * Whether init() has already run.
		 *
		 * @var bool
		 */
		private static bool $initialized = false;

		/**
		 * PHPFastCache pool instance.
		 *
		 * @var \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface|null
		 */
		private static ?\Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface $driver = null;

		/**
		 * In-memory (runtime) cache.
		 *
		 * @var array<string, mixed>
		 */
		private static array $cache = array();

		/**
		 * Groups that should not be persisted.
		 *
		 * @var array<string, true>
		 */
		private static array $non_persistent_groups = array();

		/**
		 * Groups shared across Multisite sites.
		 *
		 * @var array<string, true>
		 */
		private static array $global_groups = array();

		/**
		 * Blog prefix for Multisite key scoping.
		 *
		 * @var int
		 */
		private static int $blog_prefix = 1;

		/**
		 * Cache hit counter.
		 *
		 * @var int
		 */
		public static int $cache_hits = 0;

		/**
		 * Cache miss counter.
		 *
		 * @var int
		 */
		public static int $cache_misses = 0;

		/**
		 * Loaded configuration array.
		 *
		 * @var array
		 */
		private static array $config = array();

		/**
		 * Cached result of get_cache_path().
		 *
		 * @var string
		 */
		private static string $resolved_cache_path = '';

		// ----------------------------------------------------------
		// Initialization
		// ----------------------------------------------------------

		/**
		 * Initialize the engine. Idempotent.
		 */
		public static function init(): void {
			if ( self::$initialized ) {
				return;
			}

			self::$blog_prefix = is_multisite() ? get_current_blog_id() : 1;
			self::$driver      = self::boot_driver();
			self::$initialized = true;
		}

		/**
		 * Whether the engine has been initialized.
		 */
		public static function is_initialized(): bool {
			return self::$initialized;
		}

		// ----------------------------------------------------------
		// Driver bootstrap
		// ----------------------------------------------------------

		/**
		 * Boot the PHPFastCache driver from the static config file.
		 *
		 * Reads a PHP config file written by the admin page so we
		 * never call get_option() (which would recurse).
		 *
		 * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
		 */
		protected static function boot_driver(): \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface {
			$config_dir  = defined( 'PFC_CONFIG_DIR' ) ? PFC_CONFIG_DIR : WP_CONTENT_DIR . '/uploads/pfc-object-cache';
			$config_file = $config_dir . '/pfc-config.php';
			$options     = file_exists( $config_file ) ? (array) include $config_file : array();

			self::$config = $options;

			$allowed = array(
				'files'     => 'Files',
				'redis'     => 'Redis',
				'memcached' => 'Memcached',
				'apcu'      => 'Apcu',
				'sqlite3'   => 'Sqlite3',
			);
			$raw     = strtolower( trim( (string) ( $options['driver'] ?? 'Files' ) ) );
			$driver  = $allowed[ $raw ] ?? 'Files';

			$config_array = self::build_driver_config( $driver, $options );

			try {
				CacheManager::setDefaultConfig( new ConfigurationOption( $config_array ) );
				return CacheManager::getInstance( $driver );
			} catch ( PhpfastcacheDriverException $e ) {
				CacheManager::setDefaultConfig(
					new ConfigurationOption( array( 'path' => self::get_cache_path() ) )
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
		protected static function build_driver_config( string $driver, array $options ): array {
			$base = array();

			switch ( $driver ) {
				case 'Redis':
					$base = array(
						'host'     => (string) ( $options['redis_host'] ?? '127.0.0.1' ),
						'port'     => (int) ( $options['redis_port'] ?? 6379 ),
						'password' => (string) ( $options['redis_password'] ?? '' ),
						'database' => (int) ( $options['redis_database'] ?? 0 ),
						'timeout'  => (int) ( $options['redis_timeout'] ?? 5 ),
					);
					break;

				case 'Memcached':
					$base = array(
						'host' => (string) ( $options['memcached_host'] ?? '127.0.0.1' ),
						'port' => (int) ( $options['memcached_port'] ?? 11211 ),
					);
					break;

				case 'Sqlite3':
					$base = array( 'path' => self::get_cache_path() );
					break;

				case 'Files':
				default:
					$base = array( 'path' => self::get_cache_path() );
					break;
			}

			return $base;
		}

		/**
		 * Return the filesystem path used for file-based drivers.
		 *
		 * Uses an explicit, fixed path so that CLI (WP-CLI, cron) and
		 * web requests share the same cache directory.
		 *
		 * @return string
		 */
		public static function get_cache_path(): string {
			if ( '' !== self::$resolved_cache_path ) {
				return self::$resolved_cache_path;
			}

			$path = self::$config['cache_path']
				?? WP_CONTENT_DIR . '/uploads/pfc-object-cache/cache';

			if ( ! is_dir( $path ) ) {
				\wp_mkdir_p( $path );
			}

			self::$resolved_cache_path = $path;
			return $path;
		}

		// ----------------------------------------------------------
		// Key helpers
		// ----------------------------------------------------------

		/**
		 * Build a namespaced cache key.
		 *
		 * @param  int|string $key   Raw cache key.
		 * @param  string     $group Cache group.
		 * @return string
		 */
		protected static function build_key( int|string $key, string $group ): string {
			if ( empty( $group ) ) {
				$group = 'default';
			}

			$prefix = isset( self::$global_groups[ $group ] )
				? 'global'
				: (string) self::$blog_prefix;

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
		protected static function is_non_persistent( string $group ): bool {
			return isset( self::$non_persistent_groups[ $group ] );
		}

		// ----------------------------------------------------------
		// CRUD operations
		// ----------------------------------------------------------

		/**
		 * Add data only if the key does not already exist.
		 *
		 * @param  int|string $key    Cache key.
		 * @param  mixed      $data   Value to cache.
		 * @param  string     $group  Cache group.
		 * @param  int        $expire TTL in seconds.
		 * @return bool
		 */
		public static function add( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
			if ( wp_suspend_cache_addition() ) {
				return false;
			}

			$built = self::build_key( $key, $group );

			if ( array_key_exists( $built, self::$cache ) ) {
				return false;
			}

			if ( ! self::is_non_persistent( $group ) && null !== self::$driver ) {
				$item = self::$driver->getItem( $built );
				if ( $item->isHit() ) {
					self::$cache[ $built ] = $item->get();
					return false;
				}
			}

			return self::set( $key, $data, $group, $expire );
		}

		/**
		 * Save data to the cache.
		 *
		 * @param  int|string $key    Cache key.
		 * @param  mixed      $data   Value to cache.
		 * @param  string     $group  Cache group.
		 * @param  int        $expire TTL in seconds. 0 = no expiry.
		 * @return bool
		 */
		public static function set( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
			$built                 = self::build_key( $key, $group );
			self::$cache[ $built ] = $data;

			if ( self::is_non_persistent( $group ) || null === self::$driver ) {
				return true;
			}

			$item = self::$driver->getItem( $built );
			$item->set( $data );
			$item->addTag( $group );

			if ( $expire > 0 ) {
				$item->expiresAfter( $expire );
			}

			return self::$driver->save( $item );
		}

		/**
		 * Retrieve the cache contents, if it exists.
		 *
		 * @param  int|string $key   Cache key.
		 * @param  string     $group Cache group.
		 * @param  bool       $force Force update from persistent store.
		 * @param  bool|null  $found Whether the key was found.
		 * @return mixed|false
		 */
		public static function get(
			int|string $key,
			string $group = 'default',
			bool $force = false,
			?bool &$found = null
		): mixed {
			$built = self::build_key( $key, $group );

			if ( ! $force && array_key_exists( $built, self::$cache ) ) {
				$found = true;
				++self::$cache_hits;
				return self::$cache[ $built ];
			}

			if ( self::is_non_persistent( $group ) || null === self::$driver ) {
				$found = false;
				++self::$cache_misses;
				return false;
			}

			$item = self::$driver->getItem( $built );

			if ( $item->isHit() ) {
				$found                 = true;
				$value                 = $item->get();
				self::$cache[ $built ] = $value;
				++self::$cache_hits;
				return $value;
			}

			$found = false;
			++self::$cache_misses;
			return false;
		}

		/**
		 * Replace the contents if the key already exists.
		 *
		 * @param  int|string $key    Cache key.
		 * @param  mixed      $data   Value to cache.
		 * @param  string     $group  Cache group.
		 * @param  int        $expire TTL in seconds.
		 * @return bool
		 */
		public static function replace( int|string $key, mixed $data, string $group = 'default', int $expire = 0 ): bool {
			$built = self::build_key( $key, $group );

			if ( ! array_key_exists( $built, self::$cache ) && ! self::is_non_persistent( $group ) && null !== self::$driver ) {
				$item = self::$driver->getItem( $built );
				if ( ! $item->isHit() ) {
					return false;
				}
			}

			return self::set( $key, $data, $group, $expire );
		}

		/**
		 * Remove a cache entry.
		 *
		 * @param  int|string $key   Cache key.
		 * @param  string     $group Cache group.
		 * @return bool
		 */
		public static function delete( int|string $key, string $group = 'default' ): bool {
			$built = self::build_key( $key, $group );
			unset( self::$cache[ $built ] );

			if ( self::is_non_persistent( $group ) || null === self::$driver ) {
				return true;
			}

			return self::$driver->deleteItem( $built );
		}

		// ----------------------------------------------------------
		// Batch operations
		// ----------------------------------------------------------

		/**
		 * Add multiple values.
		 *
		 * @param  array  $data   Key => value pairs.
		 * @param  string $group  Cache group.
		 * @param  int    $expire TTL in seconds.
		 * @return bool[]
		 */
		public static function add_multiple( array $data, string $group = 'default', int $expire = 0 ): array {
			$values = array();
			foreach ( $data as $key => $value ) {
				$values[ $key ] = self::add( $key, $value, $group, $expire );
			}
			return $values;
		}

		/**
		 * Set multiple values.
		 *
		 * @param  array  $data   Key => value pairs.
		 * @param  string $group  Cache group.
		 * @param  int    $expire TTL in seconds.
		 * @return bool[]
		 */
		public static function set_multiple( array $data, string $group = 'default', int $expire = 0 ): array {
			$values = array();
			foreach ( $data as $key => $value ) {
				$values[ $key ] = self::set( $key, $value, $group, $expire );
			}
			return $values;
		}

		/**
		 * Get multiple values.
		 *
		 * @param  array  $keys  Cache keys.
		 * @param  string $group Cache group.
		 * @param  bool   $force Force update from persistent store.
		 * @return array
		 */
		public static function get_multiple( array $keys, string $group = 'default', bool $force = false ): array {
			$values = array();
			foreach ( $keys as $key ) {
				$values[ $key ] = self::get( $key, $group, $force );
			}
			return $values;
		}

		/**
		 * Delete multiple values.
		 *
		 * @param  array  $keys  Cache keys.
		 * @param  string $group Cache group.
		 * @return bool[]
		 */
		public static function delete_multiple( array $keys, string $group = 'default' ): array {
			$values = array();
			foreach ( $keys as $key ) {
				$values[ $key ] = self::delete( $key, $group );
			}
			return $values;
		}

		// ----------------------------------------------------------
		// Flush
		// ----------------------------------------------------------

		/**
		 * Clear all data from the cache.
		 *
		 * @return bool
		 */
		public static function flush(): bool {
			self::$cache = array();

			$result = null !== self::$driver ? self::$driver->clear() : true;

			NginxCachePurger::purge_all();

			return $result;
		}

		/**
		 * Clear all items in a single group (tag-based invalidation).
		 *
		 * @param  string $group Cache group name.
		 * @return bool
		 */
		public static function flush_group( string $group ): bool {
			$prefix = self::build_key( '', $group );
			foreach ( array_keys( self::$cache ) as $key ) {
				if ( str_starts_with( $key, $prefix ) ) {
					unset( self::$cache[ $key ] );
				}
			}

			if ( null === self::$driver ) {
				return true;
			}

			try {
				return self::$driver->deleteItemsByTag( $group );
			} catch ( \Throwable $e ) {
				return false;
			}
		}

		/**
		 * Clear only the runtime (in-memory) cache.
		 *
		 * @return bool
		 */
		public static function flush_runtime(): bool {
			self::$cache = array();
			return true;
		}

		// ----------------------------------------------------------
		// Increment / Decrement
		// ----------------------------------------------------------

		/**
		 * Increment a numeric cache item's value.
		 *
		 * @param  int|string $key    Cache key.
		 * @param  int        $offset Increment amount.
		 * @param  string     $group  Cache group.
		 * @return int|false
		 */
		public static function incr( int|string $key, int $offset = 1, string $group = 'default' ): int|false {
			$found = null;
			$value = self::get( $key, $group, false, $found );

			if ( ! $found || ! is_numeric( $value ) ) {
				return false;
			}

			$new = max( 0, (int) $value + (int) $offset );

			return self::set( $key, $new, $group ) ? $new : false;
		}

		/**
		 * Decrement a numeric cache item's value.
		 *
		 * @param  int|string $key    Cache key.
		 * @param  int        $offset Decrement amount.
		 * @param  string     $group  Cache group.
		 * @return int|false
		 */
		public static function decr( int|string $key, int $offset = 1, string $group = 'default' ): int|false {
			$found = null;
			$value = self::get( $key, $group, false, $found );

			if ( ! $found || ! is_numeric( $value ) ) {
				return false;
			}

			$new = max( 0, (int) $value - (int) $offset );

			return self::set( $key, $new, $group ) ? $new : false;
		}

		// ----------------------------------------------------------
		// Group management
		// ----------------------------------------------------------

		/**
		 * Add groups to the global groups list.
		 *
		 * @param  string|string[] $groups Group(s) to add.
		 */
		public static function add_global_groups( string|array $groups ): void {
			foreach ( (array) $groups as $group ) {
				self::$global_groups[ (string) $group ] = true;
			}
		}

		/**
		 * Add groups to the non-persistent list.
		 *
		 * @param  string|string[] $groups Group(s) to add.
		 */
		public static function add_non_persistent_groups( string|array $groups ): void {
			foreach ( (array) $groups as $group ) {
				self::$non_persistent_groups[ (string) $group ] = true;
			}
		}

		/**
		 * Switch the blog prefix (Multisite).
		 *
		 * @param  int $blog_id Site ID.
		 */
		public static function switch_to_blog( int $blog_id ): void {
			self::$blog_prefix = $blog_id;
		}

		// ----------------------------------------------------------
		// Statistics / helpers
		// ----------------------------------------------------------

		/**
		 * Return statistics array.
		 *
		 * @return array{hits: int, misses: int, ratio: float, runtime_items: int}
		 */
		public static function get_stats(): array {
			$total = self::$cache_hits + self::$cache_misses;
			return array(
				'hits'          => self::$cache_hits,
				'misses'        => self::$cache_misses,
				'ratio'         => $total > 0 ? round( ( self::$cache_hits / $total ) * 100, 1 ) : 0.0,
				'runtime_items' => count( self::$cache ),
			);
		}

		/**
		 * Return the loaded configuration array.
		 *
		 * @return array
		 */
		public static function get_config(): array {
			return self::$config;
		}
	}
}
