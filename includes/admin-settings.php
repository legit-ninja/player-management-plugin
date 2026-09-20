<?php
/**
 * Players → Settings: General (Update Stream) + License tabs.
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Admin settings for Player Management (Update Stream + License).
 */
class InterSoccer_Player_Management_Settings {
	const PAGE_SLUG       = 'intersoccer-players-settings';
	const OPTION_CHANNEL  = 'intersoccer_uu_update_channel';
	const NONCE_GENERAL   = 'intersoccer_pm_settings_general';
	const NONCE_FIELD     = 'intersoccer_pm_settings_nonce';
	const PLUGIN_SLUG     = 'player-management';
	const OPTION_BETA_MIGRATED = 'intersoccer_pm_beta_migrated';

	/** @var array<string, string> Host channel => short UI example */
	const CHANNELS = array(
		'release'     => 'release',
		'dev'         => 'dev',
	);

	/**
	 * Bootstrap hooks (call after class is loaded).
	 *
	 * @return void
	 */
	public static function init() {
		add_action('admin_menu', array(__CLASS__, 'register_legacy_updates_alias'), 99);
		add_action('admin_init', array(__CLASS__, 'redirect_legacy_updates_page'), 0);
		add_action('admin_init', array(__CLASS__, 'maybe_migrate_beta_channel'));
		add_action('admin_init', array(__CLASS__, 'handle_general_save'));
		add_action('admin_init', array(__CLASS__, 'handle_builtin_license_save'));
	}

	/**
	 * One-time migration: if site-wide channel was prerelease, enable per-plugin beta for this plugin.
	 *
	 * @return void
	 */
	public static function maybe_migrate_beta_channel() {
		if (get_option(self::OPTION_BETA_MIGRATED)) {
			return;
		}

		$stored = get_option(self::OPTION_CHANNEL, 'release');
		if ($stored === 'prerelease') {
			if (class_exists('InterSoccer_Updates_Http') && method_exists('InterSoccer_Updates_Http', 'set_beta_enabled_for_slug')) {
				InterSoccer_Updates_Http::set_beta_enabled_for_slug(self::PLUGIN_SLUG, true);
			}
			update_option(self::OPTION_CHANNEL, 'release', false);
		}

		update_option(self::OPTION_BETA_MIGRATED, '1', false);
	}

	/**
	 * Keep old slug registered (hidden) so WP does not 403; callback redirects.
	 *
	 * @return void
	 */
	public static function register_legacy_updates_alias() {
		add_submenu_page(
			null,
			__('Updates', 'player-management'),
			__('Updates', 'player-management'),
			'manage_options',
			'intersoccer-players-updates',
			array(__CLASS__, 'redirect_legacy_updates_page')
		);
	}

	/**
	 * Old Underdog Updates slug → Players → Settings (bookmarks / stale links).
	 *
	 * @return void
	 */
	public static function redirect_legacy_updates_page() {
		if (!is_admin()) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
		if ($page !== 'intersoccer-players-updates') {
			return;
		}
		wp_safe_redirect(self::page_url('general'));
		exit;
	}

	/**
	 * Settings page URL.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function page_url($tab = 'general') {
		$tab = self::sanitize_tab($tab);
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url('admin.php')
		);
	}

	/**
	 * @param string $tab Raw tab.
	 * @return string
	 */
	public static function sanitize_tab($tab) {
		$tab = is_string($tab) ? strtolower($tab) : 'general';
		return in_array($tab, array('general', 'license'), true) ? $tab : 'general';
	}

	/**
	 * Sanitize Update Stream channel to a host channel value.
	 *
	 * @param string $channel Raw channel.
	 * @return string release|dev
	 */
	public static function sanitize_channel($channel) {
		$channel = is_string($channel) ? strtolower(trim($channel)) : '';
		if (isset(self::CHANNELS[ $channel ])) {
			return $channel;
		}
		return 'release';
	}

	/**
	 * Whether beta updates are enabled for this plugin.
	 *
	 * @return bool
	 */
	public static function is_beta_enabled() {
		if (class_exists('InterSoccer_Updates_Http') && method_exists('InterSoccer_Updates_Http', 'is_beta_enabled_for_slug')) {
			return InterSoccer_Updates_Http::is_beta_enabled_for_slug(self::PLUGIN_SLUG);
		}
		return false;
	}

	/**
	 * Set beta updates enabled/disabled for this plugin.
	 *
	 * @param bool $enabled Whether beta is enabled.
	 * @return bool True if successfully set, false if InterSoccer Updates is not available.
	 */
	public static function set_beta_enabled($enabled) {
		if (class_exists('InterSoccer_Updates_Http') && method_exists('InterSoccer_Updates_Http', 'set_beta_enabled_for_slug')) {
			InterSoccer_Updates_Http::set_beta_enabled_for_slug(self::PLUGIN_SLUG, (bool) $enabled);
			return true;
		}
		return false;
	}

	/**
	 * Current Update Stream channel.
	 *
	 * @return string
	 */
	public static function get_channel() {
		$stored = get_option(self::OPTION_CHANNEL, 'release');
		return self::sanitize_channel(is_string($stored) ? $stored : 'release');
	}

	/**
	 * Save General tab (Update Stream).
	 *
	 * @return void
	 */
	public static function handle_general_save() {
		if (!is_admin() || !current_user_can('manage_options')) {
			return;
		}
		if (empty($_POST[ self::NONCE_FIELD ]) || empty($_POST['intersoccer_pm_save_general'])) {
			return;
		}
		if (!wp_verify_nonce(
			sanitize_text_field(wp_unslash($_POST[ self::NONCE_FIELD ])),
			self::NONCE_GENERAL
		)) {
			return;
		}

		$channel = isset($_POST['intersoccer_uu_update_channel'])
			? self::sanitize_channel(sanitize_text_field(wp_unslash($_POST['intersoccer_uu_update_channel'])))
			: 'release';
		update_option(self::OPTION_CHANNEL, $channel, false);

		$beta_enabled = !empty($_POST['intersoccer_pm_beta_enabled']);
		self::set_beta_enabled($beta_enabled);

		wp_safe_redirect(add_query_arg('settings-updated', '1', self::page_url('general')));
		exit;
	}

	/**
	 * Render Settings screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'player-management'));
		}

		$tab = isset($_GET['tab']) ? self::sanitize_tab(wp_unslash($_GET['tab'])) : 'general';

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Player Management Settings', 'player-management'); ?></h1>

			<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr__('Settings tabs', 'player-management'); ?>">
				<a href="<?php echo esc_url(self::page_url('general')); ?>" class="nav-tab <?php echo $tab === 'general' ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html__('General', 'player-management'); ?>
				</a>
				<a href="<?php echo esc_url(self::page_url('license')); ?>" class="nav-tab <?php echo $tab === 'license' ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html__('License', 'player-management'); ?>
				</a>
			</nav>

			<?php
			if ($tab === 'license') {
				self::render_license_tab();
			} else {
				self::render_general_tab();
			}
			?>
		</div>
		<?php
	}

	/**
	 * General tab: Update Stream + per-plugin beta toggle.
	 *
	 * @return void
	 */
	public static function render_general_tab() {
		$channel = self::get_channel();
		$updated = isset($_GET['settings-updated']);
		$uu_active = class_exists('InterSoccer_Updates_Http');
		$beta_enabled = self::is_beta_enabled();

		?>
		<?php if ($updated) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'player-management'); ?></p></div>
		<?php endif; ?>

		<?php if (!$uu_active) : ?>
			<div class="notice notice-warning">
				<p><?php echo esc_html__('The InterSoccer Updates plugin is not active. Update Stream is saved, but automatic updates from plugins.underdogunlimited.com require InterSoccer Updates.', 'player-management'); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field(self::NONCE_GENERAL, self::NONCE_FIELD); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__('Enable beta updates', 'player-management'); ?></th>
					<td>
						<label>
							<input type="checkbox" name="intersoccer_pm_beta_enabled" value="1" <?php checked($beta_enabled); ?> <?php disabled(!$uu_active); ?> />
							<?php echo esc_html__('Receive beta and release-candidate versions of this plugin', 'player-management'); ?>
						</label>
						<p class="description">
							<?php echo esc_html__('When enabled, this plugin prefers the latest beta or release candidate from Underdog. When disabled, only stable releases are offered.', 'player-management'); ?>
						</p>
						<?php if (!$uu_active) : ?>
							<p class="description" style="color:#d63638;">
								<?php echo esc_html__('Beta updates require the InterSoccer Updates plugin to be active.', 'player-management'); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__('Update Stream', 'player-management'); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php echo esc_html__('Update Stream', 'player-management'); ?></span></legend>

							<label style="display:block;margin-bottom:12px;">
								<input type="radio" name="intersoccer_uu_update_channel" value="release" <?php checked($channel, 'release'); ?> />
								<strong><?php echo esc_html__('Release', 'player-management'); ?></strong>
								<span class="description"> — <?php echo esc_html__('Stable builds (e.g. v2.7.1).', 'player-management'); ?></span>
							</label>

							<label style="display:block;margin-bottom:12px;">
								<input type="radio" name="intersoccer_uu_update_channel" value="dev" <?php checked($channel, 'dev'); ?> />
								<strong><?php echo esc_html__('Dev', 'player-management'); ?></strong>
								<span class="description"> — <?php echo esc_html__('Bleeding-edge development builds for staging sites only.', 'player-management'); ?></span>
							</label>
						</fieldset>
						<p class="description">
							<?php echo esc_html__('Applies site-wide to all InterSoccer plugins updated from plugins.underdogunlimited.com.', 'player-management'); ?>
						</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="intersoccer_pm_save_general" value="1" class="button button-primary">
					<?php echo esc_html__('Save changes', 'player-management'); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * License tab: site token (shared with intersoccer-updates).
	 *
	 * @return void
	 */
	public static function render_license_tab() {
		if (class_exists('InterSoccer_Updates_License_Settings')) {
			InterSoccer_Updates_License_Settings::render_embedded_form(self::page_url('license'));
			return;
		}

		self::render_builtin_license_form();
	}

	/**
	 * Whether INTERSOCCER_UU_SITE_TOKEN is set in wp-config.
	 *
	 * @return bool
	 */
	public static function site_token_constant_overrides() {
		return defined('INTERSOCCER_UU_SITE_TOKEN') && (string) INTERSOCCER_UU_SITE_TOKEN !== '';
	}

	/**
	 * Whether a DB site token is stored.
	 *
	 * @return bool
	 */
	public static function has_stored_site_token() {
		$token = get_option('intersoccer_uu_site_token', '');
		return is_string($token) && $token !== '';
	}

	/**
	 * Save/revoke site token when InterSoccer Updates is not loaded.
	 *
	 * @return void
	 */
	public static function handle_builtin_license_save() {
		if (!is_admin() || !current_user_can('manage_options')) {
			return;
		}
		if (class_exists('InterSoccer_Updates_License_Settings')) {
			return;
		}
		if (empty($_POST['intersoccer_pm_license_nonce'])) {
			return;
		}
		if (!wp_verify_nonce(
			sanitize_text_field(wp_unslash($_POST['intersoccer_pm_license_nonce'])),
			'intersoccer_pm_license'
		)) {
			return;
		}
		if (self::site_token_constant_overrides()) {
			return;
		}

		$redirect = self::page_url('license');

		if (!empty($_POST['intersoccer_pm_license_revoke'])) {
			delete_option('intersoccer_uu_site_token');
			wp_safe_redirect(add_query_arg('uu_license', 'revoked', $redirect));
			exit;
		}

		if (!empty($_POST['intersoccer_pm_license_save'])) {
			$raw = isset($_POST['intersoccer_uu_site_token'])
				? sanitize_text_field(wp_unslash($_POST['intersoccer_uu_site_token']))
				: '';
			if ($raw !== '') {
				update_option('intersoccer_uu_site_token', $raw, false);
			}
			wp_safe_redirect(add_query_arg('uu_license', 'saved', $redirect));
			exit;
		}
	}

	/**
	 * Built-in License form (same option key as intersoccer-updates).
	 *
	 * @return void
	 */
	public static function render_builtin_license_form() {
		$constant_override = self::site_token_constant_overrides();
		$has_token         = self::has_stored_site_token();
		$status            = isset($_GET['uu_license'])
			? sanitize_text_field(wp_unslash($_GET['uu_license']))
			: '';

		if ($status === 'saved') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Site token saved.', 'player-management') . '</p></div>';
		} elseif ($status === 'revoked') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Site token revoked.', 'player-management') . '</p></div>';
		}

		if (!class_exists('InterSoccer_Updates_Http')) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__('InterSoccer Updates is not active. You can still save a site token here; activate InterSoccer Updates to check for and install plugin updates.', 'player-management');
			echo '</p></div>';
		}

		if ($constant_override) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__('INTERSOCCER_UU_SITE_TOKEN is defined in wp-config.php and overrides any token stored here. Save and Revoke are disabled until the constant is removed.', 'player-management');
			echo '</p></div>';
		}

		echo '<p>' . esc_html__('Enter a site token from your Underdog Unlimited account (Account → Plugins). Tokens start with udpl_ and are used only for plugin update checks and downloads — never for CI publish.', 'player-management') . '</p>';
		?>
		<form method="post" action="">
			<?php wp_nonce_field('intersoccer_pm_license', 'intersoccer_pm_license_nonce'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__('Update host', 'player-management'); ?></th>
					<td><code>https://plugins.underdogunlimited.com</code></td>
				</tr>
				<tr>
					<th scope="row">
						<label for="intersoccer_uu_site_token"><?php echo esc_html__('Site token', 'player-management'); ?></label>
					</th>
					<td>
						<input
							type="password"
							class="regular-text"
							id="intersoccer_uu_site_token"
							name="intersoccer_uu_site_token"
							value=""
							autocomplete="off"
							<?php disabled($constant_override); ?>
							placeholder="<?php echo $has_token
								? esc_attr__('•••••••• (saved — enter a new token to replace)', 'player-management')
								: esc_attr__('udpl_…', 'player-management'); ?>"
						/>
						<?php if ($has_token && !$constant_override) : ?>
							<p class="description"><?php echo esc_html__('A site token is currently stored. Enter a new token to replace it, or use Revoke to remove it.', 'player-management'); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="intersoccer_pm_license_save" value="1" class="button button-primary" <?php disabled($constant_override); ?>>
					<?php echo esc_html__('Save token', 'player-management'); ?>
				</button>
				<?php if ($has_token && !$constant_override) : ?>
					<button type="submit" name="intersoccer_pm_license_revoke" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Revoke the stored site token?', 'player-management')); ?>');">
						<?php echo esc_html__('Revoke token', 'player-management'); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>
		<p>
			<a href="<?php echo esc_url(admin_url('update-core.php')); ?>"><?php echo esc_html__('Open Dashboard → Updates', 'player-management'); ?></a>
		</p>
		<?php
	}
}
