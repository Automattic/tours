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

defined( 'ABSPATH' ) or die();

function tour_enqueue_scripts() {
	wp_register_style( 'driver-js', plugins_url( 'assets/css/driver-js.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/driver-js.css' ) );
	wp_register_style( 'tour-css', plugins_url( 'assets/css/style.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/style.css' ) );
	wp_enqueue_style( 'driver-js' );
	wp_enqueue_style( 'tour-css' );
	wp_enqueue_script( 'driver-js', plugins_url( 'assets/js/driver-js.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/js/driver-js.js' ), array( 'in_footer' => true ) );
	wp_register_script( 'tour', plugins_url( 'assets/js/tour.js', __FILE__ ), array( 'jquery', 'driver-js' ), filemtime( __DIR__ . '/assets/js/tour.js' ), false );
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
}

add_action( 'wp_enqueue_scripts', 'tour_enqueue_scripts' );

// register custom post type
function tour_register_post_type() {
	register_post_type(
		'tour',
		array(
			'label'        => 'All Tours',
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'tour',
			'supports'     => array( 'title', 'editor' ),
		)
	);
}
add_action( 'init', 'tour_register_post_type' );

add_action(
	'rest_api_init',
	function() {
		register_rest_route(
			'tour/v1',
			'create',
			array(
				'methods'  => 'POST',
				'callback' => function( WP_REST_Request $request ) {
					$tour_content = $request->get_param( 'tour' );
					$steps = json_decode( $tour_content, true );
					$tour_title = $steps[0]['title'];
					// check if the tour already exists
					$tour = get_page_by_title( $tour_title, OBJECT, 'tour' );
					if ( $tour ) {
						// update the tour
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
						// create the tour
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
		if ( isset( $_REQUEST['post_type'] ) && 'tour' === $_REQUEST['post_type'] ) {
			wp_redirect( admin_url( 'admin.php?page=tour' ) );
			exit;
		}
	}
);

add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( ! isset( $_POST['tour-title'] ) || 'tour' !== $data['post_type'] ) {
			return $data;
		}

		$tour = array(
			array(
				'title' => sanitize_text_field( $_POST['tour-title'] ),
				'color' => sanitize_text_field( $_POST['tour-color'] ),
			),
		);
		foreach ( $_POST['tour-step-html'] as $k => $html ) {
			if ( '' === trim( $_POST['tour-step-css'][ $k ] ) ) {
				continue;
			}
			$tour[] = array(
				'html'     => sanitize_text_field( $html ),
				'selector' => sanitize_text_field( $_POST['tour-step-css'][ $k ] ),
			);
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

	if ( ! isset( $_GET['action'] ) || 'edit' !== $_GET['action'] ) {
		return;
	}

	$tour_steps = json_decode( $post->post_content, true );
	$settings   = array_shift( $tour_steps );

	$tours      = get_posts(
		array(
			'post_type'      => 'tour',
			'posts_per_page' => -1,
			'exclude'        => $post->ID,
		)
	);
	$tour_names = array_map( 'get_the_title', $tours );
	?>
	<table>
		<tr>
			<td>Title</td>
			<td>
				<input type="text" name="tour-title" id="tour_title" value="<?php echo esc_attr( $settings['title'] ); ?>" class="regular-text" required />
			</td>
			<td>Color</td>
			<td>
				<input type="color" name="tour-color"  id="tour_color" value="<?php echo esc_attr( $settings['color'] ); ?>" />
			</td>
		</tr>

	</table>

	<h2>
	<?php
	echo esc_html(
		sprintf(
		// %s: number of steps
			_n(
				'%s Step',
				'%s Steps',
				count( $tour_steps ),
				'tour'
			),
			count( $tour_steps )
		)
	);
	?>
	</h2>
	<?php foreach ( $tour_steps as $k => $step ) : ?>
		<h3><?php echo esc_html( sprintf( __( 'Step %s', 'tour' ), $k + 1 ) ); ?></h3>
		<table class="form-table">
			<tbody>
				<tr>
					<th scope="row"><label for="tour-step-html-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'HTML', 'tour' ); ?></label></th>
					<td>
					<textarea name="tour-step-html[]" rows="7" id="tour-step-html-<?php echo esc_attr( $k ); ?>" class="large-text"><?php echo esc_html( $step['html'] ); ?></textarea>
				</td>
			</tr>
				<tr>
					<th scope="row"><label for="tour-step-css-<?php echo esc_attr( $k ); ?>"><?php esc_html_e( 'CSS Selector', 'tour' ); ?></label></th>
					<td>
					<textarea name="tour-step-css[]" rows="7" id="tour-step-css-<?php echo esc_attr( $k ); ?>" class="large-text code"><?php echo esc_html( $step['selector'] ); ?></textarea>
				</td>
			</tr>
		</table>
	<?php endforeach; ?>

	<?php submit_button( 'Update' ); ?>

	</form></div>
	<script>
		document.querySelector('#post').addEventListener('submit', function(e) {
			var tour_name = document.cookie.indexOf('tour=') > -1 ? unescape(document.cookie.split('tour=')[1].split(';')[0]) : '';
			var tourTitle = document.querySelector('#tour_title').value
			var tourColor = document.querySelector('#tour_color').value;
			document.cookie = 'tour=' + escape( tourTitle ) + ';path=/';
			document.cookie = 'tour_color=' + escape( tourColor ) + ';path=/';
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
			$tour_steps         = json_decode( $_tour->post_content, true );
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

</form></div>
	<script>
		document.querySelector('#create-tour').addEventListener('submit', function(e) {
			e.preventDefault();
			var tourTitle = document.querySelector('#tour_title').value;
			var tourColor = document.querySelector('#tour_color').value;
			document.cookie = 'tour=' + escape( tourTitle ) + ';path=/';
			document.cookie = 'tour_color=' + escape( tourColor ) + ';path=/';
			enable_tour_if_cookie_is_set();
		});

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
		$tour_steps = json_decode( $_tour->post_content, true );
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
	<script>
		if ( typeof wp_tour !== 'undefined' ) {
			window.tour = wp_tour;
		}
		var tourSelectorActive = false;
		var tourSteps = [];
		var dialogOpen = false;
		function enable_tour_if_cookie_is_set() {
			var tour_name = document.cookie.indexOf('tour=') > -1 ? unescape(document.cookie.split('tour=')[1].split(';')[0]) : '';
			var tour_color = document.cookie.indexOf('tour_color=') > -1 ? unescape(document.cookie.split('tour_color=')[1].split(';')[0]) : '';
			if ( tour_name ) {
				document.querySelector('#tour-launcher').style.display = 'block';
				document.querySelector('#tour-title').textContent = tour_name;
				if ( typeof window.tour !== 'undefined' && typeof window.tour[tour_name] !== 'undefined' ) {
					tourSteps = window.tour[tour_name];
					for ( var i = 1; i < tourSteps.length; i++ ) {
						document.querySelector(tourSteps[i].selector).style.outline = '1px dashed ' + tourSteps[0].color;
					}
				} else {
					tourSteps = [
					{
						title: tour_name,
						color: tour_color,
					}];
				}
			}
		}
		enable_tour_if_cookie_is_set();

		function toggleTourSelector( event ) {
			event.stopPropagation();
			tourSelectorActive = ! tourSelectorActive;

			document.querySelector('#tour-launcher').style.color = tourSelectorActive ? tourSteps[0].color : '';
			return false;
		}

		document.querySelector('#tour-launcher').addEventListener('click', toggleTourSelector);
		var clearHighlight = function( event ) {
			for ( var i = 1; i < tourSteps.length; i++ ) {
				if ( event.target.matches(tourSteps[i].selector) ) {
					document.querySelector(tourSteps[i].selector).style.outline = '1px dashed ' + tourSteps[0].color;
					return;
				}
			}
			event.target.style.outline = '';
			event.target.style.cursor = '';
		}

		var tourStepHighlighter = function(event) {
			var target = event.target;
			if ( ! tourSelectorActive || target.closest('#tour-launcher') ) {
				clearHighlight( event );
				return;
			}
			// Highlight the element on hover
			target.style.outline = '2px solid ' + tourSteps[0].color;
			target.style.cursor = 'pointer';
		};


		var tourStepSelector = function(event) {
			if ( ! tourSelectorActive ) {
				return;
			}

			function getSelectors(elem) {
				var selectors = [];

				// Find ID selector
				if ( elem.id ) {
					selectors.push('#' + elem.id);
				} else if ( elem.className ) {
					selectors.push(elem.tagName.toLowerCase()+'.' + elem.className.trim().replace(/(\s+)/g, '.'));
				} else {
					// Find DOM nesting selectors
					while ( elem.parentElement ) {
						var currentElement = elem.parentElement;
						var tagName = elem.tagName.toLowerCase();

						if ( elem.id ) {
							selectors.push( tagName + '#' + elem.id );
							break;
						} else if ( elem.className ) {
							selectors.push( tagName + '.' + elem.className.trim().replace(/(\s+)/g, '.') );

						} else {
							var index = Array.prototype.indexOf.call(currentElement.children, elem) + 1;
							selectors.push(tagName + ':nth-child(' + index + ')');
						}

						elem = currentElement;
					}
				}

				return selectors.reverse();
			}


			event.preventDefault();

			var selectors = getSelectors(event.target);

			dialogOpen = true;
			var stepName = prompt( 'Enter html for step ' + tourSteps.length );
			if ( ! stepName ) {
				event.target.style.outline = '';
				return false;
			}

			tourSteps.push({
				selector: selectors.join(' '),
				html: stepName,
			});

			event.target.style.outline = '1px dashed ' + tourSteps[0].color;

			if ( tourSteps.length > 1 ) {
				window.tour[tourSteps[0].title] = tourSteps;

				// store the tours on the server
				var xhr = new XMLHttpRequest();
				xhr.open('POST', wp_tour_settings.rest_url + 'tour/v1/create');
				xhr.setRequestHeader('Content-Type', 'application/json');
				xhr.setRequestHeader('X-WP-Nonce', wp_tour_settings.nonce);
				xhr.send(JSON.stringify({
					tour: JSON.stringify(tourSteps),
				}));

				document.querySelector('#tour-title').textContent = 'Saved!';

				window.loadTour();
				setTimeout(function() {
					document.querySelector('#tour-title').textContent = tourSteps[0].title;
				}, 1000);
				return false;
			}

			return false;
		};

		document.addEventListener('keyup', function(event) {
			if ( event.keyCode === 27 ) {
				console.log( dialogOpen );
				if ( dialogOpen ) {
					dialogOpen = false;
					return;
				}
				tourSelectorActive = false;
				document.querySelector('#tour-launcher').style.color = '';
			}
		});
		document.addEventListener('mouseover', tourStepHighlighter);
		document.addEventListener('mouseout', clearHighlight);
		document.addEventListener('click', tourStepSelector);
	</script>
	<?php
}

add_action( 'wp_footer', 'output_tour_button' );
add_action( 'admin_footer', 'output_tour_button' );
