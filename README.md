# PFC Object Cache

> Persistent WordPress object cache drop-in powered by [PHPFastCache](https://github.com/PHPSocialNetwork/phpfastcache).

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759b)](https://wordpress.org)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Features

- **Multiple drivers** — Files, Redis, Memcached, APCu, SQLite3, and all other PHPFastCache drivers
- **Standalone admin menu** — Top-level WordPress admin page with statistics dashboard
- **One-click cache management** — Flush all caches or flush individual cache groups
- **Multisite-safe** — Non-global cache groups are prefixed with the blog ID
- **WPCS-compliant** — Follows WordPress Coding Standards throughout
- **Secure** — Every action is protected with `current_user_can('manage_options')` + nonce verification

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

## Driver Reference

| Driver | Use Case | Persistence | Requires |
|---|---|---|---|
| **Files** | Shared hosting, zero infrastructure | ✅ Disk | Writable `wp-content/cache/` |
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
├── drop-in/
│   └── object-cache.php          # Drop-in template (copied to wp-content/)
├── includes/
│   ├── class-pfc-drop-in-installer.php
│   ├── class-pfc-cache-manager.php
│   └── class-pfc-admin-page.php
├── templates/
│   └── admin-page.php
└── assets/
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

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
