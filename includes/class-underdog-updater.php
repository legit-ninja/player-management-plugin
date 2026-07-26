<?php
/**
 * Underdog Unlimited plugin update client.
 *
 * Checks GET {base}/api/plugins/v1/player-management with a site token and
 * installs packages from the authenticated download URL.
 *
 * @package PlayerManagement
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('InterSoccer_Player_Management_Underdog_Updater')) {

/**
 * WordPress update integration for the Underdog plugin host.
 */
class InterSoccer_Player_Management_Underdog_Updater {

	const OPTION_TOKEN = 'player_management_uu_site_token';
	const SLUG         = 'player-management';
	const DEFAULT_BASE = 'https://plugins.underdogunlimited.com';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'check_for_update'));
		add_filter('plugins_api', array(__CLASS__, 'plugins_api'), 10, 3);
		add_filter('http_request_args', array(__CLASS__, 'http_request_args'), 10, 2);
		add_action('admin_menu', array(__CLASS__, 'register_settings_page'), 20);
		add_action('admin_init', array(__CLASS__, 'handle_settings_actions'));
	}

	/**
	 * Public update host base URL (no trailing slash).
	 *
	 * @return string
	 */
	public static function get_update_base() {
		$base = self::DEFAULT_BASE;
		if (defined('PLAYER_MANAGEMENT_UPDATE_BASE') && PLAYER_MANAGEMENT_UPDATE_BASE) {
			$base = PLAYER_MANAGEMENT_UPDATE_BASE;
		}
		/**
		 * Filter the Underdog update host base URL.
		 *
		 * @param string $base Base URL without trailing slash preference.
		 */
		$base = apply_filters('player_management_update_base_url', $base);
		return untrailingslashit((string) $base);
	}

	/**
	 * Metadata endpoint for this plugin slug.
	 *
	 * @return string
	 */
	public static function get_metadata_url() {
		return self::get_update_base() . '/api/plugins/v1/' . rawurlencode(self::SLUG);
	}

	/**
	 * Relative path of the main plugin file from wp-content/plugins.
	 *
	 * @return string
	 */
	public static function get_plugin_basename() {
		return self::SLUG . '/player-management.php';
	}

	/**
	 * Stored site token (empty if unset).
	 *
	 * @return string
	 */
	public static function get_site_token() {
		$token = get_option(self::OPTION_TOKEN, '');
		return is_string($token) ? trim($token) : '';
	}

	/**
	 * Whether $url should receive the site Bearer token.
	 *
	 * @param string $url Request URL.
	 * @return bool
	 */
	public static function url_requires_auth($url) {
		if (!is_string($url) || $url === '') {
			return false;
		}
		$base = self::get_update_base();
		if ($base === '') {
			return false;
		}
		$prefix = $base . '/api/plugins/v1/';
		return strpos($url, $prefix) === 0;
	}

	/**
	 * Inject Authorization on Underdog metadata and download requests.
	 *
	 * @param array  $args HTTP request args.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public static function http_request_args($args, $url) {
		if (!self::url_requires_auth($url)) {
			return $args;
		}
		$token = self::get_site_token();
		if ($token === '') {
			return $args;
		}
		if (!isset($args['headers']) || !is_array($args['headers'])) {
			$args['headers'] = array();
		}
		$args['headers']['Authorization'] = 'Bearer ' . $token;
		return $args;
	}

	/**
	 * Fetch remote metadata JSON or null on failure.
	 *
	 * @return object|null
	 */
	public static function fetch_metadata() {
		$token = self::get_site_token();
		if ($token === '') {
			return null;
		}

		$response = wp_remote_get(
			self::get_metadata_url(),
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if (is_wp_error($response)) {
			return null;
		}

		$code = wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) {
			return null;
		}

		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body);
		if (!is_object($data) || empty($data->version) || empty($data->download_url)) {
			return null;
		}

		return $data;
	}

	/**
	 * Inject update into the site transient when a newer version is available.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public static function check_for_update($transient) {
		if (!is_object($transient)) {
			return $transient;
		}

		$remote = self::fetch_metadata();
		if (!$remote) {
			return $transient;
		}

		$installed = defined('PLAYER_MANAGEMENT_VERSION') ? PLAYER_MANAGEMENT_VERSION : '0';
		if (version_compare($remote->version, $installed, '<=')) {
			return $transient;
		}

		$plugin = self::get_plugin_basename();
		$update = (object) array(
			'slug'        => self::SLUG,
			'plugin'      => $plugin,
			'new_version' => $remote->version,
			'url'         => isset($remote->homepage) ? $remote->homepage : self::get_update_base(),
			'package'     => $remote->download_url,
			'tested'      => isset($remote->tested) ? $remote->tested : '',
			'requires'    => isset($remote->requires) ? $remote->requires : '',
			'requires_php'=> isset($remote->requires_php) ? $remote->requires_php : '',
		);

		if (!isset($transient->response) || !is_array($transient->response)) {
			$transient->response = array();
		}
		$transient->response[ $plugin ] = $update;

		return $transient;
	}

	/**
	 * Provide plugin information for the update details modal.
	 *
	 * @param false|object|array $result Result object or false.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public static function plugins_api($result, $action, $args) {
		if ($action !== 'plugin_information') {
			return $result;
		}
		if (!is_object($args) || empty($args->slug) || $args->slug !== self::SLUG) {
			return $result;
		}

		$remote = self::fetch_metadata();
		if (!$remote) {
			return $result;
		}

		$info = (object) array(
			'name'          => isset($remote->name) ? $remote->name : 'Player Management',
			'slug'          => self::SLUG,
			'version'       => $remote->version,
			'author'        => '<a href="https://underdogunlimited.com">Underdog Unlimited</a>',
			'homepage'      => isset($remote->homepage) ? $remote->homepage : self::get_update_base(),
			'requires'      => isset($remote->requires) ? $remote->requires : '',
			'tested'        => isset($remote->tested) ? $remote->tested : '',
			'requires_php'  => isset($remote->requires_php) ? $remote->requires_php : '',
			'download_link' => $remote->download_url,
			'sections'      => array(
				'description' => isset($remote->sections->description) ? $remote->sections->description : '',
				'changelog'   => isset($remote->sections->changelog) ? $remote->sections->changelog : '',
			),
		);

		return $info;
	}

	/**
	 * Add Updates submenu under Players.
	 *
	 * @return void
	 */
	public static function register_settings_page() {
		add_submenu_page(
			'intersoccer-players',
			__('Underdog Updates', 'player-management'),
			__('Updates', 'player-management'),
			'manage_options',
			'intersoccer-players-updates',
			array(__CLASS__, 'render_settings_page')
		);
	}

	/**
	 * Save or revoke the site token.
	 *
	 * @return void
	 */
	public static function handle_settings_actions() {
		if (!is_admin() || !current_user_can('manage_options')) {
			return;
		}
		if (empty($_POST['player_management_uu_updates_nonce'])) {
			return;
		}
		if (!wp_verify_nonce(
			sanitize_text_field(wp_unslash($_POST['player_management_uu_updates_nonce'])),
			'player_management_uu_updates'
		)) {
			return;
		}

		$redirect = admin_url('admin.php?page=intersoccer-players-updates');

		if (!empty($_POST['player_management_uu_revoke'])) {
			delete_option(self::OPTION_TOKEN);
			wp_safe_redirect(add_query_arg('uu_updates', 'revoked', $redirect));
			exit;
		}

		if (!empty($_POST['player_management_uu_save'])) {
			$raw = isset($_POST['player_management_uu_site_token'])
				? sanitize_text_field(wp_unslash($_POST['player_management_uu_site_token']))
				: '';
			// Password fields submit empty when unchanged — only replace when a new token is entered.
			if ($raw !== '') {
				update_option(self::OPTION_TOKEN, $raw, false);
			}
			wp_safe_redirect(add_query_arg('uu_updates', 'saved', $redirect));
			exit;
		}
	}

	/**
	 * Settings UI: site token + update base.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'player-management'));
		}

		$token   = self::get_site_token();
		$has_token = $token !== '';
		$status  = isset($_GET['uu_updates']) ? sanitize_text_field(wp_unslash($_GET['uu_updates'])) : '';

		?>
		<div class="wrap">
			<h1><?php echo esc_html__('Underdog Updates', 'player-management'); ?></h1>

			<?php if ($status === 'saved') : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Site token saved.', 'player-management'); ?></p></div>
			<?php elseif ($status === 'revoked') : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Site token revoked.', 'player-management'); ?></p></div>
			<?php endif; ?>

			<p>
				<?php echo esc_html__('Enter a site token from your Underdog Unlimited account (Account → Plugins). Tokens start with udpl_ and are used only for update checks and downloads — never for CI publish.', 'player-management'); ?>
			</p>

			<form method="post" action="">
				<?php wp_nonce_field('player_management_uu_updates', 'player_management_uu_updates_nonce'); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__('Update host', 'player-management'); ?></th>
						<td>
							<code><?php echo esc_html(self::get_update_base()); ?></code>
							<p class="description">
								<?php echo esc_html__('Override with the PLAYER_MANAGEMENT_UPDATE_BASE constant or the player_management_update_base_url filter.', 'player-management'); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__('Metadata URL', 'player-management'); ?></th>
						<td><code><?php echo esc_html(self::get_metadata_url()); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><label for="player_management_uu_site_token"><?php echo esc_html__('Site token', 'player-management'); ?></label></th>
						<td>
							<input
								type="password"
								class="regular-text"
								id="player_management_uu_site_token"
								name="player_management_uu_site_token"
								value=""
								autocomplete="off"
								placeholder="<?php echo $has_token ? esc_attr__('•••••••• (saved — enter a new token to replace)', 'player-management') : esc_attr__('udpl_…', 'player-management'); ?>"
							/>
							<?php if ($has_token) : ?>
								<p class="description"><?php echo esc_html__('A site token is currently stored. Enter a new token to replace it, or use Revoke to remove it.', 'player-management'); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" name="player_management_uu_save" value="1" class="button button-primary">
						<?php echo esc_html__('Save token', 'player-management'); ?>
					</button>
					<?php if ($has_token) : ?>
						<button type="submit" name="player_management_uu_revoke" value="1" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Revoke the stored site token?', 'player-management')); ?>');">
							<?php echo esc_html__('Revoke token', 'player-management'); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>

			<p>
				<a href="<?php echo esc_url(admin_url('update-core.php')); ?>"><?php echo esc_html__('Open Dashboard → Updates', 'player-management'); ?></a>
			</p>
		</div>
		<?php
	}
}

} // class_exists
