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

add_action( 'wp_footer', function() {
?>
	<style>
		#tour-launcher {
			position: fixed;
			bottom: 76px;
			right: 24px;
		}
	</style>
	<div id="tour-launcher" style="display: block;">
		<span class="dashicons dashicons-admin-site-alt3">
		</span>
	</div>
<?php
});
