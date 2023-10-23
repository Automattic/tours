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
	wp_enqueue_style( 'driver-js');
	wp_enqueue_style(   'tour-css' );
	wp_enqueue_script( 'driver-js', plugins_url( 'assets/js/driver-js.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/js/driver-js.js' ), array( 'in_footer' => true ) );
	wp_register_script( 'tour', plugins_url( 'assets/js/tour.js', __FILE__ ), array( 'jquery', 'driver-js' ), filemtime( __DIR__ . '/assets/js/tour.js' ), false );
	wp_enqueue_script( 'tour');
	wp_localize_script( 'tour', 'wp_tour', apply_filters( 'wp_tour', array() ) );
	wp_localize_script( 'tour', 'wp_tour_settings', array(
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'rest_url' => rest_url(),
	));
}

add_action( 'wp_enqueue_scripts', 'tour_enqueue_scripts' );

// register custom post type
function tour_register_post_type() {
	register_post_type( 'tour', array(
		'label' => 'Tours',
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => 'tour',
		'supports' => array( 'title', 'editor' ),
	) );
}
add_action( 'init', 'tour_register_post_type' );

add_action( 'rest_api_init', function() {
	register_rest_route( 'tour/v1', 'create', array(
		'methods' => 'POST',
		'callback' => function( WP_REST_Request $request ) {
			$tour_content = $request->get_param('tour');
			$steps = json_decode( $tour_content, true );
			$tour_title = $steps[0]['title'];
			// check if the tour already exists
			$tour = get_page_by_title( $tour_title, OBJECT, 'tour' );
			if ( $tour ) {
				// update the tour
				$tour_id = $tour->ID;
				wp_update_post( array(
					'ID' => $tour_id,
					'post_content' => $tour_content,
				) );
			} else {
				// create the tour
				$tour_id = wp_insert_post( array(
					'post_title' => $tour_title,
					'post_content' => $tour_content,
					'post_type' => 'tour',
					'post_status' => 'publish',
				) );
			}

			return $tour_id;
		},
	) );
} );


add_filter( 'wp_tour', function( $tour ) {
	$tours = get_posts( array(
		'post_type' => 'tour',
		'posts_per_page' => -1,
	) );

	foreach ( $tours as $_tour ) {
		$tour_steps = json_decode( $_tour->post_content, true );
		$tour_name = $tour_steps[0]['title'];
		$tour[$tour_name] = $tour_steps;
	}

	return $tour;
} );
function tour_add_admin_menu() {
	add_menu_page( 'Tour', 'Tour', 'manage_options', 'tour', 'tour_admin_create_tour', 'dashicons-admin-site-alt3', 6 );
	add_submenu_page( 'tour', 'Create a new tour', 'Create a new tour', 'manage_options', 'tour', 'tour_admin_create_tour' );
	add_submenu_page( 'tour', 'View all tours', 'View all tours', 'manage_options', 'tour-view', 'tour_admin_view_tours' );
	add_submenu_page( 'tour', 'Settings', 'Settings', 'manage_options', 'tour-settings', 'tour_admin_settings' );
}

add_action( 'admin_menu', 'tour_add_admin_menu' );
function tour_admin_create_tour() {
	?>
<div id="wrap"><form id="create-tour">
	<h1><?php esc_html_e( 'Create a new tour', 'tour' ); ?></h1>
	<table class="form-table">
		<tbody>
			<tr>
				<th scope="row"><label for="tour_title"><?php esc_html_e( 'Title', 'tour' ); ?></label></th>
				<td>
					<input type="text" name="tour_title" id="tour_title" value="Test" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'The title of the tour.', 'tour' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="tour_color"><?php esc_html_e( 'Color', 'tour' ); ?></label></th>
				<td>
					<input type="color" name="tour_color" id="tour_color" value="#3939c7" class="regular-text" required />
					<p class="description"><?php esc_html_e( 'The color of the tour.', 'tour' ); ?></p>
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
			console.log( tourColor );
			document.cookie = 'tour=' + escape( tourTitle ) + ';path=/';
			document.cookie = 'tour_color=' + escape( tourColor ) + ';path=/';
			enable_tour_if_cookie_is_set();
		});

	</script>

<?php
}

function tour_admin_view_tours() {
	$tours = get_posts( array(
		'post_type' => 'tour',
		'posts_per_page' => -1,
	) );

	?><ul><?php
	foreach ( $tours as $_tour ) {
		$tour_steps = json_decode( $_tour->post_content, true );
		$tour_name = $tour_steps[0]['title'];
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
			cursor: pointer;
		}
	</style>
	<div id="tour-launcher" style="display: none;">
		<span class="dashicons dashicons-admin-site-alt3">
		</span>
		<span id="tour-title"></span>
	</div>
	<script>
		var tourSelectorActive = false;
		var tourSteps = [];
		function enable_tour_if_cookie_is_set() {
			var tour_name = document.cookie.indexOf('tour=') > -1 ? unescape(document.cookie.split('tour=')[1].split(';')[0]) : '';
			var tour_color = document.cookie.indexOf('tour_color=') > -1 ? unescape(document.cookie.split('tour_color=')[1].split(';')[0]) : '';
			if ( tour_name ) {
				document.querySelector('#tour-launcher').style.display = 'block';
				document.querySelector('#tour-title').textContent = tour_name;
				tourSteps=[{
					title: tour_name,
					color: tour_color,
				}];
			}
		}
		enable_tour_if_cookie_is_set();

		function toggleTourSelector( event ) {
			event.stopPropagation();
			tourSelectorActive = ! tourSelectorActive;

			document.querySelector('#tour-launcher').style.color = tourSelectorActive ? tourSteps[0].color : '';

			if ( ! tourSelectorActive && tourSteps.length > 1 ) {
				if (confirm('Finished?')) {
					window.tour[tourSteps[0].title] = tourSteps;

					// store the tours on the server
					var xhr = new XMLHttpRequest();
					xhr.open('POST', wp_tour_settings.rest_url + 'tour/v1/create');
					xhr.setRequestHeader('Content-Type', 'application/json');
					xhr.setRequestHeader('X-WP-Nonce', wp_tour_settings.nonce);
					xhr.send(JSON.stringify({
						tour: JSON.stringify(tourSteps),
					}));

					console.log( window.tour );
					window.loadTour();

				}
				return false;
			}

			return false;
		}
		document.querySelector('#tour-launcher').addEventListener('click', toggleTourSelector);
		var clearHighlight = function( event ) {
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
			console.log( target.style.outline );
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

			var stepName = prompt( 'Enter html for step ' + tourSteps.length );
			if ( stepName ) {
				tourSteps.push({
					selector: selectors.join(' '),
					html: stepName,
				});

				console.log(tourSteps);
			}

			// Remove the highlighting
			clearHighlight( event );

			return false;
		};

		document.addEventListener('keyup', function(event) {
			if ( event.keyCode === 27 ) {
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
