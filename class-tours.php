<?php
/**
 * @package Tours
 */

/**
 * The class containting all hooks for the Tour.
 */
class Tours {
	/**
	 * Register all hooks.
	 */
	public static function register_hooks() {
		$class = get_called_class();
		add_action( 'admin_enqueue_scripts', array( $class, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $class, 'enqueue_scripts' ) );
		add_action( 'init', array( $class, 'register_post_type' ) );
		add_action( 'init', array( $class, 'register_block_type' ) );
		add_action( 'rest_api_init', array( $class, 'rest_api_init' ) );
		add_filter( 'post_row_actions', array( $class, 'post_row_actions' ), 10, 2 );
		add_filter( 'tour_row_actions', array( $class, 'tour_row_actions' ) );
		add_filter( 'get_the_excerpt', array( $class, 'get_the_excerpt' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $class, 'wp_insert_post_data' ), 10, 2 );
		add_action( 'postbox_classes_tour_tour-json', array( $class, 'add_closed_css_class' ) );
		add_action( 'admin_init', array( $class, 'admin_init' ) );
		add_action( 'edit_form_after_editor', array( $class, 'edit_form_after_editor' ) );
		add_filter( 'tour_list', array( $class, 'tour_list' ) );
		add_shortcode( 'tour_list', array( $class, 'show_tour_list' ) );
		add_action( 'admin_menu', array( $class, 'add_admin_menu' ) );
		add_action( 'admin_post_tour_import_library_tour', array( $class, 'import_library_tour' ) );
		add_action( 'admin_post_tour_export_package', array( $class, 'export_tour_package' ) );
		add_action( 'admin_post_tour_submit_github_fix', array( $class, 'submit_github_fix' ) );
		add_action( 'wp_footer', array( $class, 'output_tour_button' ) );
		add_action( 'admin_footer', array( $class, 'output_tour_button' ) );
		add_action( 'gp_footer', array( $class, 'output_tour_button' ) );
		add_action( 'show_user_profile', array( $class, 'show_user_profile' ) );
		add_action( 'wp_before_admin_bar_render', array( $class, 'add_tours_menu_to_masterbar' ) );
	}

	/**
	 * Enqueue the scripts.
	 */
	public static function enqueue_scripts() {
		static $once = false;
		if ( $once ) {
			return;
		}
		$once = true;

		$tours = apply_filters( 'tour_list', array() );
		if ( empty( $tours ) ) {
			return;
		}

		wp_register_style( 'driver-js', plugins_url( 'assets/css/driver-js.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/driver-js.css' ) );
		wp_register_style( 'tour-css', plugins_url( 'assets/css/style.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/style.css' ) );
		wp_enqueue_style( 'driver-js' );
		wp_enqueue_style( 'tour-css' );
		wp_enqueue_script( 'driver-js', plugins_url( 'assets/js/driver-js.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/js/driver-js.js' ), array( 'in_footer' => true ) );
		wp_register_script( 'tour', plugins_url( 'assets/js/tour.js', __FILE__ ), array( 'driver-js' ), filemtime( __DIR__ . '/assets/js/tour.js' ), false );
		wp_enqueue_script( 'tour' );
		wp_localize_script(
			'tour',
			'tour_plugin',
			array(
				'tours'    => $tours,
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'rest_url' => rest_url(),
				'progress' => get_user_option( 'tour-progress', get_current_user_id() ),
			)
		);

		if ( current_user_can( 'edit_others_posts' ) ) {
			wp_register_script( 'tour-step-editor', plugins_url( 'assets/js/tour-step-editor.js', __FILE__ ), array( 'driver-js' ), filemtime( __DIR__ . '/assets/js/tour-step-editor.js' ), array( 'in_footer' => true ) );
			wp_enqueue_script( 'tour-step-editor' );
		}
	}

	/**
	 * Ensure that the post_content is properly decoded to JSON.
	 *
	 * @param      string $post_content  The post content.
	 *
	 * @return     array  The decoded JSON.
	 */
	private static function json_decode( $post_content ) {
		return json_decode( wp_unslash( str_replace( "\\\\'", "'", $post_content ) ), true );
	}

	/**
	 * Register the post type.
	 */
	public static function register_post_type() {
		register_post_type(
			'tour',
			array(
				'labels'            => array(
					'name'          => __( 'Tours', 'tour' ),
					'singular_name' => __( 'Tour', 'tour' ),
					'add_new'       => __( 'Create New', 'tour' ),
					'add_new_item'  => __( 'Create New Tour', 'tour' ),
					'edit_item'     => __( 'Edit Tour', 'tour' ),
					'new_item'      => __( 'New Tour', 'tour' ),
					'all_items'     => __( 'All Tours', 'tour' ),
					'view_item'     => __( 'View Tour', 'tour' ),
					'search_items'  => __( 'Search Tours', 'tour' ),
					'not_found'     => __( 'No tours found.', 'tour' ),

				),

				'public'            => false,
				'show_ui'           => true,
				'show_in_nav_menus' => true,
				'show_in_menu'      => 'tour',
				'supports'          => array( 'title', 'revisions' ),
			)
		);
	}

	/**
	 * Initialize the REST API endpoints.
	 */
	public static function rest_api_init() {
		register_rest_route(
			'tour/v1',
			'save-progress',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					if ( ! is_user_logged_in() ) {
						return array( 'success' => 'logged-out' );
					}
					$step = $request->get_param( 'step' );
					$tour_id = $request->get_param( 'tour' );

					$tour = get_post( $tour_id );
					if ( ! $tour || is_wp_error( $tour ) || 'tour' !== $tour->post_type ) {
						return array(
							'success' => false,
						);
					}

					$tour_progress = get_user_option( 'tour-progress', get_current_user_id() );
					if ( ! $tour_progress ) {
						$tour_progress = array();
					}
					if ( $step < 0 || ! is_numeric( $step ) ) {
						unset( $tour_progress[ $tour_id ] );
					} else {
						$tour_progress[ $tour_id ] = $step;
					}
					update_user_option( get_current_user_id(), 'tour-progress', $tour_progress );
					return array(
						'success' => true,
					);
				},
				'permission_callback' => 'is_user_logged_in',
			)
		);

		register_rest_route(
			'tour/v1',
			'report-missing',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					$step = $request->get_param( 'step' );
					$tour_id = $request->get_param( 'tour' );
					$selector = $request->get_param( 'selector' );
					$url = $request->get_param( 'url' );

					$tour = get_post( $tour_id );
					if ( ! $tour || is_wp_error( $tour ) || 'tour' !== $tour->post_type ) {
						return array(
							'success' => false,
						);
					}

					if ( $tour ) {
						$missing_steps = get_post_meta( $tour, 'missing_steps', true );
						if ( ! $missing_steps ) {
							$missing_steps = array();
						}
						if ( ! isset( $missing_steps[ $step ] ) ) {
							$missing_steps[ $step ] = array();
						}
						if ( ! isset( $missing_steps[ $step ][ $url ] ) ) {
							$missing_steps[ $step ][ $url ] = array();
						}
						if ( ! isset( $missing_steps[ $step ][ $url ][ $selector ] ) ) {
							$missing_steps[ $step ][ $url ][ $selector ] = 0;
						}
						$missing_steps[ $step ][ $url ][ $selector ] += 1;
						update_post_meta( $tour, 'missing_steps', $missing_steps );
						return array(
							'success' => true,
						);
					}
					return array(
						'success' => false,
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			)
		);

		register_rest_route(
			'tour/v1',
			'save',
			array(
				'methods'             => 'POST',
				'callback'            => function ( WP_REST_Request $request ) {
					if ( ! current_user_can( 'edit_others_posts' ) ) {
						return array(
							'success' => false,
						);
					}
					$steps = json_decode( $request->get_param( 'steps' ), true );
					if ( ! isset( $steps[0]['title'] ) ) {
						return array(
							'success' => false,
						);
					}
					if ( ! isset( $steps[1]['popover'] ) ) {
						return array(
							'success' => false,
						);
					}
					$tour_id = $request->get_param( 'tour' );

					$tour = get_post( $tour_id );
					if ( ! $tour || is_wp_error( $tour ) || 'tour' !== $tour->post_type ) {
						return array(
							'success' => false,
						);
					}

					if ( $tour ) {
						wp_update_post(
							array(
								'ID'           => $tour_id,
								'post_content' => wp_json_encode( $steps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
								'post_status'  => 'publish',
							),
							true
						);
					}

					return $tour_id;
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
			)
		);
	}

	/**
	 * Register post row actions.
	 *
	 * @param      array   $actions  The actions.
	 * @param      WP_Post $post     The post.
	 *
	 * @return     array  The modified actions.
	 */
	public static function post_row_actions( $actions, $post ) {
		if ( 'tour' !== $post->post_type || 'trash' === $post->post_status ) {
			return $actions;
		}

		$tour_steps = self::json_decode( $post->post_content );
		if ( empty( $tour_steps[0]['title'] ) ) {
			return $actions;
		}

		$caption = __( 'Add more steps', 'tour' );

		$actions['add-more-steps'] = '<a href="' . get_permalink( $post->ID ) . '" data-tour-id="' . esc_attr( $post->ID ) . '" data-add-more-steps-text="' . esc_attr( $caption ) . '" data-finish-tour-creation-text="' . esc_attr( __( 'Finish tour creating the tour', ' tour' ) ) . '" title="' . esc_attr( $caption ) . '">' . esc_html( $caption ) . '</a>';
		$actions['export-package'] = '<a href="' . esc_url( self::get_export_tour_package_url( $post->ID ) ) . '">' . esc_html__( 'Export package', 'tour' ) . '</a>';
		return $actions;
	}

	/**
	 * Add a row action to the tour post type.
	 *
	 * @param      array $actions  The actions.
	 *
	 * @return     array The modified actions.
	 */
	public static function tour_row_actions( $actions ) {
		$actions[] = 'Add more steps';
		return $actions;
	}

	/**
	 * Get a custom excerpt for the tour post type.
	 *
	 * @param      string  $excerpt  The excerpt.
	 * @param      WP_Post $post     The post.
	 *
	 * @return     string  The excerpt.
	 */
	public static function get_the_excerpt( $excerpt, $post = null ) {
		if ( get_post_type( $post ) === 'tour' ) {
			$steps = self::json_decode( $post->post_content );
			if ( $steps ) {
				$c = ( count( $steps ) - 1 );
				return sprintf(
					// translators: %d is the number of steps.
					_n( '%d step', '%d steps', $c, 'tour' ),
					$c
				);
			}
			return '';
		}
		return $excerpt;
	}

	/**
	 * Store the tour as a JSON in the post_content.
	 *
	 * @param      array $data     The data.
	 * @param      array $postarr  The postarr.
	 *
	 * @return     array  The modified data with the tour as JSON.
	 */
	public static function wp_insert_post_data( $data, $postarr ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-post_' . $postarr['ID'] ) ) {
			return $data;
		}

		if ( ! isset( $_POST['color'] ) || 'tour' !== $data['post_type'] ) {
			return $data;
		}

		$data['post_title'] = sanitize_text_field( $_POST['post_title'] );

		$tour = array(
			array(
				'color' => sanitize_text_field( $_POST['color'] ),
				'title' => $data['post_title'],
			),
		);

		if ( isset( $_POST['override_json'] ) ) {
			$data['post_content'] = wp_kses_post( $_POST['json'] );
			return $data;
		}

		if ( isset( $_POST['order'] ) ) {
			foreach ( $_POST['order'] as $i ) {
				if ( ! is_int( $i ) || $i < 0 ) {
					continue;
				}
				if ( ! isset( $_POST['tour'][ $i ] ) ) {
					continue;
				}

				if ( ! isset( $_POST['tour'][ $i ]['element'] ) || '' === trim( $step['element'] ) ) {
					continue;
				}

				if ( ! isset( $_POST['tour'][ $i ]['popover'] ) ) {
					continue;
				}

				$tour[] = array(
					'element' => sanitize_text_field( $_POST['tour'][ $i ]['element'] ),
					'popover' => array(
						'title'       => sanitize_text_field( $_POST['tour'][ $i ]['popover']['title'] ),
						'description' => wp_kses_post( preg_replace( '/(\s|\x{00a0})+/siu', ' ', nl2br( $_POST['tour'][ $i ]['popover']['description'] ) ) ),
					),
				);
			}
		}

		$data['post_content'] = wp_json_encode( wp_slash( $tour ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return $data;
	}

	/**
	 * Adds a css class 'closed' to the postbox_classes_tour_tour-json.
	 *
	 * This is so that the JSON box is closed by default.
	 *
	 * @param      array $classes  The classes.
	 *
	 * @return     array  The modified classes.
	 */
	public static function add_closed_css_class( $classes ) {
		$classes[] = 'closed';
		return $classes;
	}

	/**
	 * Add the tour-json meta box.
	 */
	public static function admin_init() {
		register_setting(
			'tour-settings',
			'tour_github_library_sources',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( get_called_class(), 'sanitize_github_library_sources' ),
				'default'           => '',
			)
		);
		register_setting(
			'tour-settings',
			'tour_github_token',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		add_meta_box(
			'tour-json',
			'JSON',
			function ( $post ) {
				$tour = self::json_decode( $post->post_content );
				if ( $tour ) {
					$json = wp_json_encode( $tour, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
					?><textarea name="json" style="font-family: monospace; width: 100%" rows="<?php echo esc_attr( min( 50, 2 + count( explode( PHP_EOL, $json ) ) ) ); ?>" onchange="void(document.getElementById('override_json').checked=true)"><?php echo esc_html( $json ); ?></textarea><br/>
					<label><input type="checkbox" id="override_json" name="override_json" value="1"> <?php esc_html_e( 'Override when saving', 'tour' ); ?></label>
					<?php
				}
			},
			'tour',
			'side',
			'low'
		);
	}

	/**
	 * Show the custom tour edit fields after the editor.
	 *
	 * @param      WP_Post $post   The post.
	 */
	public static function edit_form_after_editor( $post ) {
		if ( 'tour' !== get_post_type( $post ) ) {
			return;
		}
		wp_tinymce_inline_scripts();

		$tour = self::json_decode( $post->post_content );
		if ( ! $tour ) {
			$color = '#3939c7';
			$tour  = array();
		} else {
			$color = $tour[0]['color'];
			array_shift( $tour );
		}

		?>
	<div style="border: 1px solid #ccc; border-radius: 4px; padding: .5em; margin-top: 2em">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Color', 'tour' ); ?></th>
				<td>
					<input type="color" name="color" id="tour_color" value="<?php echo esc_attr( $color ); ?>" />
				</td>
			</tr>
		</table>
	</div>
	<div id="steps">
		<?php
		foreach ( $tour as $k => $step ) {
			?>
			<div class="step" style="border: 1px solid #ccc; border-radius: 4px; padding: .5em; margin-top: 2em">
				<input type="hidden" name="order[]" value="<?php echo esc_attr( $k ); ?>"/>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="tour-title-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Title', 'tour' ); ?></label><br>
							</th>
							<td>
								<input name="tour[<?php echo esc_attr( $k ); ?>][popover][title]" rows="7" id="tour-step-title-<?php echo esc_attr( $k ); ?>" class="regular-text" value="<?php echo esc_attr( $step['popover']['title'] ); ?>"/>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tour-step-description-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'Description', 'tour' ); ?></label></th>
							<td>
								<?php
								wp_editor(
									$step['popover']['description'],
									'tour-step-description-' . $k,
									array(
										'textarea_name'    => 'tour[' . $k . '][popover][description]',
										'tinymce'          => true,
										'quicktags'        => true,
										'editor_height'    => 300,
										'media_buttons'    => true,
										'teeny'            => true,
										'editor_css'       => '',
										'textarea_rows'    => 7,
										'drag_drop_upload' => true,
										'wpautop'          => true,
									)
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tour-step-element-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'CSS Selector', 'tour' ); ?></label></th>
							<td>
								<textarea name="tour[<?php echo esc_attr( $k ); ?>][element]" rows="7" id="tour-step-element-<?php echo esc_attr( $k ); ?>" class="large-text code tour-step-css"><?php echo esc_html( is_array( $step['element'] ) ? reset( $step['element'] ) : $step['element'] ); ?></textarea>
							</td>
						</tr>
					</tbody>
				</table>
				<a href="#" class="delete-tour-step" data-delete-text="<?php esc_attr_e( 'Delete', 'tour' ); ?>" data-undo-text="<?php esc_attr_e( 'Undo Delete', 'tour' ); ?>"><?php esc_html_e( 'Delete', 'tour' ); ?></a>
				<a href="#" class="tour-move-up"><?php esc_html_e( 'Move Up', 'tour' ); ?></a>
				<a href="#" class="tour-move-down"><?php esc_html_e( 'Move Down', 'tour' ); ?></a>

			</div>
			<?php
		}
		?>
		</div>
		<?php if ( $post->post_title ) : ?>
		<br/><button id="add-more-steps" class="button"><?php esc_html_e( 'Add Steps', 'tour' ); ?></button>
	<?php else : ?>
		<p class="description">
			Set a title to add tour steps.
		</p>
	<?php endif; ?>
	<style>
		#driver-popover-content {
			max-width: none;
		}
	</style>
	<script>
		document.getElementById('post').addEventListener('submit', function ( event ) {
			setTourCookie( document.getElementById('post_ID').value );
		} );

		<?php if ( $post->post_title ) : ?>
		document.getElementById('add-more-steps').addEventListener('click', function ( event ) {
			event.preventDefault();
			setTourCookie( document.getElementById('post_ID').value );
			const driver = window.driver.js.driver;
			var driverObj = driver( {
				showProgress: false,
				steps: [
					{
						element: '#tour-launcher',
						popover: {
							title: 'Add your first step',
							description: 'Click this to enable and disable.',
							side: 'top'
						}
					},
					{
						popover: {
							title: 'Select the element to highlight',
							description: '<img src="<?php echo esc_url( plugins_url( 'assets/images/select-tour-step.gif', __FILE__ ) ); ?>" alt="<?php esc_attr_e( 'Tour creation mode', 'tour' ); ?>" width="525" height="166" />',
							side: 'top'
						}
					}
				]
			} );
			driverObj.drive();
		} );
		<?php endif; ?>
		var updateArrows = function() {
			document.querySelectorAll('.step').forEach( function( element ) {
				element.querySelector('.tour-move-up').style.display = element.previousElementSibling ? 'inline' : 'none';
				element.querySelector('.tour-move-down').style.display = element.nextElementSibling ? 'inline' : 'none';
			});
		}

		document.addEventListener('click', function( event ) {
			if ( ! event.target.matches('.tour-move-up') ) {
				return;
			}
			event.preventDefault();
			var element = event.target.closest('div');
			var parent = element.parentNode;
			var prev = element.previousElementSibling;
			if ( prev ) {
				parent.insertBefore( element, prev );
			}
			updateArrows();
		});

		document.addEventListener('click', function( event ) {
			if ( ! event.target.matches('.tour-move-down') ) {
				return;
			}
			event.preventDefault();
			var element = event.target.closest('div');
			var parent = element.parentNode;
			var next = element.nextElementSibling;
			if ( next ) {
				parent.insertBefore( next, element );
			}
			updateArrows();
		});
		updateArrows();

		document.addEventListener('click', function( event ) {
			if ( ! event.target.matches('.delete-tour-step') ) {
				return;
			}
			event.preventDefault();
			var t = event.target.closest('div').querySelector('table');
			var css = t.querySelector('.tour-step-css');
			if ( t.style.display === 'none' ) {
				t.style.display = 'table';
				css.value = css.dataset.oldValue;
				event.target.textContent = event.target.dataset.deleteText;
				return;
			}
			t.style.display = 'none';
			css.dataset.oldValue = css.value;
			css.value = '';
			event.target.textContent = event.target.dataset.undoText;
		});

	</script>
		<?php
	}

	/**
	 * Add the tours post_type posts via the tour_list hook.
	 *
	 * @param      array $tours   The tours.
	 *
	 * @return     array The augmented tours.
	 */
	public static function tour_list( $tours ) {
		$args = array(
			'post_type'      => 'tour',
			'posts_per_page' => -1,
		);

		if ( current_user_can( 'edit_others_posts' ) ) {
			$args['post_status'] = array( 'publish', 'draft' );
		}

		foreach ( get_posts( $args ) as $tour ) {
			$tour_steps = self::json_decode( $tour->post_content );
			if ( ! $tour_steps ) {
				$tour_steps = array(
					array(
						'title'  => $tour->post_title,
						'append' => 'draft' === $tour->post_status ? ' (' . _x( 'Draft', 'post status' ) . ')' : '', // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
						'color'  => '#3939c7',
					),
				);
			} elseif ( 'draft' === $tour->post_status ) {
				$tour_steps[0]['append'] = ' (' . _x( 'Draft', 'post status' ) . ')'; // phpcs:ignore WordPress.WP.I18n.MissingArgDomain
			}
			$tours[ $tour->ID ] = $tour_steps;
		}

		return $tours;
	}

	/**
	 * Adds the tour menu to the sidebar.
	 */
	public static function add_admin_menu() {
		add_menu_page( 'Tours', 'Tours', 'edit_others_posts', 'tour', 'tour', 'dashicons-admin-site-alt3', 6 );
		add_submenu_page( 'tour', 'Library', 'Library', 'edit_others_posts', 'tour-library', array( get_called_class(), 'tour_admin_library' ) );
		add_submenu_page( 'tour', 'Settings', 'Settings', 'edit_others_posts', 'tour-settings', array( get_called_class(), 'tour_admin_settings' ) );
	}

	/**
	 * Output the tour library.
	 */
	public static function tour_admin_library() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import tours.', 'tour' ) );
		}

		$library = self::get_tour_library_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tour Library', 'tour' ); ?></h1>
			<?php if ( isset( $_GET['imported'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Tour imported as a draft.', 'tour' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['pr'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php esc_html_e( 'GitHub pull request created:', 'tour' ); ?>
						<a href="<?php echo esc_url( wp_unslash( $_GET['pr'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" target="_blank" rel="noreferrer"><?php echo esc_html( wp_unslash( $_GET['pr'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></a>
					</p>
				</div>
			<?php endif; ?>

			<?php foreach ( $library['errors'] as $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error->get_error_message() ); ?></p></div>
			<?php endforeach; ?>

			<?php if ( empty( $library['items'] ) ) : ?>
				<p><?php esc_html_e( 'There are no tours available in the library.', 'tour' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tour', 'tour' ); ?></th>
							<th><?php esc_html_e( 'Description', 'tour' ); ?></th>
							<th><?php esc_html_e( 'Source', 'tour' ); ?></th>
							<th><?php esc_html_e( 'Status', 'tour' ); ?></th>
							<th><?php esc_html_e( 'Action', 'tour' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $library['items'] as $tour ) : ?>
							<?php $imported_tour = self::get_imported_library_tour( $tour['id'] ); ?>
							<tr>
								<td><strong><?php echo esc_html( $tour['title'] ); ?></strong></td>
								<td><?php echo esc_html( isset( $tour['description'] ) ? $tour['description'] : '' ); ?></td>
								<td><?php echo esc_html( $tour['source_label'] ); ?></td>
								<td>
									<?php
									if ( $imported_tour ) {
										echo esc_html( self::get_imported_tour_status_label( $imported_tour ) );
									} else {
										esc_html_e( 'Not imported', 'tour' );
									}
									?>
								</td>
								<td>
									<?php if ( $imported_tour ) : ?>
										<a class="button" href="<?php echo esc_url( get_edit_post_link( $imported_tour->ID, '' ) ); ?>"><?php esc_html_e( 'Edit', 'tour' ); ?></a>
										<a class="button" href="<?php echo esc_url( self::get_export_tour_package_url( $imported_tour->ID ) ); ?>"><?php esc_html_e( 'Export package', 'tour' ); ?></a>
										<?php if ( self::is_github_imported_tour( $imported_tour ) ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block">
												<input type="hidden" name="action" value="tour_submit_github_fix" />
												<input type="hidden" name="tour" value="<?php echo esc_attr( $imported_tour->ID ); ?>" />
												<?php wp_nonce_field( 'tour_submit_github_fix_' . $imported_tour->ID ); ?>
												<?php submit_button( __( 'Submit fix PR', 'tour' ), 'secondary small', 'submit', false ); ?>
											</form>
										<?php endif; ?>
									<?php else : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="tour_import_library_tour" />
											<input type="hidden" name="source" value="<?php echo esc_attr( $tour['source_key'] ); ?>" />
											<input type="hidden" name="tour" value="<?php echo esc_attr( $tour['id'] ); ?>" />
											<?php wp_nonce_field( 'tour_import_library_tour_' . $tour['id'] ); ?>
											<?php submit_button( __( 'Import', 'tour' ), 'primary small', 'submit', false ); ?>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Import a tour from the library.
	 */
	public static function import_library_tour() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import tours.', 'tour' ) );
		}

		$tour_id = isset( $_POST['tour'] ) ? sanitize_key( wp_unslash( $_POST['tour'] ) ) : '';
		check_admin_referer( 'tour_import_library_tour_' . $tour_id );

		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		if ( '' === $source || '' === $tour_id ) {
			wp_die( esc_html__( 'Invalid tour library source.', 'tour' ) );
		}

		$package = self::get_library_tour_package( $source, $tour_id );
		if ( is_wp_error( $package ) ) {
			wp_die( esc_html( $package->get_error_message() ) );
		}

		$tour_steps = Tour_Package::package_to_tour_steps( $package );
		if ( is_wp_error( $tour_steps ) ) {
			wp_die( esc_html( $tour_steps->get_error_message() ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'tour',
				'post_status'  => 'draft',
				'post_title'   => $package['title'],
				'post_content' => wp_json_encode( $tour_steps, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html( $post_id->get_error_message() ) );
		}

		update_post_meta( $post_id, '_tour_package_id', $package['id'] );
		update_post_meta( $post_id, '_tour_package_source', $package['source'] );
		update_post_meta( $post_id, '_tour_package_imported_hash', self::get_package_hash( $package ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'tour-library',
					'imported' => $post_id,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Submit a fixed tour package to GitHub as a pull request.
	 */
	public static function submit_github_fix() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to submit tour fixes.', 'tour' ) );
		}

		$post_id = isset( $_POST['tour'] ) ? absint( $_POST['tour'] ) : 0;
		check_admin_referer( 'tour_submit_github_fix_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || 'tour' !== $post->post_type ) {
			wp_die( esc_html__( 'The requested tour was not found.', 'tour' ) );
		}

		$package = self::get_package_for_tour_post( $post );
		if ( is_wp_error( $package ) ) {
			wp_die( esc_html( $package->get_error_message() ) );
		}

		$source = isset( $package['source'] ) ? $package['source'] : array();
		if ( empty( $source['type'] ) || 'github' !== $source['type'] ) {
			wp_die( esc_html__( 'Only GitHub-sourced tours can be submitted to GitHub.', 'tour' ) );
		}

		$token = get_option( 'tour_github_token', '' );
		if ( '' === $token ) {
			wp_die( esc_html__( 'Add a GitHub access token in Tours settings before submitting fixes.', 'tour' ) );
		}

		$pull_request = self::create_github_fix_pull_request( $package, $token );
		if ( is_wp_error( $pull_request ) ) {
			wp_die( esc_html( $pull_request->get_error_message() ) );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'tour-library',
					'pr'   => $pull_request,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Sanitize GitHub library sources.
	 *
	 * @param string $sources Source lines.
	 * @return string Sanitized source lines.
	 */
	public static function sanitize_github_library_sources( $sources ) {
		$sanitized = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $sources ) as $source ) {
			$source = trim( $source );
			if ( '' === $source || 0 === strpos( $source, '#' ) ) {
				continue;
			}

			if ( self::parse_github_library_source( $source ) ) {
				$sanitized[] = $source;
			}
		}

		return implode( "\n", $sanitized );
	}

	/**
	 * Export a tour package.
	 */
	public static function export_tour_package() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to export tours.', 'tour' ) );
		}

		$post_id = isset( $_GET['tour'] ) ? absint( $_GET['tour'] ) : 0;
		check_admin_referer( 'tour_export_package_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || 'tour' !== $post->post_type ) {
			wp_die( esc_html__( 'The requested tour was not found.', 'tour' ) );
		}

		$package = self::get_package_for_tour_post( $post );
		if ( is_wp_error( $package ) ) {
			wp_die( esc_html( $package->get_error_message() ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $package['id'] . '.json' ) . '"' );

		echo wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Get library items from all configured sources.
	 *
	 * @return array Library items and errors.
	 */
	private static function get_tour_library_items() {
		$items  = array();
		$errors = array();

		foreach ( self::get_tour_library_sources() as $source ) {
			$catalog = self::get_tour_library_catalog( $source );
			if ( is_wp_error( $catalog ) ) {
				$errors[] = $catalog;
				continue;
			}

			foreach ( $catalog['tours'] as $tour ) {
				$tour['source_key']   = $source['key'];
				$tour['source_label'] = $source['label'];
				$items[]              = $tour;
			}
		}

		return array(
			'items'  => $items,
			'errors' => $errors,
		);
	}

	/**
	 * Get configured tour library sources.
	 *
	 * @return array Library sources.
	 */
	private static function get_tour_library_sources() {
		$sources = array(
			array(
				'type'  => 'bundled',
				'key'   => 'bundled',
				'label' => __( 'Bundled', 'tour' ),
			),
		);

		foreach ( preg_split( '/\r\n|\r|\n/', get_option( 'tour_github_library_sources', '' ) ) as $source_line ) {
			$source = self::parse_github_library_source( $source_line );
			if ( ! $source ) {
				continue;
			}

			$sources[] = $source;
		}

		/**
		 * Filters configured tour library sources.
		 *
		 * @param array $sources Library sources.
		 */
		return apply_filters( 'tour_library_sources', $sources );
	}

	/**
	 * Parse a GitHub library source line.
	 *
	 * @param string $source Source line.
	 * @return array|null Source data.
	 */
	private static function parse_github_library_source( $source ) {
		$source = trim( (string) $source );
		if ( ! preg_match( '/^([A-Za-z0-9_.-]+)\/([A-Za-z0-9_.-]+):([^@\s]+)(?:@([A-Za-z0-9_.\/-]+))?$/', $source, $matches ) ) {
			return null;
		}

		$owner = $matches[1];
		$repo  = $matches[2];
		$path  = trim( $matches[3], '/' );
		$ref   = isset( $matches[4] ) ? $matches[4] : 'trunk';

		return array(
			'type'  => 'github',
			'key'   => 'github-' . md5( $owner . '/' . $repo . ':' . $path . '@' . $ref ),
			'label' => sprintf(
				/* translators: 1: GitHub repository, 2: Git ref. */
				__( 'GitHub: %1$s @ %2$s', 'tour' ),
				$owner . '/' . $repo,
				$ref
			),
			'owner' => $owner,
			'repo'  => $repo,
			'path'  => $path,
			'ref'   => $ref,
		);
	}

	/**
	 * Get a catalog for a library source.
	 *
	 * @param array $source Library source.
	 * @return array|WP_Error Catalog.
	 */
	private static function get_tour_library_catalog( $source ) {
		if ( 'bundled' === $source['type'] ) {
			return self::get_bundled_tour_catalog();
		}

		if ( 'github' === $source['type'] ) {
			$catalog = self::get_github_json_file( $source, $source['path'] . '/catalog.json' );
			if ( is_wp_error( $catalog ) ) {
				return $catalog;
			}

			$validation = Tour_Package::validate_catalog( $catalog['json'] );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}

			return $catalog['json'];
		}

		return new WP_Error( 'tour_library_unknown_source', __( 'The tour library source is not supported.', 'tour' ) );
	}

	/**
	 * Get a package from a library source.
	 *
	 * @param string $source_key Library source key.
	 * @param string $tour_id    Tour package id.
	 * @return array|WP_Error Package.
	 */
	private static function get_library_tour_package( $source_key, $tour_id ) {
		foreach ( self::get_tour_library_sources() as $source ) {
			if ( $source_key !== $source['key'] ) {
				continue;
			}

			if ( 'bundled' === $source['type'] ) {
				return self::get_bundled_tour_package( $tour_id );
			}

			if ( 'github' === $source['type'] ) {
				return self::get_github_tour_package( $source, $tour_id );
			}
		}

		return new WP_Error( 'tour_library_missing_source', __( 'The requested tour library source was not found.', 'tour' ) );
	}

	/**
	 * Get the bundled tour catalog.
	 *
	 * @return array|WP_Error Catalog data.
	 */
	private static function get_bundled_tour_catalog() {
		$catalog_path = __DIR__ . '/library/catalog.json';
		if ( ! file_exists( $catalog_path ) ) {
			return new WP_Error( 'tour_library_missing_catalog', __( 'The bundled tour catalog is missing.', 'tour' ) );
		}

		$catalog = json_decode( file_get_contents( $catalog_path ), true );
		if ( ! is_array( $catalog ) ) {
			return new WP_Error( 'tour_library_invalid_catalog', __( 'The bundled tour catalog is invalid.', 'tour' ) );
		}

		$validation = Tour_Package::validate_catalog( $catalog );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		return $catalog;
	}

	/**
	 * Get a bundled tour package by id.
	 *
	 * @param string $tour_id Tour package id.
	 * @return array|WP_Error Package data.
	 */
	private static function get_bundled_tour_package( $tour_id ) {
		$catalog = self::get_bundled_tour_catalog();
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}

		foreach ( $catalog['tours'] as $tour ) {
			if ( $tour_id !== $tour['id'] ) {
				continue;
			}

			$base_path = realpath( __DIR__ . '/library/tours' );
			$path      = realpath( __DIR__ . '/library/tours/' . $tour['path'] );
			if ( ! $base_path || ! $path || 0 !== strpos( $path, $base_path . DIRECTORY_SEPARATOR ) ) {
				return new WP_Error( 'tour_library_invalid_path', __( 'The bundled tour path is invalid.', 'tour' ) );
			}

			$package = json_decode( file_get_contents( $path ), true );
			if ( ! is_array( $package ) ) {
				return new WP_Error( 'tour_library_invalid_package', __( 'The bundled tour package is invalid.', 'tour' ) );
			}

			$package['source'] = array(
				'type' => 'bundled',
				'path' => 'library/tours/' . $tour['path'],
			);

			return Tour_Package::normalize_package( $package );
		}

		return new WP_Error( 'tour_library_missing_tour', __( 'The requested bundled tour was not found.', 'tour' ) );
	}

	/**
	 * Get a GitHub tour package by id.
	 *
	 * @param array  $source  GitHub source.
	 * @param string $tour_id Tour package id.
	 * @return array|WP_Error Package data.
	 */
	private static function get_github_tour_package( $source, $tour_id ) {
		$catalog = self::get_tour_library_catalog( $source );
		if ( is_wp_error( $catalog ) ) {
			return $catalog;
		}

		foreach ( $catalog['tours'] as $tour ) {
			if ( $tour_id !== $tour['id'] ) {
				continue;
			}

			$package = self::get_github_json_file( $source, $source['path'] . '/' . ltrim( $tour['path'], '/' ) );
			if ( is_wp_error( $package ) ) {
				return $package;
			}

			$package['json']['source'] = array(
				'type' => 'github',
				'repo' => $source['owner'] . '/' . $source['repo'],
				'path' => $source['path'] . '/' . ltrim( $tour['path'], '/' ),
				'ref'  => $source['ref'],
				'sha'  => $package['sha'],
			);

			return Tour_Package::normalize_package( $package['json'] );
		}

		return new WP_Error( 'tour_library_missing_tour', __( 'The requested GitHub tour was not found.', 'tour' ) );
	}

	/**
	 * Get and decode a JSON file from the GitHub Contents API.
	 *
	 * @param array  $source GitHub source.
	 * @param string $path   File path.
	 * @return array|WP_Error Decoded JSON and Git blob sha.
	 */
	private static function get_github_json_file( $source, $path ) {
		$url = add_query_arg(
			array(
				'ref' => $source['ref'],
			),
			sprintf(
				'https://api.github.com/repos/%1$s/%2$s/contents/%3$s',
				rawurlencode( $source['owner'] ),
				rawurlencode( $source['repo'] ),
				str_replace( '%2F', '/', rawurlencode( $path ) )
			)
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress Tours Plugin',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			return new WP_Error( 'tour_library_github_request_failed', __( 'GitHub did not return the requested tour library file.', 'tour' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['content'] ) ) {
			return new WP_Error( 'tour_library_github_invalid_response', __( 'GitHub returned an invalid tour library response.', 'tour' ) );
		}

		$content = base64_decode( preg_replace( '/\s+/', '', $body['content'] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- GitHub Contents API returns file contents base64 encoded.
		if ( false === $content ) {
			return new WP_Error( 'tour_library_github_invalid_content', __( 'GitHub returned invalid tour library content.', 'tour' ) );
		}

		$json = json_decode( $content, true );
		if ( ! is_array( $json ) ) {
			return new WP_Error( 'tour_library_github_invalid_json', __( 'GitHub returned invalid tour library JSON.', 'tour' ) );
		}

		return array(
			'json' => $json,
			'sha'  => isset( $body['sha'] ) ? $body['sha'] : '',
		);
	}

	/**
	 * Get an imported tour by package id.
	 *
	 * @param string $package_id Package id.
	 * @return WP_Post|null Imported post.
	 */
	private static function get_imported_library_tour( $package_id ) {
		$posts = get_posts(
			array(
				'post_type'      => 'tour',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'meta_key'       => '_tour_package_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $package_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return empty( $posts ) ? null : $posts[0];
	}

	/**
	 * Get the status label for an imported tour.
	 *
	 * @param WP_Post $post Imported tour post.
	 * @return string Status label.
	 */
	private static function get_imported_tour_status_label( $post ) {
		$imported_hash = get_post_meta( $post->ID, '_tour_package_imported_hash', true );
		$package       = self::get_package_for_tour_post( $post );
		if ( is_wp_error( $package ) || ! $imported_hash ) {
			return __( 'Imported', 'tour' );
		}

		if ( self::get_package_hash( $package ) !== $imported_hash ) {
			return __( 'Modified locally', 'tour' );
		}

		return __( 'Imported', 'tour' );
	}

	/**
	 * Check whether a tour was imported from GitHub.
	 *
	 * @param WP_Post $post Tour post.
	 * @return bool Whether the tour came from GitHub.
	 */
	private static function is_github_imported_tour( $post ) {
		$source = get_post_meta( $post->ID, '_tour_package_source', true );

		return is_array( $source ) && ! empty( $source['type'] ) && 'github' === $source['type'];
	}

	/**
	 * Get the export URL for a tour package.
	 *
	 * @param int $post_id Tour post id.
	 * @return string Export URL.
	 */
	private static function get_export_tour_package_url( $post_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'tour_export_package',
					'tour'   => $post_id,
				),
				admin_url( 'admin-post.php' )
			),
			'tour_export_package_' . $post_id
		);
	}

	/**
	 * Convert a tour post into a package.
	 *
	 * @param WP_Post $post Tour post.
	 * @return array|WP_Error Package data.
	 */
	private static function get_package_for_tour_post( $post ) {
		$tour_steps = self::json_decode( $post->post_content );
		if ( ! is_array( $tour_steps ) ) {
			return new WP_Error( 'tour_export_invalid_json', __( 'The tour does not contain valid JSON.', 'tour' ) );
		}

		$args       = array();
		$package_id = get_post_meta( $post->ID, '_tour_package_id', true );
		if ( $package_id ) {
			$args['id'] = $package_id;
		}

		$source = get_post_meta( $post->ID, '_tour_package_source', true );
		if ( is_array( $source ) ) {
			$args['source'] = $source;
		}

		return Tour_Package::tour_steps_to_package( $tour_steps, $args );
	}

	/**
	 * Create a GitHub pull request for a fixed tour package.
	 *
	 * @param array  $package Tour package.
	 * @param string $token   GitHub token.
	 * @return string|WP_Error Pull request URL or error.
	 */
	private static function create_github_fix_pull_request( $package, $token ) {
		$source = $package['source'];
		if ( empty( $source['repo'] ) || empty( $source['path'] ) || empty( $source['ref'] ) || empty( $source['sha'] ) ) {
			return new WP_Error( 'tour_github_missing_source', __( 'The tour is missing GitHub source metadata.', 'tour' ) );
		}

		$repo_parts = explode( '/', $source['repo'], 2 );
		if ( 2 !== count( $repo_parts ) ) {
			return new WP_Error( 'tour_github_invalid_repo', __( 'The tour GitHub repository is invalid.', 'tour' ) );
		}

		$owner       = $repo_parts[0];
		$repo        = $repo_parts[1];
		$base_branch = $source['ref'];
		$new_branch  = 'tour-fix/' . sanitize_title( $package['id'] ) . '-' . time();
		$json        = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";

		$base_ref = self::github_api_request( 'GET', $owner, $repo, 'git/ref/heads/' . $base_branch, $token );
		if ( is_wp_error( $base_ref ) ) {
			return $base_ref;
		}

		$base_sha = isset( $base_ref['object']['sha'] ) ? $base_ref['object']['sha'] : '';
		if ( '' === $base_sha ) {
			return new WP_Error( 'tour_github_missing_base_sha', __( 'GitHub did not return the base branch revision.', 'tour' ) );
		}

		$created_ref = self::github_api_request(
			'POST',
			$owner,
			$repo,
			'git/refs',
			$token,
			array(
				'ref' => 'refs/heads/' . $new_branch,
				'sha' => $base_sha,
			)
		);
		if ( is_wp_error( $created_ref ) ) {
			return $created_ref;
		}

		$updated_file = self::github_api_request(
			'PUT',
			$owner,
			$repo,
			'contents/' . $source['path'],
			$token,
			array(
				'message' => sprintf(
					/* translators: %s: Tour title. */
					__( 'Fix tour: %s', 'tour' ),
					$package['title']
				),
				'content' => base64_encode( $json ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- GitHub Contents API expects file contents base64 encoded.
				'branch'  => $new_branch,
				'sha'     => $source['sha'],
			)
		);
		if ( is_wp_error( $updated_file ) ) {
			return $updated_file;
		}

		$pull_request = self::github_api_request(
			'POST',
			$owner,
			$repo,
			'pulls',
			$token,
			array(
				'title' => sprintf(
					/* translators: %s: Tour title. */
					__( 'Fix tour: %s', 'tour' ),
					$package['title']
				),
				'body'  => __( 'This pull request updates a tour package exported from the Tours plugin.', 'tour' ),
				'head'  => $new_branch,
				'base'  => $base_branch,
			)
		);
		if ( is_wp_error( $pull_request ) ) {
			return $pull_request;
		}

		if ( empty( $pull_request['html_url'] ) ) {
			return new WP_Error( 'tour_github_missing_pr_url', __( 'GitHub created a pull request but did not return its URL.', 'tour' ) );
		}

		return $pull_request['html_url'];
	}

	/**
	 * Make a GitHub API request.
	 *
	 * @param string $method HTTP method.
	 * @param string $owner  Repository owner.
	 * @param string $repo   Repository name.
	 * @param string $path   API path.
	 * @param string $token  GitHub token.
	 * @param array  $body   Request body.
	 * @return array|WP_Error Decoded response.
	 */
	private static function github_api_request( $method, $owner, $repo, $path, $token, $body = array() ) {
		$url  = sprintf(
			'https://api.github.com/repos/%1$s/%2$s/%3$s',
			rawurlencode( $owner ),
			rawurlencode( $repo ),
			str_replace( '%2F', '/', rawurlencode( $path ) )
		);
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Accept'        => 'application/vnd.github+json',
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'WordPress Tours Plugin',
			),
			'timeout' => 20,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status        = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			$message = isset( $response_body['message'] ) ? $response_body['message'] : __( 'GitHub request failed.', 'tour' );
			return new WP_Error( 'tour_github_request_failed', $message );
		}

		if ( ! is_array( $response_body ) ) {
			return new WP_Error( 'tour_github_invalid_response', __( 'GitHub returned an invalid response.', 'tour' ) );
		}

		return $response_body;
	}

	/**
	 * Get a stable package hash.
	 *
	 * @param array $package Package data.
	 * @return string Package hash.
	 */
	private static function get_package_hash( $package ) {
		return hash( 'sha256', wp_json_encode( $package, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Output the tour settings.
	 */
	public static function tour_admin_settings() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tour Settings', 'tour' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'tour-settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="tour_github_library_sources"><?php esc_html_e( 'GitHub library sources', 'tour' ); ?></label>
						</th>
						<td>
							<textarea class="large-text code" rows="6" id="tour_github_library_sources" name="tour_github_library_sources"><?php echo esc_textarea( get_option( 'tour_github_library_sources', '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Enter one source per line in the format owner/repo:path@ref, for example Automattic/tours:library@trunk.', 'tour' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="tour_github_token"><?php esc_html_e( 'GitHub access token', 'tour' ); ?></label>
						</th>
						<td>
							<input class="regular-text" type="password" id="tour_github_token" name="tour_github_token" value="<?php echo esc_attr( get_option( 'tour_github_token', '' ) ); ?>" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Used to create pull requests when submitting fixed GitHub-sourced tours.', 'tour' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Outputs the tour button.
	 */
	public static function output_tour_button() {
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return;
		}
		?>

		<div id="tour-launcher" style="display: none;">
			<span class="dashicons dashicons-admin-site-alt3"></span>
			<span id="tour-title"></span>
			<br>
			<span style="float: right">
			<span id="tour-steps"></span>
			<a href="">close</a>
			</span>
		</div>
		<?php
	}

	/**
	 * Outputs the tour list with the ability to reset it.
	 */
	public static function show_user_profile() {
		?>
	<h2>Tour</h2>
	<p>Reset your tour progress:</p>
	<table class="">
		<thead>
			<tr>
				<td>Name</td>
				<td>Progress</td>
				<td>Action</td>
			</tr>
		</thead>
		<?php
		$progress = get_user_option( 'tour-progress', get_current_user_id() );
		foreach ( apply_filters( 'tour_list', array() ) as $tour_id => $tour ) {
			$tour_title = $tour[0]['title'];
			?>
		<tr>
			<td><?php echo esc_html( $tour_title ); ?>:</td>
			<td class="tour-progress" data-not-started-text="<?php esc_attr_e( 'Not started.', 'tour' ); ?>">
			<?php
			if ( isset( $progress[ $tour_id ] ) && $progress[ $tour_id ] ) {
				echo esc_html( $progress[ $tour_id ] );
			} else {
				esc_html_e( 'Not started.', 'tour' );
			}
			?>
		</td>
		<td><a href="" class="reset-tour" data-reset-tour-id="<?php echo esc_html( $tour_id ); ?>">Reset</td>
		</tr>
			<?php
		}

		?>
	</table>
	<script>
	document.addEventListener('click', function( event ) {
		if ( ! event.target.dataset.resetTourId ) {
			return;
		}

		event.preventDefault();

		var xhr = new XMLHttpRequest();
		xhr.open('POST', tour_plugin.rest_url + 'tour/v1/save-progress');
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.setRequestHeader('X-WP-Nonce', tour_plugin.nonce);
		xhr.send(JSON.stringify({
			tour: event.target.dataset.resetTourId,
			step: -1
		}));
		var p = event.target.closest('tr').querySelector('.tour-progress');

		p.textContent = p.dataset.notStartedText;

	} );
	</script>
		<?php
	}

	/**
	 * Shows the tour list.
	 *
	 * @param      array $attributes  The attributes.
	 *
	 * @return     string  The content.
	 */
	public static function show_tour_list( $attributes ) {
		$tours = apply_filters( 'tour_list', array() );
		if ( empty( $tours ) ) {
			return '<p>' . esc_html( $attributes['noToursText'] ) . '</p>';
		}
		$tour_list = '<ul id="page-tour-list">';
		foreach ( $tours as $tour_id => $tour ) {
			$tour_list .= '<li><a class="tour-list-item" href="" role="button" data-tour-id="' . esc_attr( $tour_id ) . '">' . esc_html( $tour[0]['title'] . ( isset( $tour[0]['append'] ) ? $tour[0]['append'] : '' ) ) . '</a></li>';
		}
		$tour_list .= '</ul>';

		return $tour_list;
	}

	/**
	 * Register the Available Tours block.
	 */
	public static function register_block_type() {
		register_block_type(
			__DIR__ . '/assets/blocks/build',
			array(
				'api_version'     => 3,
				'attributes'      => array(
					'noToursText' => array(
						'type'    => 'string',
						'default' => __( 'There are no tours available.', 'tour' ),
					),
				),
				'render_callback' => array( get_called_class(), 'show_tour_list' ),
			)
		);
	}

	/**
	 * Adds the tours menu to masterbar.
	 */
	public static function add_tours_menu_to_masterbar() {
		global $wp_admin_bar;
		$tours = apply_filters( 'tour_list', array() );
		if ( empty( $tours ) ) {
			return;
		}
		$wp_admin_bar->add_menu(
			array(
				'id'    => 'tour-list',
				'title' => esc_html__( 'Tours', 'tour' ),
				'href'  => '#',
			)
		);

		foreach ( $tours as $tour_id => $tour ) {
			$wp_admin_bar->add_menu(
				array(
					'parent' => 'tour-list',
					'id'     => 'tour-' . esc_html( $tour_id ),
					'title'  => esc_html( $tour[0]['title'] . ( isset( $tour[0]['append'] ) ? $tour[0]['append'] : '' ) ),
					'href'   => '#',
					'meta'   => array(
						'class' => 'tour-list-item',
					),
				)
			);
		}
	}
}
