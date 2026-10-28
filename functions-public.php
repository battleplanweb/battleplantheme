<?php
/* Battle Plan Web Design Functions: Public (plug-ins)

/*--------------------------------------------------------------
>>> TABLE OF CONTENTS:
----------------------------------------------------------------
# Metabox Constructor
# User Switching
# Adding Nonces

--------------------------------------------------------------*/

/*--------------------------------------------------------------
# Metabox Constructor
--------------------------------------------------------------*/

	// Post meta boxes holding text / textarea / wysiwyg fields (used for the Page Top & Bottom editors in functions.php)
	if ( !class_exists('Metabox_Constructor') ) :
		class Metabox_Constructor {
			const BLOCK_NAMESPACE = 'mcc-box'; // (A.K.A "Metabox Constructor Class")
			private $_meta_box;
			private $_nonce_name;
			private $_fields = [];

			public function __construct($meta_box_config) {
				$this->_meta_box = array_merge(['context' => 'advanced', 'priority' => 'default'], $meta_box_config);
				$this->_nonce_name = $meta_box_config['id'] . '_nonce';

				add_action('add_meta_boxes', [$this, 'add']);
				add_action('save_post', [$this, 'save']);
			}

			public function add() {
				add_meta_box($this->_meta_box['id'], $this->_meta_box['title'], [$this, 'show'], $this->_meta_box['screen'], $this->_meta_box['context'], $this->_meta_box['priority']);
			}

			public function show($post) {
				wp_nonce_field(basename(__FILE__), $this->_nonce_name);
				echo '<div class="' . self::BLOCK_NAMESPACE . '">';
				foreach ($this->_fields as $field) {
					$this->{'show_field_' . $field['type']}($field, get_post_meta($post->ID, $field['id'], true));
				}
				echo '</div>';
			}

			public function save($post_id) {
				if (
					(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
					wp_is_post_revision($post_id) ||
					!isset($_POST[$this->_nonce_name]) ||
					!wp_verify_nonce($_POST[$this->_nonce_name], basename(__FILE__)) ||
					!current_user_can('edit_post', $post_id)
				) return;

				foreach ($this->_fields as $field) {
					if (!isset($_POST[$field['id']])) {
						delete_post_meta($post_id, $field['id']);
						continue;
					}

					$value = $_POST[$field['id']];
					if ($field['type'] !== 'wysiwyg') $value = sanitize_text_field($value);
					elseif (!current_user_can('unfiltered_html')) $value = wp_kses_post($value);

					update_post_meta($post_id, $field['id'], $value);
				}
			}

			private function field_class($element, $isField = true) {
				return trim(($isField ? self::BLOCK_NAMESPACE . '__field ' : '') . self::BLOCK_NAMESPACE . '__' . ($isField ? 'field-' : '') . $element);
			}

			private function before_field($field) {
				printf('<div class="%s %s">', esc_attr($this->field_class('field-container', false)), esc_attr($this->field_class($field['type'] . '-container', false)));

				if (isset($field['label'])) printf('<label class="%s" for="%s">%s</label>', esc_attr($this->field_class('label', false)), esc_attr($field['id']), esc_html($field['label']));
				if (isset($field['desc'])) printf('<p class="%s">%s</p>', esc_attr($this->field_class('description', false)), esc_html($field['desc']));
			}

			private function add_field($type, $args) {
				$this->_fields[] = array_merge(['type' => $type], $args);
			}

			public function addText($args) { $this->add_field('text', $args); }
			public function addTextArea($args) { $this->add_field('textarea', $args); }
			public function addWysiwyg($args) { $this->add_field('wysiwyg', $args); }

			public function show_field_text($field, $meta) {
				$this->before_field($field);
				printf('<input type="text" class="%1$s" id="%2$s" name="%2$s" value="%3$s">', esc_attr($this->field_class($field['type'])), esc_attr($field['id']), esc_attr($meta));
				echo '</div>';
			}

			public function show_field_textarea($field, $meta) {
				$this->before_field($field);
				printf('<textarea class="%1$s" id="%2$s" name="%2$s">%3$s</textarea>', esc_attr($this->field_class($field['type'])), esc_attr($field['id']), esc_html($meta));
				echo '</div>';
			}

			public function show_field_wysiwyg($field, $meta) {
				$this->before_field($field);
				wp_editor($meta, $field['id']);
				echo '</div>';
			}
		}
	endif;

/*--------------------------------------------------------------
# User Switching
--------------------------------------------------------------*/

	if ( !defined('USER_SWITCHING_COOKIE') ) define( 'USER_SWITCHING_COOKIE', 'wordpress_user_sw_' . COOKIEHASH );
	if ( !defined('USER_SWITCHING_SECURE_COOKIE') ) define( 'USER_SWITCHING_SECURE_COOKIE', 'wordpress_user_sw_secure_' . COOKIEHASH );
	if ( !defined('USER_SWITCHING_OLDUSER_COOKIE') ) define( 'USER_SWITCHING_OLDUSER_COOKIE', 'wordpress_user_sw_olduser_' . COOKIEHASH );

	if ( !class_exists('user_switching') ) :

		class user_switching {

			public static $application = 'WordPress/User Switching';

			public function init_hooks() {
				add_filter( 'user_has_cap',          array( $this, 'filter_user_has_cap' ), 10, 4 );
				add_filter( 'map_meta_cap',          array( $this, 'filter_map_meta_cap' ), 10, 4 );
				add_filter( 'user_row_actions',      array( $this, 'filter_user_row_actions' ), 10, 2 );
				add_action( 'init',                  array( $this, 'action_init' ) );
				add_action( 'all_admin_notices',     array( $this, 'action_admin_notices' ), 1 );
				add_action( 'wp_logout',             'user_switching_clear_olduser_cookie' );
				add_action( 'wp_login',              'user_switching_clear_olduser_cookie' );
				add_filter( 'ms_user_row_actions',   array( $this, 'filter_user_row_actions' ), 10, 2 );
				add_filter( 'login_message',         array( $this, 'filter_login_message' ), 1 );
				add_filter( 'removable_query_args',  array( $this, 'filter_removable_query_args' ) );
				add_action( 'wp_meta',               array( $this, 'action_wp_meta' ) );
				add_action( 'wp_footer',             array( $this, 'action_wp_footer' ) );
				add_action( 'personal_options',      array( $this, 'action_personal_options' ) );
				add_action( 'admin_bar_menu',        array( $this, 'action_admin_bar_menu' ), 11 );
				add_action( 'switch_to_user',        array( $this, 'forget_woocommerce_session' ) );
				add_action( 'switch_back_user',      array( $this, 'forget_woocommerce_session' ) );
			}

			public function action_personal_options( WP_User $user ) {
				$link = self::maybe_switch_url( $user );
				if ( ! $link ) return;

				?>
				<tr class="user-switching-wrap">
					<th scope="row"><?php echo esc_html_x( 'User Switching', 'User Switching title on user profile screen', 'user-switching' ); ?></th>
					<td><a id="user_switching_switcher" href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Switch&nbsp;To', 'user-switching' ); ?></a></td>
				</tr>
				<?php
			}

			public static function remember() {
				$cookie_life = apply_filters( 'auth_cookie_expiration', 172800, get_current_user_id(), false );
				$current     = wp_parse_auth_cookie( '', 'logged_in' );

				if ( ! $current ) return false;

				return ( intval( $current['expiration'] ) - time() > $cookie_life );
			}

			public function action_init() {
				if ( ! isset( $_REQUEST['action'] ) ) return;

				$current_user = ( is_user_logged_in() ) ? wp_get_current_user() : null;

				switch ( $_REQUEST['action'] ) {

					case 'switch_to_user':
						$user_id = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;

						if ( ! current_user_can( 'switch_to_user', $user_id ) ) {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 403 );
						}

						check_admin_referer( "switch_to_user_{$user_id}" );

						$user = switch_to_user( $user_id, self::remember() );
						if ( ! $user ) wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 404 );

						$redirect_to = self::get_redirect( $user, $current_user );
						if ( ! $redirect_to ) $redirect_to = current_user_can( 'read' ) ? admin_url() : home_url();

						wp_safe_redirect( add_query_arg( array( 'user_switched' => 'true' ), $redirect_to ), 302, self::$application );
						exit;

					case 'switch_to_olduser':
						$old_user = self::get_old_user();
						if ( ! $old_user ) {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 400 );
						}

						if ( ! self::authenticate_old_user( $old_user ) ) {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 403 );
						}

						check_admin_referer( "switch_to_olduser_{$old_user->ID}" );

						if ( ! switch_to_user( $old_user->ID, self::remember(), false ) ) {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 404 );
						}

						if ( ! empty( $_REQUEST['interim-login'] ) && function_exists( 'login_header' ) ) {
							$GLOBALS['interim_login'] = 'success'; // @codingStandardsIgnoreLine
							login_header( '', '' );
							exit;
						}

						wp_safe_redirect( add_query_arg( array( 'user_switched' => 'true', 'switched_back' => 'true' ), admin_url( 'users.php' ) ), 302, self::$application );
						exit;

					case 'switch_off':
						if ( ! $current_user || ! current_user_can( 'switch_off' ) ) {
							wp_die( esc_html__( 'Could not switch off.', 'user-switching' ) );
						}

						check_admin_referer( "switch_off_{$current_user->ID}" );

						if ( ! switch_off_user() ) wp_die( esc_html__( 'Could not switch off.', 'user-switching' ) );

						$redirect_to = self::get_redirect( null, $current_user ) ?: home_url();
						wp_safe_redirect( add_query_arg( array( 'switched_off' => 'true' ), $redirect_to ), 302, self::$application );
						exit;
				}
			}

			protected static function get_redirect( ?WP_User $new_user = null, ?WP_User $old_user = null ) {
				$requested_redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';
				$redirect_to           = $requested_redirect_to !== '' ? self::remove_query_args( $requested_redirect_to ) : '';

				return $new_user
					? apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $new_user )
					: apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $old_user );
			}

			// "Switch Back -> Name" link text
			private static function switch_back_label( WP_User $old_user ) {
				return sprintf( __( 'Switch Back -> %1$s', 'user-switching' ), $old_user->display_name, $old_user->user_login );
			}

			// Switch-back URL that returns to the current page
			private static function switch_back_here_url( WP_User $old_user ) {
				return add_query_arg( array( 'redirect_to' => urlencode( self::current_url() ) ), self::switch_back_url( $old_user ) );
			}

			public function action_admin_notices() {
				$user     = wp_get_current_user();
				$old_user = self::get_old_user();

				if ( $old_user ) {
					$switched_locale = false;
					$lang_attr       = '';

					if ( function_exists( 'get_user_locale' ) ) {
						$locale          = get_user_locale( $old_user );
						$switched_locale = switch_to_locale( $locale );
						$lang_attr       = str_replace( '_', '-', $locale );
					}

					$message       = '';
					$just_switched = isset( $_GET['user_switched'] );
					if ( $just_switched ) {
						$message = esc_html( sprintf( __( 'Switched to %1$s (%2$s).', 'user-switching' ), $user->display_name, $user->user_login ) );
					}
					$switch_back_url = self::switch_back_here_url( $old_user );
					$message .= sprintf( ' <a href="%s">%s</a>.', esc_url( $switch_back_url ), esc_html( self::switch_back_label( $old_user ) ) );
					$message  = apply_filters( 'user_switching_switched_message', $message, $user, $old_user, $switch_back_url, $just_switched );

					?>
					<div id="user_switching" class="updated notice is-dismissible">
						<?php echo $lang_attr ? '<p lang="' . esc_attr( $lang_attr ) . '">' : '<p>'; ?>
						<span class="dashicons dashicons-admin-users" style="color:#56c234" aria-hidden="true"></span>
						<?php echo wp_kses( $message, array( 'a' => array( 'href' => array() ) ) ); ?>
						</p>
					</div>
					<?php
					if ( $switched_locale ) restore_previous_locale();

				} elseif ( isset( $_GET['user_switched'] ) ) {
					$text = isset( $_GET['switched_back'] ) ? __( 'Switched back to %1$s (%2$s).', 'user-switching' ) : __( 'Switched to %1$s (%2$s).', 'user-switching' );
					?>
					<div id="user_switching" class="updated notice is-dismissible">
						<p><?php echo esc_html( sprintf( $text, $user->display_name, $user->user_login ) ); ?></p>
					</div>
					<?php
				}
			}

			public static function get_old_user() {
				$cookie = user_switching_get_olduser_cookie();
				if ( empty( $cookie ) ) return false;

				$old_user_id = wp_validate_auth_cookie( $cookie, 'logged_in' );
				return $old_user_id ? get_userdata( $old_user_id ) : false;
			}

			public static function authenticate_old_user( WP_User $user ) {
				$cookie = user_switching_get_auth_cookie();
				if ( empty( $cookie ) ) return false;

				$old_user_id = wp_validate_auth_cookie( end( $cookie ), self::secure_auth_cookie() ? 'secure_auth' : 'auth' );
				return $old_user_id && $user->ID === $old_user_id;
			}

			public function action_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
				if ( ! is_admin_bar_showing() ) return;

				if ( method_exists( $wp_admin_bar, 'get_node' ) ) {
					if ( ! $wp_admin_bar->get_node( 'user-actions' ) ) return;
					$parent = 'user-actions';
				} else {
					$parent = get_option( 'show_avatars' ) ? 'my-account-with-avatar' : 'my-account';
				}

				$old_user = self::get_old_user();

				if ( $old_user ) {
					$wp_admin_bar->add_node( array(
						'parent' => $parent,
						'id'     => 'switch-back',
						'title'  => esc_html( self::switch_back_label( $old_user ) ),
						'href'   => self::switch_back_here_url( $old_user ),
					) );
				}

				if ( current_user_can( 'switch_off' ) ) {
					$url = self::switch_off_url( wp_get_current_user() );
					if ( ! is_admin() ) $url = add_query_arg( array( 'redirect_to' => urlencode( self::current_url() ) ), $url );

					$wp_admin_bar->add_node( array(
						'parent' => $parent,
						'id'     => 'switch-off',
						'title'  => esc_html__( 'Switch Off', 'user-switching' ),
						'href'   => $url,
					) );
				}

				if ( ! is_admin() && is_author() && ( get_queried_object() instanceof WP_User ) ) {
					if ( $old_user ) {
						$wp_admin_bar->add_node( array(
							'parent' => 'edit',
							'id'     => 'author-switch-back',
							'title'  => esc_html( self::switch_back_label( $old_user ) ),
							'href'   => self::switch_back_here_url( $old_user ),
						) );
					} elseif ( current_user_can( 'switch_to_user', get_queried_object_id() ) ) {
						$wp_admin_bar->add_node( array(
							'parent' => 'edit',
							'id'     => 'author-switch-to',
							'title'  => esc_html__( 'Switch&nbsp;To', 'user-switching' ),
							'href'   => add_query_arg( array( 'redirect_to' => urlencode( self::current_url() ) ), self::switch_to_url( get_queried_object() ) ),
						) );
					}
				}
			}

			public function action_wp_meta() {
				$old_user = self::get_old_user();
				if ( ! $old_user instanceof WP_User ) return;

				printf( '<li id="user_switching_switch_on"><a href="%s">%s</a></li>', esc_url( self::switch_back_here_url( $old_user ) ), esc_html( self::switch_back_label( $old_user ) ) );
			}

			public function action_wp_footer() {
				if ( is_admin_bar_showing() || did_action( 'wp_meta' ) || ! apply_filters( 'user_switching_in_footer', true ) ) return;

				$old_user = self::get_old_user();
				if ( ! $old_user instanceof WP_User ) return;

				$new_user = wp_get_current_user();
				$role     = $new_user->roles[1] ?? $new_user->roles[0] ?? '';

				printf(
					'<p id="user_switching_switch_on">Viewing as %s %s [ %s // %s ]&nbsp;&nbsp;&nbsp;·&nbsp;&nbsp;&nbsp;<a href="%s">%s</a></p>',
					esc_html( $new_user->user_firstname ),
					esc_html( $new_user->user_lastname ),
					esc_html( $new_user->user_login ),
					esc_html( $role ),
					esc_url( self::switch_back_here_url( $old_user ) ),
					esc_html__( 'Switch Back', 'user-switching' )
				);
			}

			public function filter_login_message( $message ) {
				$old_user = self::get_old_user();
				if ( ! $old_user instanceof WP_User ) return $message;

				$url = self::switch_back_url( $old_user );

				if ( ! empty( $_REQUEST['interim-login'] ) ) {
					$url = add_query_arg( array( 'interim-login' => '1' ), $url );
				} elseif ( ! empty( $_REQUEST['redirect_to'] ) ) {
					$url = add_query_arg( array( 'redirect_to' => urlencode( wp_unslash( $_REQUEST['redirect_to'] ) ) ), $url );
				}

				$message .= '<p class="message" id="user_switching_switch_on">';
				$message .= '<span class="dashicons dashicons-admin-users" style="color:#56c234" aria-hidden="true"></span> ';
				$message .= sprintf( '<a href="%1$s" onclick="window.location.href=\'%1$s\';return false;">%2$s</a>', esc_url( $url ), esc_html( self::switch_back_label( $old_user ) ) );
				$message .= '</p>';

				return $message;
			}

			public function filter_user_row_actions( array $actions, WP_User $user ) {
				$link = self::maybe_switch_url( $user );
				if ( $link ) $actions['switch_to_user'] = sprintf( '<a href="%s">%s</a>', esc_url( $link ), esc_html__( 'Switch&nbsp;To', 'user-switching' ) );

				return $actions;
			}

			public function filter_removable_query_args( array $args ) {
				return array_merge( $args, array( 'user_switched', 'switched_off', 'switched_back' ) );
			}

			public static function maybe_switch_url( WP_User $user ) {
				$old_user = self::get_old_user();

				if ( $old_user && $old_user->ID === $user->ID ) return self::switch_back_url( $old_user );
				if ( current_user_can( 'switch_to_user', $user->ID ) ) return self::switch_to_url( $user );
				return false;
			}

			public static function switch_to_url( WP_User $user ) {
				return wp_nonce_url( add_query_arg( array( 'action' => 'switch_to_user', 'user_id' => $user->ID, 'nr' => 1 ), wp_login_url() ), "switch_to_user_{$user->ID}" );
			}

			public static function switch_back_url( WP_User $user ) {
				return wp_nonce_url( add_query_arg( array( 'action' => 'switch_to_olduser', 'nr' => 1 ), wp_login_url() ), "switch_to_olduser_{$user->ID}" );
			}

			public static function switch_off_url( WP_User $user ) {
				return wp_nonce_url( add_query_arg( array( 'action' => 'switch_off', 'nr' => 1 ), wp_login_url() ), "switch_off_{$user->ID}" );
			}

			public static function current_url() {
				return ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			}

			public static function remove_query_args( $url ) {
				return remove_query_arg( wp_removable_query_args(), $url );
			}

			public static function secure_olduser_cookie() {
				return is_ssl() && 'https' === parse_url( home_url(), PHP_URL_SCHEME );
			}

			public static function secure_auth_cookie() {
				return is_ssl() && 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME );
			}

			public function forget_woocommerce_session() {
				if ( function_exists( 'WC' ) && isset( WC()->session ) && method_exists( WC()->session, 'forget_session' ) ) {
					WC()->session->forget_session();
				}
			}

			public function filter_user_has_cap( array $user_caps, array $required_caps, array $args, WP_User $user ) {
				if ( 'switch_to_user' === $args[0] ) {
					if ( empty( $args[2] ) ) {
						$user_caps['switch_to_user'] = false;
					} elseif ( array_key_exists( 'switch_users', $user_caps ) ) {
						$user_caps['switch_to_user'] = $user_caps['switch_users'];
					} else {
						$user_caps['switch_to_user'] = ( user_can( $user->ID, 'edit_user', $args[2] ) && ( $args[2] !== $user->ID ) );
					}
				} elseif ( 'switch_off' === $args[0] ) {
					$user_caps['switch_off'] = array_key_exists( 'switch_users', $user_caps ) ? $user_caps['switch_users'] : user_can( $user->ID, 'edit_users' );
				}
				return $user_caps;
			}

			public function filter_map_meta_cap( array $required_caps, $cap, $user_id, array $args ) {
				if ( 'switch_to_user' === $cap && ( empty( $args[0] ) || $args[0] === $user_id ) ) {
					$required_caps[] = 'do_not_allow';
				}
				return $required_caps;
			}

			public static function get_instance() {
				static $instance;
				return $instance ??= new user_switching();
			}

			private function __construct() {}
		}

		if ( ! function_exists( 'user_switching_set_olduser_cookie' ) ) {
			function user_switching_set_olduser_cookie( $old_user_id, $pop = false, $token = '' ) {
				$secure_auth_cookie    = user_switching::secure_auth_cookie();
				$secure_olduser_cookie = user_switching::secure_olduser_cookie();
				$expiration            = time() + 2 * DAY_IN_SECONDS;
				$auth_cookie           = user_switching_get_auth_cookie();
				$olduser_cookie        = wp_generate_auth_cookie( $old_user_id, $expiration, 'logged_in', $token );
				$auth_cookie_name      = $secure_auth_cookie ? USER_SWITCHING_SECURE_COOKIE : USER_SWITCHING_COOKIE;
				$scheme                = $secure_auth_cookie ? 'secure_auth' : 'auth';

				if ( $pop ) {
					array_pop( $auth_cookie );
				} else {
					$auth_cookie[] = wp_generate_auth_cookie( $old_user_id, $expiration, $scheme, $token );
				}

				$auth_cookie = json_encode( $auth_cookie );
				if ( false === $auth_cookie ) return;

				do_action( 'set_user_switching_cookie', $auth_cookie, $expiration, $old_user_id, $scheme, $token );
				do_action( 'set_olduser_cookie', $olduser_cookie, $expiration, $old_user_id, 'logged_in', $token );

				if ( ! apply_filters( 'user_switching_send_auth_cookies', true ) ) return;

				setcookie( $auth_cookie_name, $auth_cookie, $expiration, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_auth_cookie, true );
				setcookie( USER_SWITCHING_OLDUSER_COOKIE, $olduser_cookie, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure_olduser_cookie, true );
			}
		}

		if ( ! function_exists( 'user_switching_clear_olduser_cookie' ) ) {
			function user_switching_clear_olduser_cookie( $clear_all = true ) {
				$auth_cookie = user_switching_get_auth_cookie();
				if ( ! empty( $auth_cookie ) ) array_pop( $auth_cookie );

				if ( $clear_all || empty( $auth_cookie ) ) {
					do_action( 'clear_olduser_cookie' );

					if ( ! apply_filters( 'user_switching_send_auth_cookies', true ) ) return;

					$expire = time() - YEAR_IN_SECONDS;
					setcookie( USER_SWITCHING_COOKIE,         ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN );
					setcookie( USER_SWITCHING_SECURE_COOKIE,  ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN );
					setcookie( USER_SWITCHING_OLDUSER_COOKIE, ' ', $expire, COOKIEPATH, COOKIE_DOMAIN );
					return;
				}

				$scheme      = user_switching::secure_auth_cookie() ? 'secure_auth' : 'auth';
				$old_cookie  = end( $auth_cookie );
				$old_user_id = wp_validate_auth_cookie( $old_cookie, $scheme );
				if ( ! $old_user_id ) return;

				$parts = wp_parse_auth_cookie( $old_cookie, $scheme );
				if ( false !== $parts ) user_switching_set_olduser_cookie( $old_user_id, true, $parts['token'] );
			}
		}

		if ( ! function_exists( 'user_switching_get_olduser_cookie' ) ) {
			function user_switching_get_olduser_cookie() {
				return isset( $_COOKIE[ USER_SWITCHING_OLDUSER_COOKIE ] ) ? wp_unslash( $_COOKIE[ USER_SWITCHING_OLDUSER_COOKIE ] ) : false;
			}
		}

		if ( ! function_exists( 'user_switching_get_auth_cookie' ) ) {
			function user_switching_get_auth_cookie() {
				$auth_cookie_name = user_switching::secure_auth_cookie() ? USER_SWITCHING_SECURE_COOKIE : USER_SWITCHING_COOKIE;

				$cookie = ( isset( $_COOKIE[ $auth_cookie_name ] ) && is_string( $_COOKIE[ $auth_cookie_name ] ) ) ? json_decode( wp_unslash( $_COOKIE[ $auth_cookie_name ] ) ) : null;

				return is_array( $cookie ) ? $cookie : array();
			}
		}

		if ( ! function_exists( 'switch_to_user' ) ) {
			function switch_to_user( $user_id, $remember = false, $set_old_user = true ) {
				$user = get_userdata( $user_id );
				if ( ! $user ) return false;

				$old_user_id  = is_user_logged_in() ? get_current_user_id() : false;
				$old_token    = wp_get_session_token();
				$auth_cookies = user_switching_get_auth_cookie();
				$auth_cookie  = end( $auth_cookies );
				$cookie_parts = $auth_cookie ? wp_parse_auth_cookie( $auth_cookie ) : false;

				if ( $set_old_user && $old_user_id ) {
					$new_token = '';
					user_switching_set_olduser_cookie( $old_user_id, false, $old_token );
				} else {
					$new_token = $cookie_parts['token'] ?? '';
					user_switching_clear_olduser_cookie( false );
				}

				$session_filter = function( array $session ) use ( $old_user_id, $old_token ) {
					$session['switched_from_id']      = $old_user_id;
					$session['switched_from_session'] = $old_token;
					return $session;
				};

				add_filter( 'attach_session_information', $session_filter, 99 );

				wp_clear_auth_cookie();
				wp_set_auth_cookie( $user_id, $remember, '', $new_token );
				wp_set_current_user( $user_id );

				remove_filter( 'attach_session_information', $session_filter, 99 );

				do_action( $set_old_user ? 'switch_to_user' : 'switch_back_user', $user_id, $old_user_id, $new_token, $old_token );

				if ( $old_token && $old_user_id && ! $set_old_user ) {
					WP_Session_Tokens::get_instance( $old_user_id )->destroy( $old_token );
				}

				return $user;
			}
		}

		if ( ! function_exists( 'switch_off_user' ) ) {
			function switch_off_user() {
				$old_user_id = get_current_user_id();
				if ( ! $old_user_id ) return false;

				$old_token = wp_get_session_token();

				user_switching_set_olduser_cookie( $old_user_id, false, $old_token );
				wp_clear_auth_cookie();
				wp_set_current_user( 0 );

				do_action( 'switch_off_user', $old_user_id, $old_token );

				return true;
			}
		}

		if ( ! function_exists( 'current_user_switched' ) ) {
			function current_user_switched() {
				return is_user_logged_in() ? user_switching::get_old_user() : false;
			}
		}

		$GLOBALS['user_switching'] = user_switching::get_instance();
		$GLOBALS['user_switching']->init_hooks();
	endif;

/*--------------------------------------------------------------
# Adding Nonces
--------------------------------------------------------------*/

	// Replaces $wp_scripts (functions.php, init) so the inline wp_localize_script blocks get the CSP nonce
	if ( !class_exists('WP_Filterable_Scripts') ) :
		class WP_Filterable_Scripts extends WP_Scripts {

			private $type_attr = '';

			public function __construct() {
				parent::__construct();

				if ( ! is_admin() && ! current_theme_supports( 'html5', 'script' ) ) $this->type_attr = " type='text/javascript'";

				if ( $GLOBALS['wp_scripts'] instanceof WP_Scripts ) {
					$this->registered += $GLOBALS['wp_scripts']->registered;
				}
			}

			public function print_extra_script( $handle, $display = true ) {
				$output = $this->get_data( $handle, 'data' );
				if ( ! $output ) return;
				if ( ! $display ) return $output;

				$tag = apply_filters( 'battleplan_csp_localized_scripts', sprintf( "<script%s id='%s-js-extra'>\n", $this->type_attr, esc_attr( $handle ) ), $handle );

				// CDATA is not needed for HTML 5.
				$tag .= $this->type_attr ? "/* <![CDATA[ */\n$output\n/* ]]> */\n" : "$output\n";
				$tag .= "</script>\n";

				echo $tag;
				return true;
			}
		}
	endif;
