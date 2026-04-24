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
the object cache is flushed, and also **auto-purge individual URLs** when
content is updated or deleted.

### Full Cache Purge (directory-based)

1. In **Object Cache → Driver Configuration**, enable **Nginx Cache Purge**.
2. Set the **Nginx Cache Path** to the absolute path of your `fastcgi_cache_path` or `proxy_cache_path` directory (e.g. `/var/run/nginx-cache`).
3. Save settings.

After this, any "Flush All Caches" action will also recursively delete the
nginx cache directory contents. You can also use the **Purge Nginx Cache**
button for a standalone purge.

> **Tip:** Ensure the web-server user (e.g. `www-data`) has write access to the
> nginx cache directory.

### Auto-Purge on Content Changes (per-URL)

When a post, comment, or term is created, updated, or deleted, the plugin can
automatically send purge requests for the affected URLs (permalink, home page,
archives, feeds, etc.) so visitors immediately see fresh content.

This requires the **nginx `fastcgi_cache_purge` module** (or the equivalent
`proxy_cache_purge`) compiled into your nginx build.

#### 1. Nginx configuration

Add a `/purge/` location block inside your `server {}`:

```nginx
# FastCGI cache zone — adjust path, levels and keys_zone to match your setup.
fastcgi_cache_path /var/run/nginx-cache levels=1:2
                   keys_zone=WORDPRESS:100m inactive=60m;
fastcgi_cache_key  "$scheme$request_method$host$request_uri";

server {
    # ... your existing config ...

    # Cache-purge endpoint — only accessible from localhost.
    location ~ /purge(/.*) {
        allow 127.0.0.1;
        deny all;

        fastcgi_cache_purge WORDPRESS "$scheme$request_method$host$1";
    }
}
```

**Key points:**

| Directive | Purpose |
|---|---|
| `fastcgi_cache_key` | Defines how nginx builds the cache key. The purge must reconstruct the same key. |
| `location ~ /purge(/.*) {}` | Captures the original path via `$1` and purges the matching cache entry. |
| `allow 127.0.0.1; deny all;` | Restricts purge requests to localhost for security. |

> **Important:** The `$scheme` in the cache key means HTTPS and HTTP entries are
> stored separately. The plugin preserves the original URL scheme when sending
> purge requests so the key matches correctly.

#### 2. Plugin settings

1. In **Object Cache → Driver Configuration → Nginx Cache Purge**, enable
   **Nginx Cache Purge** (checkbox).
2. Set the **Purge Server URL** to the loopback address and port nginx listens
   on, e.g. `http://127.0.0.1:80` or `https://127.0.0.1:443`.
3. Enable **Auto-purge nginx cache when content is updated or deleted**
   (checkbox).
4. Save settings.

#### 3. How it works

When a post is updated, the plugin collects all related URLs:

- The post permalink
- The home / front page
- The post type archive
- Category and tag archive pages for the post
- The main RSS feed

For each URL, the plugin sends a **GET** request to the purge server:

```
GET https://127.0.0.1/purge/my-post-slug/
Host: yourdomain.com
```

Nginx matches this against `location ~ /purge(/.*) {}`, extracts `/my-post-slug/`
as `$1`, and purges the cache entry whose key is
`httpsGETyourdomain.com/my-post-slug/`.

> **Note:** The request uses the **same scheme** (`https://`) as the original
> public URL so that `$scheme` in the cache key matches the cached entry. If
> your site is HTTPS but you set the purge server URL to `http://127.0.0.1`,
> the scheme mismatch will cause purge failures (404).

#### 4. Purged content types

| Event | URLs purged |
|---|---|
| Post created / updated / trashed / deleted | Permalink, home, post type archive, category & tag archives, feed |
| Comment added / edited / deleted / status changed | Same as the parent post |
| Term (category / tag) edited / deleted | Term archive, home page |

#### 5. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Purge returns **404** | Cache key mismatch — usually a scheme (`http` vs `https`) or host mismatch | Ensure the purge server URL scheme matches how visitors access the site |
| Purge returns **403** | Nginx `allow/deny` blocking the request | Add the server's loopback IP to the `allow` list in the `/purge/` location |
| Purge returns **connection refused** | Wrong port or nginx not listening on that address | Verify the purge server URL and port match your nginx `listen` directive |
| Nothing happens on post save | Auto-purge not enabled or no server URL configured | Check both the **Auto-purge** checkbox and **Purge Server URL** are set |

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
