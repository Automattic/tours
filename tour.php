<?php
/**
 * Plugin Name: Tour
 * Plugin URI: http://wordpress.org/plugins/tour/
 * Description: A WordPress plugin for creating tours for your site.
 * Version: 1.0
 * Author: Automattic
 * Author URI: http://automattic.com/
 * Text Domain: tour
 * License: GPLv2 or later
 */

defined( 'ABSPATH' ) || die();

function tour_enqueue_scripts() {
	wp_register_style( 'driver-js', plugins_url( 'assets/css/driver-js.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/driver-js.css' ) );
	wp_register_style( 'tour-css', plugins_url( 'assets/css/style.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/style.css' ) );
	wp_enqueue_style( 'driver-js' );
	wp_enqueue_style( 'tour-css' );
	wp_enqueue_script( 'driver-js', plugins_url( 'assets/js/driver-js.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/js/driver-js.js' ), array( 'in_footer' => true ) );
	wp_register_script( 'tour', plugins_url( 'assets/js/tour.js', __FILE__ ), array( 'driver-js' ), filemtime( __DIR__ . '/assets/js/tour.js' ), false );
	wp_enqueue_script( 'tour' );
	wp_localize_script( 'tour', 'wp_tour', apply_filters( 'wp_tour', array() ) );
	wp_localize_script(
		'tour',
		'wp_tour_settings',
		array(
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'rest_url' => rest_url(),
		)
	);

	if ( current_user_can( 'manage_options' ) ) {
		wp_register_script( 'tour-admin', plugins_url( 'assets/js/tour-admin.js', __FILE__ ), array(  'driver-js' ), filemtime( __DIR__ . '/assets/js/tour-admin.js' ), true );
		wp_enqueue_script( 'tour-admin' );
	}

}

add_action( 'admin_enqueue_scripts', 'tour_enqueue_scripts' );
add_action( 'wp_enqueue_scripts', 'tour_enqueue_scripts' );

function tour_register_post_type() {
	register_post_type(
		'tour',
		array(
			'label'        => 'All Tours',
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'tour',
			'supports'     => array( 'title', 'editor', 'revisions' ),
		)
	);
}
add_action( 'init', 'tour_register_post_type' );

add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'tour/v1',
			'report-missing',
			array(
				'methods'  => 'POST',
				'callback' => function( WP_REST_Request $request ) {
					$step = $request->get_param( 'step' );
					$tour_title = $request->get_param( 'tour' );
					$url = $request->get_param( 'url' );

					$tour = get_page_by_title( $tour_title, OBJECT, 'tour' );
					if ( $tour ) {
						$missing_steps = get_post_meta( $tour, 'missing_steps', true );
						if ( ! $missing_steps ) {
							$missing_steps = array();
						}
						if ( ! isset( $missing_steps[ $step ] ) ) {
							$missing_steps[ $step ] = array();
						}
						if ( ! isset( $missing_steps[ $step ][ $url ] ) ) {
							$missing_steps[ $step ][ $url ] = 0;
						}
						$missing_steps[ $step ][ $url ]++;
						update_post_meta( $tour, 'missing_steps', $missing_steps );
						return array(
							'success' => true,
						);
					}
						return array(
							'success' => false,
						);
				},
			)
		);

		register_rest_route(
			'tour/v1',
			'save',
			array(
				'methods'  => 'POST',
				'callback' => function( WP_REST_Request $request ) {
					$tour_content = $request->get_param( 'tour' );
					$steps = json_decode( $tour_content, true );
					$tour_title = $steps[0]['title'];

					$tour = get_page_by_title( $tour_title, OBJECT, 'tour' );
					if ( $tour ) {
						$tour_id = $tour->ID;
						wp_update_post(
							array(
								'ID'           => $tour_id,
								'post_content' => $tour_content,
								'post_status'  => 'publish',
							),
							true
						);
					} else {
						$tour_id = wp_insert_post(
							array(
								'post_title'   => $tour_title,
								'post_content' => $tour_content,
								'post_type'    => 'tour',
								'post_status'  => 'publish',
							)
						);
					}

					return $tour_id;
				},
			)
		);
	}
);

add_action(
	'load-post-new.php',
	function() {
		if ( isset( $_REQUEST['post_type'] ) && 'tour' === $_REQUEST['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=tour' ) );
			exit;
		}
	}
);
add_filter( 'post_row_actions', 'my_custom_row_actions', 10, 2 );
function my_custom_row_actions( $actions, $post ) {
    if ( $post->post_type != 'tour' ) {
        return $actions;
    }

	$tour_steps = json_decode( wp_unslash( $post->post_content ), true );

    $caption = __( 'Add more steps', 'tour' );

    $actions['add-more-steps'] = '<a href="' . get_permalink( $post->ID ) . '" data-tour-title="' . esc_attr( $tour_steps[0]['title'] ) . '" data-tour-color="' . esc_attr( $tour_steps[0]['color'] ) . '" data-add-more-steps-text="' . esc_attr( $caption ) . '" data-finish-tour-creation-text="' . esc_attr( __( 'Finish tour creating the tour',' tour' ) ) . '" title="' . esc_attr( $caption ) . '">' . esc_html( $caption ) . '</a>';
    return $actions;
}

add_filter(
	'tour_row_actions',
	function ( $actions, $tag ) {
		echo 1;exit;
		$actions[] = 'Add more steps';
		return $actions;
	}, 10, 2
);

add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'update-post_' . $postarr['ID'] ) ) {
			return $data;
		}

		if ( ! isset( $_POST['tour'] ) || ! isset( $_POST['tour'][0]['title'] ) || 'tour' !== $data['post_type'] ) {
			return $data;
		}

		$tour = array(
			array()
		);
		foreach ( $_POST['tour'][0] as $k => $v ) {
			$tour[0][$k] = sanitize_text_field( $v );
		}

		foreach ( $_POST['order'] as $i ) {
			$step = $_POST['tour'][$i];

			if ( '' === trim( $step['element'] ) ) {
				continue;
			}

			if ( ! isset( $step['popover'] ) ) {
				continue;
			}

			$step['element'] = sanitize_text_field($step['element']);
			foreach ( $step['popover'] as $k => $v ) {
				if ( ! in_array( $k, array( 'title', 'description'))) {
					unset($step['popover'][$k]);
				}
			}
			$tour[] = $step;
		}
		$data['post_title']   = $tour[0]['title'];
		$data['post_content'] = wp_json_encode( $tour );
		return $data;
	},
	10,
	2
);

function tour_edit_form_top( $post ) {
	if ( 'tour' !== get_post_type( $post ) ) {
		return;
	}

	$tour = json_decode( wp_unslash( $post->post_content ), true );
	register_and_do_post_meta_boxes( $post );

	?>
	<details><summary>JSON</summary>
	<pre><?php echo esc_html( json_encode( $tour, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) ); ?></pre></details>
	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-2">
			<div id="postbox-container-1" class="postbox-container">
				<?php
				do_action( 'submitpost_box', $post );
				do_meta_boxes( 'tour', 'side', $post );
				?>
			</div>
			<div id="postbox-container-2" class="postbox-container">
				<table>
					<tr>
						<td>Title</td>
						<td>
							<input type="text" name="tour[0][title]" id="tour_title" value="<?php echo esc_attr( $tour[0]['title'] ); ?>" class="regular-text" required />
						</td>
						<td>Color</td>
						<td>
							<input type="color" name="tour[0][color]"  id="tour_color" value="<?php echo esc_attr( $tour[0]['color'] ); ?>" />
						</td>
						<td><button id="add-more-steps">Add more steps</button></td>
					</tr>

				</table>
				<div id="steps">
					<?php
					foreach ( $tour as $k => $step ) {
						if ( 0 === $k ) {
							continue;
						}
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
											<textarea name="tour[<?php echo esc_attr( $k ); ?>][popover][description]" rows="7" id="tour-step-description-<?php echo esc_attr( $k ); ?>" class="large-text"><?php echo esc_html( $step['popover']['description'] ); ?></textarea>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="tour-step-element-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'CSS Selector', 'tour' ); ?></label></th>
										<td>
											<textarea name="tour[<?php echo esc_attr( $k ); ?>][element]" rows="7" id="tour-step-element-<?php echo esc_attr( $k ); ?>" class="large-text code tour-step-css"><?php echo esc_html( $step['element'] ); ?></textarea>
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
			</form>
		</div>
	</div>
	<script>
		document.querySelector('#post').addEventListener('submit', function ( event ) {
			setTourCookie( document.querySelector('#tour_title').value, document.querySelector('#tour_color').value );
		} );

		document.querySelector('#add-more-steps').addEventListener('click', function ( event ) {
			event.preventDefault();
			setTourCookie( document.querySelector('#tour_title').value, document.querySelector('#tour_color').value );
		} );

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
	do_action( 'wp_footer' );
	exit;
}
add_action( 'edit_form_top', 'tour_edit_form_top' );


add_filter(
	'wp_tour',
	function( $tour ) {
		$tours = get_posts(
			array(
				'post_type'      => 'tour',
				'posts_per_page' => -1,
			)
		);

		foreach ( $tours as $_tour ) {
			$tour_steps         = json_decode( wp_unslash( $_tour->post_content ), true );
			$tour_name          = $tour_steps[0]['title'];
			$tour[ $tour_name ] = $tour_steps;
		}

		return $tour;
	}
);
function tour_add_admin_menu() {
	add_menu_page( 'Tour', 'Tour', 'manage_options', 'tour', 'tour_admin_create_tour', 'dashicons-admin-site-alt3', 6 );
	add_submenu_page( 'tour', 'Create a new tour', 'Create a new tour', 'manage_options', 'tour', 'tour_admin_create_tour' );
	add_submenu_page( 'tour', 'Settings', 'Settings', 'manage_options', 'tour-settings', 'tour_admin_settings' );
}

add_action( 'admin_menu', 'tour_add_admin_menu' );
function tour_admin_create_tour() {
	$tours      = get_posts(
		array(
			'post_type'      => 'tour',
			'posts_per_page' => -1,
		)
	);
	$tour_names = array_map( 'get_the_title', $tours );
	$tour_names = array_map( 'preg_quote', $tour_names );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Create a new tour', 'tour' ); ?></h1>
		<form id="create-tour">
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="tour_title"><?php esc_html_e( 'Title', 'tour' ); ?></label></th>
					<td>
						<input type="text" name="tour_title" id="tour_title" value="Test" class="regular-text" required />
						<p class="description"><?php esc_html_e( 'This cannot be the name of an already existing tour.', 'tour' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tour_color"><?php esc_html_e( 'Color', 'tour' ); ?></label></th>
					<td>
						<input type="color" name="tour_color" id="tour_color" value="#3939c7" required />
					</td>
				</tr>
			</tbody>
		</table>

		<button>Start creating the tour</button>

		</form>
		<div id="ready-tour">
			<h2><?php esc_html_e( 'Ready to start creating the tour?', 'tour' ); ?></h2>
			<p><?php esc_html_e( 'Navigate to the page where you want to add your tour and click the button in the bottom corner.', 'tour' ); ?></p>
			<p><?php esc_html_e( 'Click on the elements you want to highlight and add a description for each step.', 'tour' ); ?></p>

			<img src="<?php echo esc_url( plugins_url( 'assets/images/select-tour-step.gif', __FILE__ ) ); ?>" alt="<?php esc_attr_e( 'Tour creation mode', 'tour' ); ?>" />
			<p><?php esc_html_e( 'When you are done, come back here and click on the button below.', 'tour' ); ?></p>

			<button id="finished-creating-tour">Finished creating the tour</button>
		</div>
	</div>
	<script>
		document.querySelector('#create-tour').addEventListener('submit', function(e) {
			e.preventDefault();
			setTourCookie( document.querySelector('#tour_title').value, document.querySelector('#tour_color').value );
			this.style.display = 'none';
			document.querySelector('#ready-tour').style.display = 'block';
		});

		document.querySelector('#finished-creating-tour').addEventListener('click', function(e) {
			e.preventDefault();
			deleteTourCookie();

			document.querySelector('#ready-tour').style.display = 'none';
			document.querySelector('#create-tour').style.display = 'block';
		});

		document.querySelector('#create-tour').style.display = document.cookie.indexOf('tour=') > -1 ? 'none' : 'block';
		document.querySelector('#ready-tour').style.display = document.cookie.indexOf('tour=') === -1 ? 'none' : 'block';
	</script>

	<?php
}

function tour_admin_view_tours() {
	$tours = get_posts(
		array(
			'post_type'      => 'tour',
			'posts_per_page' => -1,
		)
	);

	?>
	<ul>
	<?php
	foreach ( $tours as $_tour ) {
		$tour_steps = json_decode( wp_unslash( $_tour->post_content ), true );
		$tour_name  = $tour_steps[0]['title'];
		$tour_color = $tour_steps[0]['color'];
		?>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tour&tour=' . $_tour->ID ) ); ?>"><?php echo esc_html( $tour_name ); ?></a>
			<span style="background-color: <?php echo esc_attr( $tour_color ); ?>; width: 20px; height: 20px; display: inline-block;"></span>
		<?php echo count( $tour_steps ); ?> steps
			(<?php echo esc_html( $_tour->post_date ); ?>)

		</li>
		<?php
	}
}
function tour_admin_settings() {}

function output_tour_button() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<style>
		#tour-launcher {
			position: fixed;
			bottom: 76px;
			right: 24px;
			font-size: 13px;
			cursor: pointer;
			border: 1px solid #ccc;
			border-radius: 10px;
			background: #fff;
			padding: .5em;
			line-height: 1;
			box-shadow: 0 0 3px #999;
			z-index: 999999;
		}
		#tour-launcher:hover {
			text-shadow: 0 0 1px #999;
		}
		#tour-launcher span#tour-title {
			line-height: 1.3em;
		}
	</style>
	<div id="tour-launcher" style="display: none;">
		<span class="dashicons dashicons-admin-site-alt3">
		</span>
		<span id="tour-title"></span>
	</div>
	<?php
}

add_action( 'wp_footer', 'output_tour_button' );
add_action( 'admin_footer', 'output_tour_button' );
