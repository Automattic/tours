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
 *
 */
defined( 'ABSPATH' ) || die();
define( 'TOUR_VERSION', '1.0' );

include __DIR__ . '/class-tour.php';
Tour::init();
