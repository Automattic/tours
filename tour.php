<?php
/**
 * Plugin Name: Tour
 * Plugin URI: http://wordpress.org/plugins/tour/
 * Description: A WordPress plugin for creating tours for your site.
 * Version: 1.0
 * Author: Automattic
 * Author URI: http://automattic.com/
 */

function tour_enqueue_scripts() {
	wp_register_style( 'driver-js', plugins_url( 'assets/css/driver-js.css', __FILE__ ), array(), filemtime( __DIR__ . '/assets/css/driver-js.css' ) );
	wp_enqueue_script( 'driver-js', plugins_url( 'assets/js/driver-js.js', __FILE__ ), array(), filemtime( __DIR__ . '/assets/js/driver-js.js' ), array( 'in_footer' => true ) );
}

add_action( 'wp_enqueue_scripts', 'tour_enqueue_scripts' );

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
