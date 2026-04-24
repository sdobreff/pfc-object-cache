# PFC Object Cache

> Persistent WordPress object cache drop-in powered by [PHPFastCache](https://github.com/PHPSocialNetwork/phpfastcache).

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759b)](https://wordpress.org)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Features

- **Multiple drivers** — Files, Redis, Memcached, APCu, SQLite3, and all other PHPFastCache drivers
- **Static cache engine** — `PFC\ObjectCache\CacheEngine` provides a fully static API that can be used directly or through the `WP_Object_Cache` wrapper
- **Nginx cache purge** — Automatically (or manually) purge the nginx FastCGI / proxy cache when the object cache is flushed
- **Standalone admin menu** — Top-level WordPress admin page with statistics dashboard, driver configuration, and nginx purge settings
- **One-click cache management** — Flush all caches, flush individual cache groups, or purge the nginx cache
- **Multisite-safe** — Non-global cache groups are prefixed with the blog ID
- **Namespaced & strict** — All classes live under the `PFC\ObjectCache` namespace with `declare(strict_types=1)`
- **CLI / web unified cache** — Uses a single explicit cache directory so CLI (WP-CLI, cron) and web requests share the same cache store
- **WPCS-compliant** — Follows WordPress Coding Standards throughout
- **Secure** — Every action is protected with `current_user_can('manage_options')` + nonce verification; all input sanitised, all output escaped

---

## Requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.1 |
| WordPress | 6.4 |
| Composer | 2.x |

---

## Installation

### 1. Install via Composer

```bash
cd wp-content/plugins/
git clone https://github.com/your-org/pfc-object-cache.git
cd pfc-object-cache
composer install --no-dev --optimize-autoloader
```

### 2. Activate the plugin

Activate **PFC Object Cache** from **Plugins → Installed Plugins** in the WordPress admin.

On activation, the plugin copies `drop-in/object-cache.php` to `wp-content/object-cache.php`.

### 3. Configure your driver

Navigate to **Object Cache** in the WordPress admin menu, choose your preferred driver, and click **Save Settings**.

> **Important:** After changing the driver, deactivate and reactivate the plugin so the drop-in picks up the new configuration.

---

## Architecture

```
PFC\ObjectCache\CacheEngine    (static)  — All cache logic, driver bootstrap
PFC\ObjectCache\NginxCachePurger (static) — Nginx cache directory purging
PFC\ObjectCache\AdminPage       (static)  — Admin UI, settings persistence
PFC\ObjectCache\CacheManager    (static)  — Flush / stats helpers for admin
PFC\ObjectCache\DropInInstaller (static)  — Activation/deactivation lifecycle

WP_Object_Cache                 (global)  — Thin wrapper delegating to CacheEngine
wp_cache_*()                    (global)  — WordPress API functions
```

The `WP_Object_Cache` class in the drop-in is a thin wrapper. All persistent
cache logic lives in `CacheEngine`, which can also be called directly:

```php
use PFC\ObjectCache\CacheEngine;

CacheEngine::set( 'my_key', $data, 'my_group', 3600 );
$value = CacheEngine::get( 'my_key', 'my_group' );
```

---

## Nginx Cache Purge

The plugin can automatically purge the nginx FastCGI or proxy cache whenever
the object cache is flushed.

1. In **Object Cache → Driver Configuration**, enable **Nginx Cache Purge**.
2. Set the **Nginx Cache Path** to the absolute path of your `fastcgi_cache_path` or `proxy_cache_path` directory (e.g. `/var/run/nginx-cache`).
3. Save settings.

After this, any "Flush All Caches" action will also recursively delete the
nginx cache directory contents. You can also use the **Purge Nginx Cache**
button for a standalone purge.

> **Tip:** Ensure the web-server user (e.g. `www-data`) has write access to the
> nginx cache directory.

---

## CLI / Web Cache Directory

PHPFastCache can default to different temporary directories for CLI and web
contexts. This plugin avoids that problem by always setting an **explicit,
fixed cache path** (`wp-content/uploads/pfc-object-cache/cache/`).

This means WP-CLI commands, wp-cron, and web requests all share the same
cache store — no stale or split caches.

> **Note:** Ensure the CLI user and web-server user both have read/write
> permissions on the cache directory. The simplest approach is to run CLI
> commands as the web-server user: `sudo -u www-data wp cache flush`.

---

## Driver Reference

| Driver | Use Case | Persistence | Requires |
|---|---|---|---|
| **Files** | Shared hosting, zero infrastructure | ✅ Disk | Writable `wp-content/uploads/` |
| **Redis** | VPS / cloud, high-traffic | ✅ Optional | Redis server + `phpredis` or `predis` |
| **Memcached** | High-traffic, cache-only | ❌ RAM | Memcached server + `php-memcached` |
| **APCu** | Single-server, max speed | ❌ RAM | PHP `apcu` extension |
| **SQLite3** | Low-traffic, no Redis/Memcached | ✅ Disk | PHP `pdo_sqlite` extension |

---

## File Structure

```
pfc-object-cache/
├── pfc-object-cache.php          # Main plugin file (bootstrap)
├── composer.json
├── README.md
├── vendor/                       # Composer autoloader + PHPFastCache
├── classes/
│   ├── class-cache-engine.php    # PFC\ObjectCache\CacheEngine (static)
│   └── class-nginx-cache-purger.php  # PFC\ObjectCache\NginxCachePurger
├── drop-in/
│   └── object-cache.php          # Drop-in template (copied to wp-content/)
├── includes/
│   ├── class-pfc-drop-in-installer.php  # PFC\ObjectCache\DropInInstaller
│   ├── class-pfc-cache-manager.php      # PFC\ObjectCache\CacheManager
│   └── class-pfc-admin-page.php         # PFC\ObjectCache\AdminPage
├── templates/
│   └── admin-page.php
└── css/
    └── admin.css
```

---

## Smoke Test

Add this as a temporary mu-plugin to verify the drop-in is active:

```php
<?php
add_action( 'admin_notices', static function (): void {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    wp_cache_set( 'pfc_test', 'ok', 'pfc_smoke', 30 );
    $found = null;
    $val   = wp_cache_get( 'pfc_test', 'pfc_smoke', false, $found );
    echo $found && 'ok' === $val
        ? '<div class="notice notice-success"><p>PFC Object Cache: Active ✅</p></div>'
        : '<div class="notice notice-error"><p>PFC Object Cache: Not working ❌</p></div>';
    wp_cache_delete( 'pfc_test', 'pfc_smoke' );
} );
```

Or via WP-CLI:

```bash
wp cache type
wp cache flush
```

---

## Security

- All admin actions require `manage_options` capability
- All POST forms are protected with WordPress nonces (`wp_nonce_field` / `check_admin_referer`)
- All `$_POST` input is sanitised before use (`sanitize_key`, `sanitize_text_field`, `absint`)
- All output is escaped (`esc_html`, `esc_attr`, `esc_url`, `esc_js`)
- File operations use the `WP_Filesystem` API
- The installer will not overwrite a drop-in belonging to another plugin
- Every PHP file guards against direct access with `defined('ABSPATH') || exit`
- Nginx cache purge validates the path is safe (not a system directory) and writable
- `declare(strict_types=1)` is enabled on all PHP files to prevent type coercion bugs
- All classes are wrapped with `class_exists()` guards to prevent redeclaration
- The `var_export`-based config file only writes sanitised values and is protected by `.htaccess` + `index.php`

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
