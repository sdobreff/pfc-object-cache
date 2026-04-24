<?php
/**
 * Admin page template for PFC Object Cache.
 *
 * Variables provided by AdminPage::render_page():
 *
 * @var array  $stats    Cache statistics.
 * @var array  $settings Saved plugin settings.
 * @var string $status   Redirect status slug.
 * @var string $message  Redirect notice message.
 * @var string $driver   Active driver slug.
 *
 * @package PFC_Object_Cache
 */

declare(strict_types=1);

use PFC\ObjectCache\AdminPage;

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap pfc-admin-wrap">

	<!-- ── Page Header ──────────────────────────────────────────────────── -->
	<div class="pfc-page-header">
		<div class="pfc-page-header__left">
			<span class="dashicons dashicons-database pfc-header-icon"></span>
			<div>
				<h1 class="pfc-page-title"><?php esc_html_e( 'PFC Object Cache', 'pfc-object-cache' ); ?></h1>
				<p class="pfc-page-subtitle">
					<?php
					printf(
						/* translators: %s: active driver name */
						esc_html__( 'Persistent object cache powered by PHPFastCache — Active driver: %s', 'pfc-object-cache' ),
						'<strong class="pfc-driver-badge pfc-driver-badge--' . esc_attr( strtolower( $driver ) ) . '">'
							. esc_html( $driver ) . '</strong>'
					);
					?>
				</p>
			</div>
		</div>
		<div class="pfc-page-header__right">
			<span class="pfc-version-badge">v<?php echo esc_html( PFC_PLUGIN_VERSION ); ?></span>
		</div>
	</div>

	<!-- ── Admin Notices ────────────────────────────────────────────────── -->
	<?php if ( $message ) : ?>
		<div class="notice notice-<?php echo in_array( $status, array( 'flushed', 'settings_saved' ), true ) ? 'success' : 'error'; ?> is-dismissible pfc-notice">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $stats['drop_in_active'] ) : ?>
		<div class="notice notice-warning pfc-notice">
			<p>
				<strong><?php esc_html_e( 'Drop-in not active:', 'pfc-object-cache' ); ?></strong>
				<?php esc_html_e( 'The object-cache.php drop-in is not loaded. Deactivate and reactivate this plugin to reinstall it.', 'pfc-object-cache' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- ── Stats Cards ──────────────────────────────────────────────────── -->
	<div class="pfc-stats-grid">

		<div class="pfc-stat-card">
			<div class="pfc-stat-card__value pfc-stat-card__value--cyan">
				<?php echo esc_html( $stats['ratio'] ); ?>%
			</div>
			<div class="pfc-stat-card__label"><?php esc_html_e( 'Hit Ratio', 'pfc-object-cache' ); ?></div>
		</div>

		<div class="pfc-stat-card">
			<div class="pfc-stat-card__value pfc-stat-card__value--green">
				<?php echo esc_html( number_format( $stats['hits'] ) ); ?>
			</div>
			<div class="pfc-stat-card__label"><?php esc_html_e( 'Cache Hits', 'pfc-object-cache' ); ?></div>
		</div>

		<div class="pfc-stat-card">
			<div class="pfc-stat-card__value pfc-stat-card__value--orange">
				<?php echo esc_html( number_format( $stats['misses'] ) ); ?>
			</div>
			<div class="pfc-stat-card__label"><?php esc_html_e( 'Cache Misses', 'pfc-object-cache' ); ?></div>
		</div>

		<div class="pfc-stat-card">
			<div class="pfc-stat-card__value pfc-stat-card__value--purple">
				<?php echo esc_html( number_format( $stats['runtime_items'] ) ); ?>
			</div>
			<div class="pfc-stat-card__label"><?php esc_html_e( 'Runtime Items', 'pfc-object-cache' ); ?></div>
		</div>

	</div><!-- /.pfc-stats-grid -->

	<!-- ── Two-column layout ────────────────────────────────────────────── -->
	<div class="pfc-two-col">

		<!-- Left: Cache Management -->
		<div class="pfc-panel">
			<h2 class="pfc-panel__title">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Cache Management', 'pfc-object-cache' ); ?>
			</h2>

			<!-- Flush All -->
			<form method="post" action="" class="pfc-form pfc-form--flush">
				<?php wp_nonce_field( AdminPage::NONCE_FLUSH ); ?>
				<input type="hidden" name="pfc_action" value="flush_all" />
				<div class="pfc-form-row">
					<p class="pfc-form-description">
						<?php esc_html_e( 'Remove all items from the persistent cache store. Use this after deployments or major content changes.', 'pfc-object-cache' ); ?>
					</p>
					<button
						type="submit"
						class="button pfc-btn pfc-btn--danger"
						onclick="return confirm( '<?php echo esc_js( __( 'Flush the entire object cache? This may temporarily increase database load.', 'pfc-object-cache' ) ); ?>' )"
					>
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Flush All Caches', 'pfc-object-cache' ); ?>
					</button>
				</div>
			</form>

			<hr class="pfc-divider" />

			<!-- Flush Group -->
			<form method="post" action="" class="pfc-form pfc-form--flush-group">
				<?php wp_nonce_field( AdminPage::NONCE_FLUSH ); ?>
				<input type="hidden" name="pfc_action" value="flush_group" />
				<div class="pfc-form-row">
					<label for="pfc-group-input" class="pfc-label">
						<?php esc_html_e( 'Cache Group', 'pfc-object-cache' ); ?>
					</label>
					<input
						type="text"
						id="pfc-group-input"
						name="pfc_group"
						class="regular-text pfc-input"
						placeholder="e.g. posts, options, transient"
						pattern="[a-zA-Z0-9_\-]+"
						title="<?php echo esc_attr__( 'Alphanumeric characters, hyphens and underscores only.', 'pfc-object-cache' ); ?>"
					/>
					<button type="submit" class="button pfc-btn pfc-btn--secondary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Flush Group', 'pfc-object-cache' ); ?>
					</button>
				</div>
				<p class="pfc-form-description">
					<?php esc_html_e( 'Common group names: posts, post_meta, options, terms, users, default, transient, site-transient', 'pfc-object-cache' ); ?>
				</p>
			</form>

			<hr class="pfc-divider" />

			<!-- Purge Nginx Cache -->
			<form method="post" action="" class="pfc-form pfc-form--flush">
				<?php wp_nonce_field( AdminPage::NONCE_FLUSH ); ?>
				<input type="hidden" name="pfc_action" value="purge_nginx" />
				<div class="pfc-form-row">
					<p class="pfc-form-description">
						<?php esc_html_e( 'Purge the nginx FastCGI / proxy cache directory. Requires a valid cache path in driver settings.', 'pfc-object-cache' ); ?>
					</p>
					<button
						type="submit"
						class="button pfc-btn pfc-btn--secondary"
						onclick="return confirm( '<?php echo esc_js( __( 'Purge the nginx cache directory?', 'pfc-object-cache' ) ); ?>' )"
					>
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Purge Nginx Cache', 'pfc-object-cache' ); ?>
					</button>
				</div>
			</form>
		</div><!-- /.pfc-panel (left) -->

		<!-- Right: Driver Settings -->
		<div class="pfc-panel">
			<h2 class="pfc-panel__title">
				<span class="dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Driver Configuration', 'pfc-object-cache' ); ?>
			</h2>

			<form method="post" action="" class="pfc-form pfc-form--settings">
				<?php wp_nonce_field( AdminPage::NONCE_SETTINGS ); ?>
				<input type="hidden" name="pfc_action" value="save_settings" />

				<!-- Driver selector -->
				<div class="pfc-form-group">
					<label for="pfc-driver-select" class="pfc-label">
						<?php esc_html_e( 'Cache Driver', 'pfc-object-cache' ); ?>
					</label>
					<select id="pfc-driver-select" name="pfc_settings[driver]" class="pfc-select">
						<?php
						$drivers = array( 'Files', 'Redis', 'Memcached', 'Apcu', 'Sqlite3' );
						foreach ( $drivers as $d ) :
							?>
							<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $settings['driver'] ?? 'Files', $d ); ?>>
								<?php echo esc_html( $d ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<!-- Redis fields -->
				<div class="pfc-form-group pfc-driver-fields pfc-driver-fields--redis">
					<h3 class="pfc-fields-heading"><?php esc_html_e( 'Redis Settings', 'pfc-object-cache' ); ?></h3>
					<label class="pfc-label"><?php esc_html_e( 'Host', 'pfc-object-cache' ); ?></label>
					<input type="text" name="pfc_settings[redis_host]" class="regular-text pfc-input"
						value="<?php echo esc_attr( $settings['redis_host'] ?? '127.0.0.1' ); ?>" />
					<label class="pfc-label"><?php esc_html_e( 'Port', 'pfc-object-cache' ); ?></label>
					<input type="number" name="pfc_settings[redis_port]" class="small-text pfc-input"
						value="<?php echo esc_attr( $settings['redis_port'] ?? '6379' ); ?>" min="1" max="65535" />
					<label class="pfc-label"><?php esc_html_e( 'Password', 'pfc-object-cache' ); ?></label>
					<input type="password" name="pfc_settings[redis_password]" class="regular-text pfc-input"
						value="<?php echo esc_attr( $settings['redis_password'] ?? '' ); ?>"
						autocomplete="new-password" />
					<label class="pfc-label"><?php esc_html_e( 'Database Index', 'pfc-object-cache' ); ?></label>
					<input type="number" name="pfc_settings[redis_database]" class="small-text pfc-input"
						value="<?php echo esc_attr( $settings['redis_database'] ?? '0' ); ?>" min="0" max="15" />
					<label class="pfc-label"><?php esc_html_e( 'Connection Timeout (seconds)', 'pfc-object-cache' ); ?></label>
					<input type="number" name="pfc_settings[redis_timeout]" class="small-text pfc-input"
						value="<?php echo esc_attr( $settings['redis_timeout'] ?? '5' ); ?>" min="1" max="30" />
				</div>

				<!-- Memcached fields -->
				<div class="pfc-form-group pfc-driver-fields pfc-driver-fields--memcached">
					<h3 class="pfc-fields-heading"><?php esc_html_e( 'Memcached Settings', 'pfc-object-cache' ); ?></h3>
					<label class="pfc-label"><?php esc_html_e( 'Host', 'pfc-object-cache' ); ?></label>
					<input type="text" name="pfc_settings[memcached_host]" class="regular-text pfc-input"
						value="<?php echo esc_attr( $settings['memcached_host'] ?? '127.0.0.1' ); ?>" />
					<label class="pfc-label"><?php esc_html_e( 'Port', 'pfc-object-cache' ); ?></label>
					<input type="number" name="pfc_settings[memcached_port]" class="small-text pfc-input"
						value="<?php echo esc_attr( $settings['memcached_port'] ?? '11211' ); ?>" min="1" max="65535" />
				</div>

				<!-- Nginx cache purge settings -->
				<div class="pfc-form-group" style="margin-top:1.5em;">
					<h3 class="pfc-fields-heading"><?php esc_html_e( 'Nginx Cache Purge', 'pfc-object-cache' ); ?></h3>
					<label class="pfc-label" style="display:flex;align-items:center;gap:6px;">
						<input type="hidden" name="pfc_settings[nginx_purge_enabled]" value="" />
						<input
							type="checkbox"
							name="pfc_settings[nginx_purge_enabled]"
							value="1"
							<?php checked( ! empty( $settings['nginx_purge_enabled'] ) ); ?>
						/>
						<?php esc_html_e( 'Enable automatic nginx cache purge on full flush', 'pfc-object-cache' ); ?>
					</label>
					<label class="pfc-label" style="margin-top:.5em;"><?php esc_html_e( 'Nginx Cache Path', 'pfc-object-cache' ); ?></label>
					<input type="text" name="pfc_settings[nginx_cache_path]" class="regular-text pfc-input"
						value="<?php echo esc_attr( $settings['nginx_cache_path'] ?? '' ); ?>"
						placeholder="/var/run/nginx-cache" />
					<p class="pfc-form-description">
						<?php esc_html_e( 'Absolute path to the nginx fastcgi_cache_path or proxy_cache_path directory.', 'pfc-object-cache' ); ?>
					</p>
				</div>

				<button type="submit" class="button button-primary pfc-btn pfc-btn--primary">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Settings', 'pfc-object-cache' ); ?>
				</button>

				<p class="pfc-notice-text">
					<span class="dashicons dashicons-info-outline"></span>
					<?php esc_html_e( 'After saving, deactivate and reactivate the plugin so the drop-in picks up the new driver configuration.', 'pfc-object-cache' ); ?>
				</p>
			</form>

			<script>
			( function() {
				var sel    = document.getElementById( 'pfc-driver-select' );
				var panels = document.querySelectorAll( '.pfc-driver-fields' );

				function toggle() {
					var val = sel ? sel.value.toLowerCase() : '';
					panels.forEach( function( panel ) {
						var cls      = panel.className;
						var forDriver = cls.replace( /.*pfc-driver-fields--(\S+).*/, '$1' );
						panel.style.display = ( val === forDriver ) ? 'block' : 'none';
					} );
				}

				if ( sel ) {
					sel.addEventListener( 'change', toggle );
					toggle();
				}
			}() );
			</script>

		</div><!-- /.pfc-panel (right) -->

	</div><!-- /.pfc-two-col -->

</div><!-- /.wrap.pfc-admin-wrap -->
