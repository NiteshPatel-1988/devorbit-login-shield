<?php
/**
 * Core plugin class.
 *
 * @package DevOrbit_Login_Shield
 */

// Block direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles hiding the default login URL and admin area behind a custom
 * slug, and provides the settings screen used to configure it.
 */
final class DevOrbit_Login_Shield {

	const OPTION_SLUG          = 'devorbit_login_shield_slug';
	const OPTION_REDIRECT_MODE = 'devorbit_login_shield_redirect_mode';
	const OPTION_HIDE_LINKS    = 'devorbit_login_shield_hide_links';
	const SETTINGS_GROUP       = 'devorbit_login_shield_settings_group';
	const DEFAULT_SLUG         = 'login';

	/**
	 * Slugs that must never be usable as the custom login slug, either
	 * because WordPress core reserves them or because using them would
	 * break the block/redirect logic.
	 *
	 * @var string[]
	 */
	private $reserved_slugs = array(
		'wp-admin',
		'wp-login',
		'wp-login.php',
		'wp-content',
		'wp-includes',
		'wp-cron.php',
		'xmlrpc.php',
		'admin-ajax.php',
		'admin-post.php',
		'admin',
		'dashboard',
		'.htaccess',
		'wp-config.php',
	);

	/**
	 * Whether wp-login.php is currently being served at the custom slug.
	 *
	 * Set immediately before wp-login.php is required, so that login URLs
	 * generated while that screen renders still point at the custom slug even
	 * when public login links are being hidden.
	 *
	 * @var bool
	 */
	private $serving_login_page = false;

	/**
	 * The single running instance of the plugin.
	 *
	 * @var DevOrbit_Login_Shield|null
	 */
	private static $instance = null;

	/**
	 * Returns the single running instance of the plugin.
	 *
	 * @return DevOrbit_Login_Shield
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor. Use instance() to get the singleton.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'intercept_request' ), 0 );

		add_filter( 'do_parse_request', array( $this, 'serve_custom_login_url' ), 0 );

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . DEVORBIT_LOGIN_SHIELD_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

		add_filter( 'site_url', array( $this, 'filter_login_urls' ), 10, 2 );
		add_filter( 'network_site_url', array( $this, 'filter_login_urls' ), 10, 2 );

		// Themes, widgets and the comment form render "Log in" and "Register"
		// links for logged-out visitors, which would otherwise publish the
		// custom slug in the markup of every page that shows them.
		add_filter( 'loginout', array( $this, 'filter_public_login_link' ) );
		add_filter( 'register', array( $this, 'filter_public_login_link' ) );
		add_filter( 'comment_form_defaults', array( $this, 'filter_comment_form_login_link' ) );
	}

	/*
	 * -----------------------------------------------------------------
	 * Request interception
	 * ---------------------------------------------------------------
	 */

	/**
	 * Blocks the two default ways into the login screen: a direct request to
	 * wp-login.php, and an unauthenticated request to anything under
	 * /wp-admin/.
	 */
	public function intercept_request() {
		if ( $this->is_exempt_request() ) {
			return;
		}

		if ( $this->is_default_login_request() ) {
			if ( ! $this->is_post_password_submission() ) {
				$this->block_request();
			}

			return;
		}

		if ( is_admin() && ! $this->is_authenticated() ) {
			$this->block_request();
		}
	}

	/**
	 * Whether this is a submission of the password-protected post form.
	 *
	 * @return bool
	 */
	private function is_post_password_submission() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		if ( 'POST' !== $method ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only inspection of the requested action; wp-login.php does its own handling and this grants no privileges.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return 'postpass' === $action;
	}

	/**
	 * Requests that must never be blocked, because doing so would break core
	 * or third-party functionality rather than hide the login screen.
	 *
	 * @return bool
	 */
	private function is_exempt_request() {
		if ( wp_doing_ajax() || wp_doing_cron() || wp_installing() ) {
			return true;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		// Any other command-line bootstrap of wp-load.php, such as a
		// deployment or maintenance script, which must never be redirected.
		if ( 'cli' === PHP_SAPI ) {
			return true;
		}

		// admin-ajax.php and admin-post.php sit under /wp-admin/ but have to
		// stay reachable while logged out, for themes and plugins that post
		// to them from the front end.
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';

		return in_array( $script, array( 'admin-ajax.php', 'admin-post.php' ), true );
	}

	/**
	 * Whether the current request targets the real wp-login.php.
	 *
	 * $pagenow is derived from the requested script in wp-includes/vars.php,
	 * which loads before plugins do, and it covers query-string variants such
	 * as wp-login.php?action=register. The path comparison is a fallback for
	 * setups that route the request through index.php instead.
	 *
	 * @return bool
	 */
	private function is_default_login_request() {
		if ( isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === strtolower( (string) $GLOBALS['pagenow'] ) ) {
			return true;
		}

		$request_path = $this->get_request_path();

		if ( '' === $request_path ) {
			return false;
		}

		return $this->paths_match( $request_path, 'wp-login.php' ) || $this->paths_match( $request_path, 'wp-login' );
	}

	/**
	 * Checks for a valid logged-in cookie without resolving the current user.
	 *
	 * Using is_user_logged_in() would also work, but calling it this early
	 * forces WordPress to determine the current user before every plugin has
	 * had a chance to hook determine_current_user, which can break
	 * authentication plugins that load after this one.
	 *
	 * @return bool
	 */
	private function is_authenticated() {
		if ( ! function_exists( 'wp_validate_auth_cookie' ) ) {
			return true;
		}

		return (bool) wp_validate_auth_cookie( '', 'logged_in' );
	}

	/**
	 * Serves the login screen at the custom slug. Runs on do_parse_request,
	 * which is late enough that WordPress is fully loaded, but early enough
	 * that the main query has not been parsed yet.
	 *
	 * @param bool $do_parse Whether WP should continue parsing the request.
	 * @return bool
	 */
	public function serve_custom_login_url( $do_parse ) {
		if ( is_admin() ) {
			return $do_parse;
		}

		$request_path = $this->get_request_path();

		if ( '' === $request_path ) {
			return $do_parse;
		}

		if ( $this->paths_match( $request_path, $this->get_login_slug() ) ) {
			$this->serve_login_page();
		}

		return $do_parse;
	}

	/**
	 * Serves the real wp-login.php logic in place of the current request.
	 * WordPress core is already fully bootstrapped by this point (ABSPATH
	 * is defined), so requiring wp-login.php simply runs its normal form
	 * rendering / authentication logic against the current request.
	 */
	private function serve_login_page() {
		// wp-login.php is required from inside this method, so the variables it
		// and its helper functions share through the global scope must be bound.
		global $error, $interim_login, $action, $user_login;

		$this->serving_login_page = true;
		$GLOBALS['pagenow']       = 'wp-login.php';

		require ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Blocks a request by either redirecting to the home page or serving a
	 * genuine 404, depending on the configured mode. Either way this exits.
	 */
	private function block_request() {
		$mode = get_option( self::OPTION_REDIRECT_MODE, 'home' );

		if ( '404' === $mode ) {
			$this->serve_404();
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Renders the active theme's own 404 template, the same one a genuinely
	 * missing front-end URL would produce, instead of a generic wp_die()
	 * message. Falls back to the theme's index template, matching core's own
	 * template-loader.php fallback, and to wp_die() only if the theme has
	 * neither.
	 */
	private function serve_404() {
		global $wp_query;

		status_header( 404 );
		nocache_headers();

		$wp_query->set_404();

		$template = get_404_template();

		if ( ! $template ) {
			$template = get_index_template();
		}

		if ( ! $template ) {
			wp_die(
				esc_html__( 'Not found.', 'devorbit-login-shield' ),
				esc_html__( 'Not found', 'devorbit-login-shield' ),
				array( 'response' => 404 )
			);
		}

		load_template( $template, false );
		exit;
	}

	/**
	 * Returns the current request path relative to the site's home
	 * directory, with leading/trailing slashes trimmed. Handles installs
	 * that live in a subdirectory.
	 *
	 * @return string
	 */
	private function get_request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( $home_path && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		return trim( (string) $path, '/' );
	}

	/**
	 * Case-insensitive comparison of a request path against a target slug.
	 *
	 * @param string $request_path Path extracted from the current request.
	 * @param string $target       Slug to compare against.
	 * @return bool
	 */
	private function paths_match( $request_path, $target ) {
		return strtolower( $request_path ) === strtolower( trim( $target, '/' ) );
	}

	/*
	 * -----------------------------------------------------------------
	 * URL rewriting so login/logout/register/lostpassword links point
	 * to the custom slug instead of wp-login.php
	 * ---------------------------------------------------------------
	 */

	/**
	 * Rewrites any generated URL whose path begins with wp-login.php so it
	 * points at the custom slug instead. This single filter covers
	 * wp_login_url(), wp_logout_url(), wp_registration_url() and
	 * wp_lostpassword_url(), since all of them are built on top of
	 * site_url( 'wp-login.php', ... ) internally.
	 *
	 * @param string $url  The complete URL including scheme and path.
	 * @param string $path The requested path relative to the site URL.
	 * @return string
	 */
	public function filter_login_urls( $url, $path ) {
		if ( 0 !== strpos( (string) $path, 'wp-login.php' ) ) {
			return $url;
		}

		if ( ! $this->should_expose_login_url() ) {
			return $url;
		}

		return preg_replace( '/wp-login\.php/', $this->get_login_slug(), $url, 1 );
	}

	/**
	 * Whether the custom slug is allowed to appear in a URL generated during
	 * this request.
	 *
	 * It has to appear while the login screen itself is rendering, because the
	 * form posts back to it, and for logged-in users, whose logout link points
	 * at it. Anywhere else the slug would be going into markup a logged-out
	 * visitor can read, so wp-login.php is left in place instead. That URL is
	 * blocked, which is why the links themselves are removed as well.
	 *
	 * @return bool
	 */
	private function should_expose_login_url() {
		if ( ! $this->is_hiding_public_login_links() ) {
			return true;
		}

		return $this->serving_login_page || is_admin() || ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() );
	}

	/**
	 * Whether the option to keep the slug out of public markup is enabled.
	 *
	 * @return bool
	 */
	private function is_hiding_public_login_links() {
		return '1' === (string) get_option( self::OPTION_HIDE_LINKS, '1' );
	}

	/**
	 * Removes the front-end "Log in" and "Register" links from pages served to
	 * logged-out visitors, since those links would expose the custom slug.
	 *
	 * @param string $link Markup for the link, which may already be empty.
	 * @return string
	 */
	public function filter_public_login_link( $link ) {
		if ( $this->should_expose_login_url() ) {
			return $link;
		}

		return '';
	}

	/**
	 * Strips the link out of the comment form's "you must be logged in"
	 * notice, keeping the message itself.
	 *
	 * @param array $defaults Comment form arguments.
	 * @return array
	 */
	public function filter_comment_form_login_link( $defaults ) {
		if ( $this->should_expose_login_url() ) {
			return $defaults;
		}

		if ( isset( $defaults['must_log_in'] ) ) {
			$defaults['must_log_in'] = '<p class="must-log-in">' . esc_html__( 'You must be logged in to post a comment.', 'devorbit-login-shield' ) . '</p>';
		}

		return $defaults;
	}

	/*
	 * -----------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------
	 */

	/**
	 * Returns the currently configured login slug, falling back to the
	 * default if none is set.
	 *
	 * @return string
	 */
	public function get_login_slug() {
		$slug = get_option( self::OPTION_SLUG, self::DEFAULT_SLUG );
		$slug = sanitize_title( $slug );

		return $slug ? $slug : self::DEFAULT_SLUG;
	}

	/**
	 * Registers the settings screen under Settings > DevOrbit Login Shield.
	 */
	public function add_settings_page() {
		add_options_page(
			esc_html__( 'DevOrbit Login Shield', 'devorbit-login-shield' ),
			esc_html__( 'DevOrbit Login Shield', 'devorbit-login-shield' ),
			'manage_options',
			'devorbit-login-shield',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Adds a "Settings" link to the plugin's row on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=devorbit-login-shield' ) ),
			esc_html__( 'Settings', 'devorbit-login-shield' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Registers the plugin's options with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_SLUG,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_slug' ),
				'default'           => self::DEFAULT_SLUG,
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_REDIRECT_MODE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_redirect_mode' ),
				'default'           => 'home',
			)
		);

		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_HIDE_LINKS,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => '1',
			)
		);
	}

	/**
	 * Sanitizes a checkbox value to either '1' or an empty string.
	 *
	 * @param mixed $input Raw value submitted by the settings form.
	 * @return string
	 */
	public function sanitize_checkbox( $input ) {
		return '1' === (string) $input ? '1' : '';
	}

	/**
	 * Sanitizes and validates the requested slug. Rejects reserved words
	 * and slugs that collide with an existing page/post, and surfaces a
	 * settings error to the admin so failures are never silent.
	 *
	 * @param string $input Raw value submitted by the settings form.
	 * @return string
	 */
	public function sanitize_slug( $input ) {
		$old_value = get_option( self::OPTION_SLUG, self::DEFAULT_SLUG );
		$slug      = sanitize_title( (string) $input );

		if ( '' === $slug ) {
			add_settings_error(
				self::OPTION_SLUG,
				'devorbit_login_shield_empty_slug',
				esc_html__( 'The login URL cannot be empty. Reverting to the previous value.', 'devorbit-login-shield' )
			);
			return $old_value;
		}

		if ( in_array( $slug, $this->reserved_slugs, true ) ) {
			add_settings_error(
				self::OPTION_SLUG,
				'devorbit_login_shield_reserved_slug',
				esc_html__( 'That slug is reserved by WordPress and cannot be used. Reverting to the previous value.', 'devorbit-login-shield' )
			);
			return $old_value;
		}

		if ( $this->slug_conflicts_with_content( $slug ) ) {
			add_settings_error(
				self::OPTION_SLUG,
				'devorbit_login_shield_conflicting_slug',
				esc_html__( 'That slug matches an existing page or post URL. Choose a different one.', 'devorbit-login-shield' )
			);
			return $old_value;
		}

		return $slug;
	}

	/**
	 * Sanitizes the blocked-request behaviour setting.
	 *
	 * @param string $input Raw value submitted by the settings form.
	 * @return string
	 */
	public function sanitize_redirect_mode( $input ) {
		$input = (string) $input;
		return in_array( $input, array( 'home', '404' ), true ) ? $input : 'home';
	}

	/**
	 * Checks whether a slug is already in use by a published page or post,
	 * to avoid the login form silently shadowing real site content.
	 *
	 * @param string $slug Candidate slug.
	 * @return bool
	 */
	private function slug_conflicts_with_content( $slug ) {
		$existing = get_page_by_path( $slug, OBJECT, get_post_types( array( 'public' => true ) ) );
		return null !== $existing;
	}

	/**
	 * Renders the settings screen markup.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_slug = get_option( self::OPTION_SLUG, self::DEFAULT_SLUG );
		$current_mode = get_option( self::OPTION_REDIRECT_MODE, 'home' );
		$hide_links   = (string) get_option( self::OPTION_HIDE_LINKS, '1' );
		$login_url    = home_url( '/' . $current_slug . '/' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DevOrbit Login Shield', 'devorbit-login-shield' ); ?></h1>

			<p>
				<?php esc_html_e( 'Your login page is currently reachable at:', 'devorbit-login-shield' ); ?>
				<code><?php echo esc_html( $login_url ); ?></code>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="devorbit_login_shield_slug"><?php esc_html_e( 'Login slug', 'devorbit-login-shield' ); ?></label>
						</th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code>
							<input
								type="text"
								id="devorbit_login_shield_slug"
								name="<?php echo esc_attr( self::OPTION_SLUG ); ?>"
								value="<?php echo esc_attr( $current_slug ); ?>"
								class="regular-text"
								autocomplete="off"
								spellcheck="false"
							/>
							<p class="description">
								<?php esc_html_e( 'Letters, numbers and hyphens only. wp-login.php and wp-admin will stop working directly once this is saved.', 'devorbit-login-shield' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Blocked request behaviour', 'devorbit-login-shield' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input
										type="radio"
										name="<?php echo esc_attr( self::OPTION_REDIRECT_MODE ); ?>"
										value="home"
										<?php checked( $current_mode, 'home' ); ?>
									/>
									<?php esc_html_e( 'Redirect to the home page', 'devorbit-login-shield' ); ?>
								</label>
								<br />
								<label>
									<input
										type="radio"
										name="<?php echo esc_attr( self::OPTION_REDIRECT_MODE ); ?>"
										value="404"
										<?php checked( $current_mode, '404' ); ?>
									/>
									<?php esc_html_e( 'Show a 404 Not Found page', 'devorbit-login-shield' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Public login links', 'devorbit-login-shield' ); ?></th>
						<td>
							<?php // Submitted when the box is unchecked, so the setting can be turned off. ?>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_HIDE_LINKS ); ?>" value="0" />
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_HIDE_LINKS ); ?>"
									value="1"
									<?php checked( $hide_links, '1' ); ?>
								/>
								<?php esc_html_e( 'Keep the login URL out of public page markup', 'devorbit-login-shield' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Removes the "Log in" and "Register" links that themes, the Meta widget and the comment form show to logged-out visitors. Those links are built from your login URL, so leaving them in place publishes it in the HTML of every page that renders one.', 'devorbit-login-shield' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<p class="description">
				<strong><?php esc_html_e( 'Before saving,', 'devorbit-login-shield' ); ?></strong>
				<?php esc_html_e( 'bookmark the new login URL shown above. If you get locked out, delete or rename this plugin\'s folder via FTP/SFTP or your hosting file manager to disable it immediately.', 'devorbit-login-shield' ); ?>
			</p>
		</div>
		<?php
	}
}
