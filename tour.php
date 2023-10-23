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

function tour_enqueue_scripts() {
	wp_register_style( 'driver-js', plugins_url( 'assets/css/driver-js.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/driver-js.css' ) );
	wp_enqueue_script( 'driver-js', plugins_url( 'assets/js/driver-js.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/js/driver-js.js' ), array( 'in_footer' => true ) );
	wp_register_script( 'gp-tour', plugins_url( 'assets/js/tour.js', __FILE__ ), array( 'jquery', 'driver-js' ), filemtime( __DIR__ . '/assets/js/tour.js' ), false );
	wp_set_script_translations( 'gp-tour', 'tour' );
	wp_localize_script( 'gp-tour', 'gp_tour', apply_filters( 'gp_tour', array() ) );}

add_action( 'wp_enqueue_scripts', 'tour_enqueue_scripts' );

add_filter( 'gp_tour', function( ) {
	return array(
		'ui-intro' => [
			[
				'title' => 'UI Introduction Tour',
				'color' => '#3939c7',
			],
			[
				'selector' => '.strings .original',
				'html' => 'This is the original string that needs translation'
			],
			[
				'selector' => 'textarea.foreign-text',
				'html' => 'Enter translation here',
			],
			[
				'selector' => '.sidebar-tabs',
				'html' => 'See meta tabs here',
			],
			[
				'selector' => '.button.ok',
				'html' => 'Click here to save translation',
			]
		],

	);
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
					<input type="text" name="tour_title" id="tour_title" value="" class="regular-text" />
					<p class="description"><?php esc_html_e( 'The title of the tour.', 'tour' ); ?></p>
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
			if ( ! tourTitle ) {
				alert( 'Please enter a title for the tour' );
				return;
			}
			document.cookie = 'tour=' + escape( tourTitle ) + ';path=/';
			enable_tour_if_cookie_is_set();
		});

	</script>

<?php
}

function tour_admin_view_tours() {}
function tour_admin_settings() {}

function output_tour_button() {
?>
	<style>
		#tour-launcher {
			position: fixed;
			bottom: 76px;
			right: 24px;
		}
	</style>
	<div id="tour-launcher" style="display: none;">
		<span class="dashicons dashicons-admin-site-alt3">
		</span>
	</div>
	<script>
		function enable_tour_if_cookie_is_set() {
			var tour_name = document.cookie.indexOf('tour=') > -1 ? document.cookie.split('tour=')[1].split(';')[0] : '';
			if ( tour_name ) {
				var launcher = document.querySelector('#tour-launcher');
				launcher.style.display = 'block';
				launcher.appendChild(document.createTextNode(tour_name));
			}
		}
	</script>
<?php
}

add_action( 'wp_footer', 'output_tour_button' );
add_action( 'admin_footer', 'output_tour_button' );

