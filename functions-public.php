<?php
/* Battle Plan Web Design Functions (Public / PlugIns)
 
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

	if ( !class_exists('Metabox_Constructor') ) :
		class Metabox_Constructor {
			const BLOCK_NAMESPACE = 'mcc-box'; // (A.K.A "Metabox Constructor Class")
			const REPEATER_INDEX_PLACEHOLDER = 'CurrentCounter';
			const REPEATER_ITEM_NUMBER_PLACEHOLDER = 'ItemNumber';
			private $_meta_box;
			private $_folder_name;
			private $_path;
			private $_nonce_name;
			private $_fields;

			public function __construct($meta_box_config) {				
				$defaults = array(
					'context' => 'advanced',
					'priority' => 'default'
				);

				$this->_meta_box = array_merge($defaults, $meta_box_config);
				$this->_nonce_name = $meta_box_config['id'] . '_nonce';
				$this->_folder_name = 'wp-metabox-constructor-class';
				$this->_path = plugins_url($this->_folder_name, plugin_basename(dirname( __FILE__ )));

				add_action('add_meta_boxes', array($this, 'add'));
				add_action('save_post', array($this, 'save'));
				add_action('admin_enqueue_scripts', array($this, 'scripts'));
			}

			public function scripts() {
				global $typenow;
				wp_enqueue_media();
				if (
					(is_array($this->_meta_box['screen']) && in_array($typenow, $this->_meta_box['screen'])) ||
					(is_string($this->_meta_box['screen']) && $typenow == $this->_meta_box['screen'])
				) {
					wp_enqueue_style(sprintf('%s-styles', self::BLOCK_NAMESPACE), $this->_path . '/style.css', array());
			        wp_enqueue_script(sprintf('%s-scripts', self::BLOCK_NAMESPACE), $this->_path . '/script.js', array('jquery'));
				}
			}

			public function add() {
				add_meta_box(
					$this->_meta_box['id'],
					$this->_meta_box['title'],
					array($this, 'show'), // callback
					$this->_meta_box['screen'],
					$this->_meta_box['context'],
					$this->_meta_box['priority']
				);
			}

			public function show() {
				global $post;
				wp_nonce_field(basename(__FILE__), $this->_nonce_name);
				echo sprintf('<div class="%s">', self::BLOCK_NAMESPACE);
				foreach($this->_fields as $field) {
					$meta = get_post_meta($post->ID, $field['id'], true);
					call_user_func( array($this, 'show_field_' . $field['type']), $field, $meta);
				}
				echo '</div>';
			}

			public function save() {
				global $post_id, $post;

				if (
			        (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 
			        (!current_user_can('edit_post', $post_id)) || 
			        ((!isset($_POST[$this->_nonce_name]))) || 
			        (!wp_verify_nonce($_POST[$this->_nonce_name], basename(__FILE__)))
			    ) 
			    { return; }

			    foreach($this->_fields as $field) {
			    	if(isset($_POST[$field['id']])) {
			    		if($field['type'] == 'text' || $field['type'] == 'textarea') {
			    			update_post_meta($post->ID, $field['id'], sanitize_text_field($_POST[$field['id']]));
			    		} else {
			    			update_post_meta($post->ID, $field['id'], $_POST[$field['id']]);
			    		}
				    } else { delete_post_meta($post->ID, $field['id']); }
			    }
			}

			public function column($width, $contents) {
				if(isset($width, $contents)) {
					return sprintf(
						'<div class="%s %s">%s</div>',
						esc_attr( $this->get_element_class_with_namespace('col') ),
						esc_attr( $this->get_element_class_with_namespace(sprintf('col-%d', $width)) ),
						esc_html( $contents )
					);
				}
			}

			public function get_block_element_class($block, $element) {
				if(isset($block, $element)) {
					return trim(sprintf('%s__%s', $block, $element));
				}
			}

			public function get_block_element_class_with_namespace($element, $isField = true) {
				if(isset($element)) {
					return trim(sprintf(
						'%s %s%s',  
						($isField 
							? (sprintf('%s__%s', self::BLOCK_NAMESPACE, 'field')) 
							: ''
						),
						sprintf('%s__%s', self::BLOCK_NAMESPACE, ($isField ? 'field-' : '')),
						$element
					));
				}
			}

			public function get_element_class_with_namespace($suffix) {
				if(isset($suffix)) {
					return trim(sprintf(
						'%s-%s',
						self::BLOCK_NAMESPACE,
						$suffix
					));
				}
			}

			public function before_field($field, $meta = null) {
				echo sprintf(
					'<div class="%s %s">',
					esc_attr( $this->get_block_element_class_with_namespace('field-container', false) ),
					esc_attr( $this->get_block_element_class_with_namespace($field['type'].'-container', false) )
				);

				if(isset($field['label'])) {
					echo sprintf(
						'<label class="%s" for="%s">%s</label>',
						esc_attr( $this->get_block_element_class_with_namespace('label', false) ),
						esc_attr( $field['id'] ),
						esc_html( $field['label'] )
					);
				}
				
				if(isset($field['desc']) && $field['type'] != 'checkbox') $this->get_field_description($field['desc']);
				if($field['type'] == 'image') $this->get_image_preview($field, $meta);
			}

			public function after_field($field = null) {
				if(isset($field['desc']) && $field['type'] == 'checkbox') $this->get_field_description($field['desc']);
				echo '</div>';
			}

			public function get_field_description($desc) {
				echo sprintf(
					'<p class="%s">%s</p>',
					esc_attr( $this->get_block_element_class_with_namespace('description', false) ),
					esc_html( $desc )
				);	
			}

			public function get_image_preview($field, $meta) {
				global $post;

				echo sprintf(
					'<img id="%s" class="%s" src="%s" alt="%s">',
					esc_attr( sprintf('js-%s-image-preview', $field['id']) ),
					esc_attr( sprintf('%s %s', $this->get_block_element_class_with_namespace('image-preview', false), empty($meta) ? 'is-hidden' : '') ),
					esc_attr( $meta ),
					esc_attr( '' )	
				);
			}

			public function addText($args, $repeater = false) {
				$field = array_merge(array('type' => 'text'), $args);
				if(false == $repeater) {
					$this->_fields[] = $field;
				} else { return $field; }			
			}

			public function addTextArea($args, $repeater = false) {
				$field = array_merge(array('type' => 'textarea'), $args);
				if(!$repeater) {
					$this->_fields[] = $field;
				} else { return $field; }
			}

			public function addCheckbox($args, $repeater = false) {
				$field = array_merge(array('type' => 'checkbox'), $args);
				if(!$repeater) {
					$this->_fields[] = $field;
				} else { return $field; }
			}

			public function addImage($args, $repeater = false) {
				$field = array_merge(array('type' => 'image'), $args);
				if(!$repeater) {
					$this->_fields[] = $field;
				} else { return $field; }
			}

			public function addWysiwyg($args, $repeater = false) {
				$field = array_merge(array('type' => 'wysiwyg'), $args);
				if(!$repeater) {
					$this->_fields[] = $field;
				} else { return $field; }
			}

			public function addRadio($args, $options, $repeater = false) {
				$options = array('options' => $options);
				$field = array_merge(array('type' => 'radio'), $args, $options);
				if(!$repeater) {
					$this->_fields[] = $field;
				} else { return $field; }
			}

			public function addRepeaterBlock($args) {
				$field = array_merge(array(
					'type' => 'repeater', 
					'single_label' => 'Item',
					'is_sortable' => true
				), $args);
				$this->_fields[] = $field;
			}

			public function show_field_text($field, $meta) {				
				$this->before_field($field);
				echo sprintf(
					'<input type="text" class="%1$s" id="%2$s" name="%2$s" value="%3$s">',
					esc_attr( $this->get_block_element_class_with_namespace($field['type']) ),
					esc_attr( $field['id'] ),
					esc_attr( $meta )
				);
				$this->after_field();
			}

			public function show_field_textarea($field, $meta) {
				$this->before_field($field);
				echo sprintf(
					'<textarea class="%1$s" id="%2$s" name="%2$s">%3$s</textarea>',
					esc_attr( $this->get_block_element_class_with_namespace($field['type']) ),
					esc_attr( $field['id'] ),
					esc_html( $meta )
				);
				$this->after_field();
			}	

			public function show_field_checkbox($field, $meta) {
				$this->before_field($field);
				echo sprintf(
					'<input type="checkbox" class="%1$s" id="%2$s" name="%2$s" %3$s>',
					esc_attr( $this->get_block_element_class_with_namespace($field['type']) ),
					esc_attr( $field['id'] ),
					checked(!empty($meta), true, false)
				);
				$this->after_field($field); // pass in $field to render desc below input
			}

			public function show_field_image($field, $meta) {
				$this->before_field($field, $meta); // pass in $meta for preview image
				echo sprintf(
					'<input type="hidden" id="%s" name="%s" value="%s">',
					esc_attr( 'image-' . $field['id'] ),
					esc_attr( $field['id'] ),
					(isset($meta) ? $meta : '')
				);
				echo sprintf(
					'<a class="%s button" data-hidden-input="%s">%s</a>',
					esc_attr( sprintf('js-%s-image-upload-button', self::BLOCK_NAMESPACE) ),
					esc_attr( $field['id'] ),
					esc_html( sprintf('%s Image', empty($meta) ? 'Upload' : 'Change') )
				);
				$this->after_field();
			}

			public function show_field_wysiwyg($field, $meta) {
				$this->before_field($field);
				wp_editor($meta, $field['id']);
				$this->after_field();
			}

			public function show_field_radio($field, $meta) {
				$this->before_field($field);
				foreach($field['options'] as $key => $value) {
					echo sprintf( '<label for="%1$s">%2$s</label><input type="radio" class="%3$s" id="%1$s" name="%4$s" value="%5$s" %6$s>',
						esc_attr( $field['id'] . '_' . $key ),
						esc_html( $value ),
						esc_attr( $this->get_block_element_class_with_namespace($field['type']) ),
						esc_attr( $field['id'] ),
						esc_attr( $key ),
						checked( $key == $meta, true, false )
					);
				}
				$this->after_field($field); // pass in $field to render desc below input
			}

			public function show_field_repeater($field, $meta) {
				$this->before_field($field);

				echo sprintf( '<div id="%s" class="%s">', esc_attr( sprintf('js-%s-repeated-blocks', $field['id']) ), esc_attr( $this->get_block_element_class_with_namespace('repeated-blocks', false) ));

				$count = 0;

				if(count($meta) > 0 && is_array($meta)) {
					foreach($meta as $m) {
						$this->get_repeated_block($field, $m, $count);
						$count++;
					}
				} else {
					$this->get_repeated_block($field, '', $count);
				}

				echo '</div>';

				echo sprintf(
					'<a id="%s" class="%s button">
						<span class="dashicons dashicons-plus"></span>
						%s
					</a>',
					esc_attr( sprintf('js-%s-add', $field['id']) ),
					esc_attr( $this->get_block_element_class_with_namespace('add', false)  ),
					esc_html( sprintf('Add %s', $field['single_label']) )
				);

				$this->after_field();

				ob_start();

				sprintf('<div>%s</div>', esc_html( $this->get_repeated_block($field, $meta, null, true) ));

			    $js_code = ob_get_clean();
			    $js_code = str_replace("\n", "", $js_code);
			    $js_code = str_replace("\r", "", $js_code);
			    $js_code = str_replace("'", "\"", $js_code);

				echo '<script> 
						jQuery(document).ready(function($) {
							var count = '.max(1, $count).'; // we use max() because we want count to be at least 1

							$("#js-'. $field['id'] .'-add").on("click", function() {
								var repeater = \''.$js_code.'\'
									.replace(/'. self::REPEATER_INDEX_PLACEHOLDER .'/g, count)
									.replace(/'. self::REPEATER_ITEM_NUMBER_PLACEHOLDER .'/g, count + 1);
								$("#js-'. $field['id'] .'-repeated-blocks").append(repeater);
								count++;
								return false;
							});
						});
				</script>';
			}

			public function get_repeated_block($field, $meta, $index, $isTemplate = false) {

				echo sprintf(
					'<div class="%s">',
					esc_attr( $this->get_block_element_class_with_namespace('repeated', false) )	
				);

				echo sprintf(
					'<div class="%s %s">
						<p class="%s">%s</p>
						<ul class="%s">
							<li>
								<a class="%s %s" title="%s">
									<span class="dashicons dashicons-no"></span>
								</a>
							</li>
							<li>
								<a class="%s %s" title="Click and drag to sort">
									<span class="dashicons dashicons-menu"></span>
								</a>
							</li>
						</ul>
					</div>', 
					esc_attr( $this->get_element_class_with_namespace('repeated-header', false)  ),
					esc_attr( $this->get_element_class_with_namespace('clearfix') ),
					esc_attr( sprintf('%s %s %s', $this->get_block_element_class('repeated-header', 'title'), $this->get_element_class_with_namespace('col'), $this->get_element_class_with_namespace('col-6')) ),
					esc_html( sprintf('%s '.($isTemplate ? '%s' : '%d'), $field['single_label'], ($isTemplate ? self::REPEATER_ITEM_NUMBER_PLACEHOLDER : $index + 1)) ), 
					esc_attr( sprintf('%s %s %s', $this->get_block_element_class('repeated-header', 'nav'), $this->get_element_class_with_namespace('col'), $this->get_element_class_with_namespace('col-6')) ),
					esc_attr( $this->get_block_element_class_with_namespace('repeater-button', false)  ),
					esc_attr( $this->get_block_element_class_with_namespace('remove', false)  ),
					esc_attr( sprintf('Remove %s', $field['single_label'])  ),
					esc_attr( $this->get_block_element_class_with_namespace('repeater-button', false)  ),
					esc_attr( sprintf('js-%s-sort', self::BLOCK_NAMESPACE) )
				);

				echo sprintf('<div class="%s is-hidden">', esc_attr( $this->get_block_element_class_with_namespace('repeated-content', false)  ));
					foreach($field['fields'] as $child_field) {
						$old_id = $child_field['id'];

						$child_field['id'] = sprintf(
							'%s[%s][%s]', 
							$field['id'], 
							($isTemplate ? self::REPEATER_INDEX_PLACEHOLDER : $index), 
							$child_field['id']
						);

						$child_meta = isset($meta[$old_id]) && !$isTemplate ? $meta[$old_id] : '';

						call_user_func( array($this, 'show_field_' . $child_field['type']), $child_field, $child_meta );
					}
				echo '</div></div>';
			}
 		}
	endif;
	
/*--------------------------------------------------------------
# User Switching
--------------------------------------------------------------*/

	if ( !class_exists('user_switching') ) :

		class user_switching {

			public static $application = 'WordPress/User Switching';

			public function init_hooks() {
				add_filter( 'user_has_cap',                    	array( $this, 'filter_user_has_cap' ), 10, 4 );
				add_filter( 'map_meta_cap',                    	array( $this, 'filter_map_meta_cap' ), 10, 4 );
				add_filter( 'user_row_actions',                	array( $this, 'filter_user_row_actions' ), 10, 2 );
				add_action( 'plugins_loaded',                 	array( $this, 'action_plugins_loaded' ), 1 );
				add_action( 'init',                            	array( $this, 'action_init' ) );
				add_action( 'all_admin_notices',               	array( $this, 'action_admin_notices' ), 1 );
				add_action( 'wp_logout',                      	'user_switching_clear_olduser_cookie' );
				add_action( 'wp_login',                        	'user_switching_clear_olduser_cookie' );
				add_filter( 'ms_user_row_actions',             	array( $this, 'filter_user_row_actions' ), 10, 2 );
				add_filter( 'login_message',                   	array( $this, 'filter_login_message' ), 1 );
				add_filter( 'removable_query_args',            	array( $this, 'filter_removable_query_args' ) );
				add_action( 'wp_meta',                         	array( $this, 'action_wp_meta' ) );
				add_action( 'wp_footer',                      	array( $this, 'action_wp_footer' ) );
				add_action( 'personal_options',                	array( $this, 'action_personal_options' ) );
				add_action( 'admin_bar_menu',                  	array( $this, 'action_admin_bar_menu' ), 11 );
				add_action( 'bp_member_header_actions',        	array( $this, 'action_bp_button' ), 11 );
				add_action( 'bp_directory_members_actions',    	array( $this, 'action_bp_button' ), 11 );
				add_action( 'bbp_template_after_user_details', 	array( $this, 'action_bbpress_button' ) );
				add_action( 'switch_to_user',                  	array( $this, 'forget_woocommerce_session' ) );
				add_action( 'switch_back_user',                	array( $this, 'forget_woocommerce_session' ) );
			}

			public function action_plugins_loaded() {
				if ( ! defined( 'USER_SWITCHING_COOKIE' ) ) {
					define( 'USER_SWITCHING_COOKIE', 'wordpress_user_sw_' . COOKIEHASH );
				}

				if ( ! defined( 'USER_SWITCHING_SECURE_COOKIE' ) ) {
					define( 'USER_SWITCHING_SECURE_COOKIE', 'wordpress_user_sw_secure_' . COOKIEHASH );
				}

				if ( ! defined( 'USER_SWITCHING_OLDUSER_COOKIE' ) ) {
					define( 'USER_SWITCHING_OLDUSER_COOKIE', 'wordpress_user_sw_olduser_' . COOKIEHASH );
				}
			}

			public function action_personal_options( WP_User $user ) {
				$link = self::maybe_switch_url( $user );

				if ( ! $link ) {
					return;
				}

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

				if ( ! $current ) {
					return false;
				}

				return ( intval( $current['expiration'] ) - time() > $cookie_life );
			}

			public function action_init() {
				load_plugin_textdomain( 'user-switching', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

				if ( ! isset( $_REQUEST['action'] ) ) {
					return;
				}

				$current_user = ( is_user_logged_in() ) ? wp_get_current_user() : null;

				switch ( $_REQUEST['action'] ) {

					case 'switch_to_user':
						if ( isset( $_REQUEST['user_id'] ) ) {
							$user_id = absint( $_REQUEST['user_id'] );
						} else {
							$user_id = 0;
						}

						if ( ! current_user_can( 'switch_to_user', $user_id ) ) {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 403 );
						}

						check_admin_referer( "switch_to_user_{$user_id}" );

						$user = switch_to_user( $user_id, self::remember() );
						if ( $user ) {
							$redirect_to = self::get_redirect( $user, $current_user );

							$args = array(
								'user_switched' => 'true',
							);

							if ( $redirect_to ) {
								wp_safe_redirect( add_query_arg( $args, $redirect_to ), 302, self::$application );
							} elseif ( ! current_user_can( 'read' ) ) {
								wp_safe_redirect( add_query_arg( $args, home_url() ), 302, self::$application );
							} else {
								wp_safe_redirect( add_query_arg( $args, admin_url() ), 302, self::$application );
							}
							exit;
						} else {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 404 );
						}
						break;

					case 'switch_to_olduser':
						$old_user = self::get_old_user();
						if ( ! $old_user ) {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 400 );
						}

						if ( ! self::authenticate_old_user( $old_user ) ) {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 403 );
						}

						check_admin_referer( "switch_to_olduser_{$old_user->ID}" );

						if ( switch_to_user( $old_user->ID, self::remember(), false ) ) {

							if ( ! empty( $_REQUEST['interim-login'] ) && function_exists( 'login_header' ) ) {
								$GLOBALS['interim_login'] = 'success'; // @codingStandardsIgnoreLine
								login_header( '', '' );
								exit;
							}

							$redirect_to = self::get_redirect( $old_user, $current_user );
							$args        = array(
								'user_switched' => 'true',
								'switched_back' => 'true',
							);

							wp_safe_redirect( add_query_arg( $args, admin_url( 'users.php' ) ), 302, self::$application );
							exit;
						} else {
							wp_die( esc_html__( 'Could not switch users.', 'user-switching' ), 404 );
						}
						break;


					case 'switch_off':
						if ( ! $current_user || ! current_user_can( 'switch_off' ) ) {
							wp_die( esc_html__( 'Could not switch off.', 'user-switching' ) );
						}

						check_admin_referer( "switch_off_{$current_user->ID}" );

						if ( switch_off_user() ) {
							$redirect_to = self::get_redirect( null, $current_user );
							$args        = array(
								'switched_off' => 'true',
							);

							if ( $redirect_to ) {
								wp_safe_redirect( add_query_arg( $args, $redirect_to ), 302, self::$application );
							} else {
								wp_safe_redirect( add_query_arg( $args, home_url() ), 302, self::$application );
							}
							exit;
						} else {
							wp_die( esc_html__( 'Could not switch off.', 'user-switching' ) );
						}
						break;
				}
			}

			protected static function get_redirect( WP_User $new_user = null, WP_User $old_user = null ) {
				if ( ! empty( $_REQUEST['redirect_to'] ) ) {
					$redirect_to           = self::remove_query_args( wp_unslash( $_REQUEST['redirect_to'] ) );
					$requested_redirect_to = wp_unslash( $_REQUEST['redirect_to'] );
				} else {
					$redirect_to           = '';
					$requested_redirect_to = '';
				}

				if ( ! $new_user ) {
					$redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $old_user );
				} else {
					$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $new_user );
				}

				return $redirect_to;
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

					?>
					<div id="user_switching" class="updated notice is-dismissible">
						<?php
							if ( $lang_attr ) {
								printf(
									'<p lang="%s">',
									esc_attr( $lang_attr )
								);
							} else {
								echo '<p>';
							}
						?>
						<span class="dashicons dashicons-admin-users" style="color:#56c234" aria-hidden="true"></span>
						<?php
							$message       = '';
							$just_switched = isset( $_GET['user_switched'] );
							if ( $just_switched ) {
								$message = esc_html( sprintf(
									__( 'Switched to %1$s (%2$s).', 'user-switching' ),
									$user->display_name,
									$user->user_login
								) );
							}
							$switch_back_url = add_query_arg( array(
								'redirect_to' => urlencode( self::current_url() ),
							), self::switch_back_url( $old_user ) );

							$message .= sprintf(
								' <a href="%s">%s</a>.',
								esc_url( $switch_back_url ),
								esc_html( sprintf(
									__( 'Switch Back -> %1$s', 'user-switching' ),
									$old_user->display_name,
									$old_user->user_login
								) )
							);

							$message = apply_filters( 'user_switching_switched_message', $message, $user, $old_user, $switch_back_url, $just_switched );

							echo wp_kses( $message, array(
								'a' => array(
									'href' => array(),
								),
							) );
						?>
						</p>
					</div>
					<?php
					if ( $switched_locale ) {
						restore_previous_locale();
					}
				} elseif ( isset( $_GET['user_switched'] ) ) {
					?>
					<div id="user_switching" class="updated notice is-dismissible">
						<p>
						<?php
							if ( isset( $_GET['switched_back'] ) ) {
								echo esc_html( sprintf(
									__( 'Switched back to %1$s (%2$s).', 'user-switching' ),
									$user->display_name,
									$user->user_login
								) );
							} else {
								echo esc_html( sprintf(
									__( 'Switched to %1$s (%2$s).', 'user-switching' ),
									$user->display_name,
									$user->user_login
								) );
							}
						?>
						</p>
					</div>
					<?php
				}
			}

			public static function get_old_user() {
				$cookie = user_switching_get_olduser_cookie();
				if ( ! empty( $cookie ) ) {
					$old_user_id = wp_validate_auth_cookie( $cookie, 'logged_in' );

					if ( $old_user_id ) {
						return get_userdata( $old_user_id );
					}
				}
				return false;
			}

			public static function authenticate_old_user( WP_User $user ) {
				$cookie = user_switching_get_auth_cookie();
				if ( ! empty( $cookie ) ) {
					if ( self::secure_auth_cookie() ) {
						$scheme = 'secure_auth';
					} else {
						$scheme = 'auth';
					}

					$old_user_id = wp_validate_auth_cookie( end( $cookie ), $scheme );

					if ( $old_user_id ) {
						return ( $user->ID === $old_user_id );
					}
				}
				return false;
			}

			public function action_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
				if ( ! is_admin_bar_showing() ) {
					return;
				}

				if ( method_exists( $wp_admin_bar, 'get_node' ) ) {
					if ( $wp_admin_bar->get_node( 'user-actions' ) ) {
						$parent = 'user-actions';
					} else {
						return;
					}
				} elseif ( get_option( 'show_avatars' ) ) {
					$parent = 'my-account-with-avatar';
				} else {
					$parent = 'my-account';
				}

				$old_user = self::get_old_user();

				if ( $old_user ) {
					$wp_admin_bar->add_node( array(
						'parent' => $parent,
						'id'     => 'switch-back',
						'title'  => esc_html( sprintf(
							__( 'Switch Back -> %1$s', 'user-switching' ),
							$old_user->display_name,
							$old_user->user_login
						) ),
						'href'   => add_query_arg( array(
							'redirect_to' => urlencode( self::current_url() ),
						), self::switch_back_url( $old_user ) ),
					) );
				}

				if ( current_user_can( 'switch_off' ) ) {
					$url = self::switch_off_url( wp_get_current_user() );
					if ( ! is_admin() ) {
						$url = add_query_arg( array(
							'redirect_to' => urlencode( self::current_url() ),
						), $url );
					}

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
							'title'  => esc_html( sprintf(
								__( 'Switch Back -> %1$s', 'user-switching' ),
								$old_user->display_name,
								$old_user->user_login
							) ),
							'href'   => add_query_arg( array(
								'redirect_to' => urlencode( self::current_url() ),
							), self::switch_back_url( $old_user ) ),
						) );
					} elseif ( current_user_can( 'switch_to_user', get_queried_object_id() ) ) {
						$wp_admin_bar->add_node( array(
							'parent' => 'edit',
							'id'     => 'author-switch-to',
							'title'  => esc_html__( 'Switch&nbsp;To', 'user-switching' ),
							'href'   => add_query_arg( array(
								'redirect_to' => urlencode( self::current_url() ),
							), self::switch_to_url( get_queried_object() ) ),
						) );
					}
				}
			}

			public function action_wp_meta() {
				$old_user = self::get_old_user();

				if ( $old_user instanceof WP_User ) {
					$link = sprintf(
						__( 'Switch Back -> %1$s', 'user-switching' ),
						$old_user->display_name,
						$old_user->user_login
					);
					$url = add_query_arg( array(
						'redirect_to' => urlencode( self::current_url() ),
					), self::switch_back_url( $old_user ) );
					printf(
						'<li id="user_switching_switch_on"><a href="%s">%s</a></li>',
						esc_url( $url ),
						esc_html( $link )
					);
				}
			}

			public function action_wp_footer() {
				if ( is_admin_bar_showing() || did_action( 'wp_meta' ) ) {
					return;
				}

				if ( ! apply_filters( 'user_switching_in_footer', true ) ) {
					return;
				}

				$old_user = self::get_old_user();
				$new_user = wp_get_current_user();

				if ( $old_user instanceof WP_User ) {
					$link = sprintf(
						__( 'Switch Back', 'user-switching' ),
						$old_user->display_name,
						$old_user->user_login
					);
					$url = add_query_arg( array(
						'redirect_to' => urlencode( self::current_url() ),
					), self::switch_back_url( $old_user ) );
					printf(
						'<p id="user_switching_switch_on">Viewing as '.esc_html($new_user->user_firstname).' '.esc_html($new_user->user_lastname).' [ '.esc_html($new_user->user_login).' // '.$new_user->roles[1].' ]&nbsp;&nbsp;&nbsp;·&nbsp;&nbsp;&nbsp;<a href="%s">%s</a></p>',
						esc_url( $url ),
						esc_html( $link )
					);
				}
			}

			public function filter_login_message( $message ) {
				$old_user = self::get_old_user();

				if ( $old_user instanceof WP_User ) {
					$link = sprintf(
						__( 'Switch Back -> %1$s', 'user-switching' ),
						$old_user->display_name,
						$old_user->user_login
					);
					$url = self::switch_back_url( $old_user );

					if ( ! empty( $_REQUEST['interim-login'] ) ) {
						$url = add_query_arg( array(
							'interim-login' => '1',
						), $url );
					} elseif ( ! empty( $_REQUEST['redirect_to'] ) ) {
						$url = add_query_arg( array(
							'redirect_to' => urlencode( wp_unslash( $_REQUEST['redirect_to'] ) ),
						), $url );
					}

					$message .= '<p class="message" id="user_switching_switch_on">';
					$message .= '<span class="dashicons dashicons-admin-users" style="color:#56c234" aria-hidden="true"></span> ';
					$message .= sprintf(
						'<a href="%1$s" onclick="window.location.href=\'%1$s\';return false;">%2$s</a>',
						esc_url( $url ),
						esc_html( $link )
					);
					$message .= '</p>';
				}

				return $message;
			}

			public function filter_user_row_actions( array $actions, WP_User $user ) {
				$link = self::maybe_switch_url( $user );

				if ( ! $link ) {
					return $actions;
				}

				$actions['switch_to_user'] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $link ),
					esc_html__( 'Switch&nbsp;To', 'user-switching' )
				);

				return $actions;
			}

			public function action_bp_button() {
				$user = null;

				if ( bp_is_user() ) {
					$user = get_userdata( bp_displayed_user_id() );
				} elseif ( bp_is_members_directory() ) {
					$user = get_userdata( bp_get_member_user_id() );
				}

				if ( ! $user ) {
					return;
				}

				$link = self::maybe_switch_url( $user );

				if ( ! $link ) {
					return;
				}

				$link = add_query_arg( array(
					'redirect_to' => urlencode( bp_core_get_user_domain( $user->ID ) ),
				), $link );

				$components = array_keys( buddypress()->active_components );

				echo bp_get_button( array(
					'id'         => 'user_switching',
					'component'  => reset( $components ),
					'link_href'  => esc_url( $link ),
					'link_text'  => esc_html__( 'Switch&nbsp;To', 'user-switching' ),
					'wrapper_id' => 'user_switching_switch_to',
				) );
			}

			public function action_bbpress_button() {
				$user = get_userdata( bbp_get_user_id() );

				if ( ! $user ) {
					return;
				}

				$link = self::maybe_switch_url( $user );

				if ( ! $link ) {
					return;
				}

				$link = add_query_arg( array(
					'redirect_to' => urlencode( bbp_get_user_profile_url( $user->ID ) ),
				), $link );

				echo '<ul id="user_switching_switch_to">';
				printf(
					'<li><a href="%s">%s</a></li>',
					esc_url( $link ),
					esc_html__( 'Switch&nbsp;To', 'user-switching' )
				);
				echo '</ul>';
			}

			public function filter_removable_query_args( array $args ) {
				return array_merge( $args, array(
					'user_switched',
					'switched_off',
					'switched_back',
				) );
			}

			public static function maybe_switch_url( WP_User $user ) {
				$old_user = self::get_old_user();

				if ( $old_user && ( $old_user->ID === $user->ID ) ) {
					return self::switch_back_url( $old_user );
				} elseif ( current_user_can( 'switch_to_user', $user->ID ) ) {
					return self::switch_to_url( $user );
				} else {
					return false;
				}
			}

			public static function switch_to_url( WP_User $user ) {
				return wp_nonce_url( add_query_arg( array(
					'action'  => 'switch_to_user',
					'user_id' => $user->ID,
					'nr'      => 1,
				), wp_login_url() ), "switch_to_user_{$user->ID}" );
			}

			public static function switch_back_url( WP_User $user ) {
				return wp_nonce_url( add_query_arg( array(
					'action' => 'switch_to_olduser',
					'nr'     => 1,
				), wp_login_url() ), "switch_to_olduser_{$user->ID}" );
			}

			public static function switch_off_url( WP_User $user ) {
				return wp_nonce_url( add_query_arg( array(
					'action' => 'switch_off',
					'nr'     => 1,
				), wp_login_url() ), "switch_off_{$user->ID}" );
			}

			public static function current_url() {
				return ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			}

			public static function remove_query_args( $url ) {
				if ( function_exists( 'wp_removable_query_args' ) ) {
					$url = remove_query_arg( wp_removable_query_args(), $url );
				}

				return $url;
			}

			public static function secure_olduser_cookie() {
				return ( is_ssl() && ( 'https' === parse_url( home_url(), PHP_URL_SCHEME ) ) );
			}

			public static function secure_auth_cookie() {
				return ( is_ssl() && ( 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME ) ) );
			}

			public function forget_woocommerce_session() {
				if ( ! function_exists( 'WC' ) ) {
					return;
				}

				$wc = WC();

				if ( ! property_exists( $wc, 'session' ) ) {
					return;
				}

				if ( ! method_exists( $wc->session, 'forget_session' ) ) {
					return;
				}

				$wc->session->forget_session();
			}

			public function filter_user_has_cap( array $user_caps, array $required_caps, array $args, WP_User $user ) {
				if ( 'switch_to_user' === $args[0] ) {
					if ( empty( $args[2] ) ) {
						$user_caps['switch_to_user'] = false;
						return $user_caps;
					}
					if ( array_key_exists( 'switch_users', $user_caps ) ) {
						$user_caps['switch_to_user'] = $user_caps['switch_users'];
						return $user_caps;
					}

					$user_caps['switch_to_user'] = ( user_can( $user->ID, 'edit_user', $args[2] ) && ( $args[2] !== $user->ID ) );
				} elseif ( 'switch_off' === $args[0] ) {
					if ( array_key_exists( 'switch_users', $user_caps ) ) {
						$user_caps['switch_off'] = $user_caps['switch_users'];
						return $user_caps;
					}

					$user_caps['switch_off'] = user_can( $user->ID, 'edit_users' );
				}
				return $user_caps;
			}

			public function filter_map_meta_cap( array $required_caps, $cap, $user_id, array $args ) {
				if ( 'switch_to_user' === $cap ) {
					if ( empty( $args[0] ) || $args[0] === $user_id ) {
						$required_caps[] = 'do_not_allow';
					}
				}
				return $required_caps;
			}

			public static function get_instance() {
				static $instance;

				if ( ! isset( $instance ) ) {
					$instance = new user_switching();
				}
				return $instance;
			}

			final private function __construct() {}

		}

		if ( ! function_exists( 'user_switching_set_olduser_cookie' ) ) {
			function user_switching_set_olduser_cookie( $old_user_id, $pop = false, $token = '' ) {
				$secure_auth_cookie    = user_switching::secure_auth_cookie();
				$secure_olduser_cookie = user_switching::secure_olduser_cookie();
				$expiration            = 2; // 2 days
				$auth_cookie           = user_switching_get_auth_cookie();
				$olduser_cookie        = wp_generate_auth_cookie( $old_user_id, $expiration, 'logged_in', $token );

				if ( $secure_auth_cookie ) {
					$auth_cookie_name = USER_SWITCHING_SECURE_COOKIE;
					$scheme           = 'secure_auth';
				} else {
					$auth_cookie_name = USER_SWITCHING_COOKIE;
					$scheme           = 'auth';
				}

				if ( $pop ) {
					array_pop( $auth_cookie );
				} else {
					array_push( $auth_cookie, wp_generate_auth_cookie( $old_user_id, $expiration, $scheme, $token ) );
				}

				$auth_cookie = json_encode( $auth_cookie );

				if ( false === $auth_cookie ) {
					return;
				}

				do_action( 'set_user_switching_cookie', $auth_cookie, $expiration, $old_user_id, $scheme, $token );

				$scheme = 'logged_in';

				do_action( 'set_olduser_cookie', $olduser_cookie, $expiration, $old_user_id, $scheme, $token );

				if ( ! apply_filters( 'user_switching_send_auth_cookies', true ) ) {
					return;
				}

				setcookie( $auth_cookie_name, $auth_cookie, $expiration, SITECOOKIEPATH, COOKIE_DOMAIN, $secure_auth_cookie, true );
				setcookie( USER_SWITCHING_OLDUSER_COOKIE, $olduser_cookie, $expiration, COOKIEPATH, COOKIE_DOMAIN, $secure_olduser_cookie, true );
			}
		}

		if ( ! function_exists( 'user_switching_clear_olduser_cookie' ) ) {

			function user_switching_clear_olduser_cookie( $clear_all = true ) {
				$auth_cookie = user_switching_get_auth_cookie();
				if ( ! empty( $auth_cookie ) ) {
					array_pop( $auth_cookie );
				}
				if ( $clear_all || empty( $auth_cookie ) ) {
					do_action( 'clear_olduser_cookie' );

					if ( ! apply_filters( 'user_switching_send_auth_cookies', true ) ) {
						return;
					}

					$expire = 365;
					setcookie( USER_SWITCHING_COOKIE,         ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN );
					setcookie( USER_SWITCHING_SECURE_COOKIE,  ' ', $expire, SITECOOKIEPATH, COOKIE_DOMAIN );
					setcookie( USER_SWITCHING_OLDUSER_COOKIE, ' ', $expire, COOKIEPATH, COOKIE_DOMAIN );

				} else {
					if ( user_switching::secure_auth_cookie() ) {
						$scheme = 'secure_auth';
					} else {
						$scheme = 'auth';
					}

					$old_cookie = end( $auth_cookie );

					$old_user_id = wp_validate_auth_cookie( $old_cookie, $scheme );
					if ( $old_user_id ) {
						$parts = wp_parse_auth_cookie( $old_cookie, $scheme );

						if ( false !== $parts ) {
							user_switching_set_olduser_cookie( $old_user_id, true, $parts['token'] );
						}
					}
				}
			}
		}

		if ( ! function_exists( 'user_switching_get_olduser_cookie' ) ) {
			function user_switching_get_olduser_cookie() {
				if ( isset( $_COOKIE[ 'USER_SWITCHING_OLDUSER_COOKIE' ] ) ) {
					return wp_unslash( $_COOKIE[ 'USER_SWITCHING_OLDUSER_COOKIE' ] );
				} else {
					return false;
				}
			}
		}

		if ( ! function_exists( 'user_switching_get_auth_cookie' ) ) {

			function user_switching_get_auth_cookie() {
				if ( user_switching::secure_auth_cookie() ) {
					$auth_cookie_name = USER_SWITCHING_SECURE_COOKIE;
				} else {
					$auth_cookie_name = USER_SWITCHING_COOKIE;
				}

				if ( isset( $_COOKIE[ $auth_cookie_name ] ) && is_string( $_COOKIE[ $auth_cookie_name ] ) ) {
					$cookie = json_decode( wp_unslash( $_COOKIE[ $auth_cookie_name ] ) );
				}

				if ( ! isset( $cookie ) || ! is_array( $cookie ) ) {
					$cookie = array();
				}

				return $cookie;
			}
		}

		if ( ! function_exists( 'switch_to_user' ) ) {

			function switch_to_user( $user_id, $remember = false, $set_old_user = true ) {
				$user = get_userdata( $user_id );

				if ( ! $user ) {
					return false;
				}

				$old_user_id  = ( is_user_logged_in() ) ? get_current_user_id() : false;
				$old_token    = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';
				$auth_cookies = user_switching_get_auth_cookie();
				$auth_cookie  = end( $auth_cookies );
				$cookie_parts = $auth_cookie ? wp_parse_auth_cookie( $auth_cookie ) : false;

				if ( $set_old_user && $old_user_id ) {
					$new_token = '';
					user_switching_set_olduser_cookie( $old_user_id, false, $old_token );
				} else {
					$new_token = ( $cookie_parts && isset( $cookie_parts['token'] ) ) ? $cookie_parts['token'] : '';
					user_switching_clear_olduser_cookie( false );
				}

				$session_filter = function( array $session, $user_id ) use ( $old_user_id, $old_token ) {
					$session['switched_from_id']      = $old_user_id;
					$session['switched_from_session'] = $old_token;
					return $session;
				};

				add_filter( 'attach_session_information', $session_filter, 99, 2 );

				wp_clear_auth_cookie();
				wp_set_auth_cookie( $user_id, $remember, '', $new_token );
				wp_set_current_user( $user_id );

				remove_filter( 'attach_session_information', $session_filter, 99 );

				if ( $set_old_user ) {
					do_action( 'switch_to_user', $user_id, $old_user_id, $new_token, $old_token );
				} else {
					do_action( 'switch_back_user', $user_id, $old_user_id, $new_token, $old_token );
				}

				if ( $old_token && $old_user_id && ! $set_old_user ) {
					$manager = WP_Session_Tokens::get_instance( $old_user_id );
					$manager->destroy( $old_token );
				}

				return $user;
			}
		}

		if ( ! function_exists( 'switch_off_user' ) ) {
			function switch_off_user() {
				$old_user_id = get_current_user_id();

				if ( ! $old_user_id ) {
					return false;
				}

				$old_token = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';

				user_switching_set_olduser_cookie( $old_user_id, false, $old_token );
				wp_clear_auth_cookie();
				wp_set_current_user( 0 );

				do_action( 'switch_off_user', $old_user_id, $old_token );

				return true;
			}
		}

		if ( ! function_exists( 'current_user_switched' ) ) {
			function current_user_switched() {
				if ( ! is_user_logged_in() ) {
					return false;
				}
				return user_switching::get_old_user();
			}
		}

		$GLOBALS['user_switching'] = user_switching::get_instance();
		$GLOBALS['user_switching']->init_hooks();


		/*--------------------------------------------------------------
		# Adding Nonces
		--------------------------------------------------------------*/

		class WP_Filterable_Scripts extends WP_Scripts {

			private $type_attr;

			public function __construct() {
				parent::__construct();

				if ( function_exists( 'is_admin' ) && ! is_admin() && function_exists( 'current_theme_supports' ) && ! current_theme_supports( 'html5', 'script' ) ) {
					$this->type_attr = " type='text/javascript'";
				}

				if ( $GLOBALS['wp_scripts'] instanceof WP_Scripts ) {
					$missing_scripts = array_diff_key( $GLOBALS['wp_scripts']->registered, $this->registered );
					foreach ( $missing_scripts as $mscript ) {
						$this->registered[ $mscript->handle ] = $mscript;
					}
				}
			}

			public function print_extra_script( $handle, $echo = true ) {
				$output = $this->get_data( $handle, 'data' );
				if ( ! $output ) { return; }

				if ( ! $echo ) { return $output; }

				$tag = sprintf( "<script%s id='%s-js-extra'>\n", $this->type_attr, esc_attr( $handle ) );

				$tag = apply_filters( 'battleplan_csp_localized_scripts', $tag, $handle );

				// CDATA is not needed for HTML 5.
				if ( $this->type_attr ) {
					$tag .= "/* <![CDATA[ */\n";
				}

				$tag .= "$output\n";
				if ( $this->type_attr ) {
					$tag .= "/* ]]> */\n";
				}
				$tag .= "</script>\n";

				echo $tag;
				return true;
			}
		}
	endif;

?>