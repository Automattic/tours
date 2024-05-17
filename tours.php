<?php
/**
 * Plugin Name: Tours
 * Plugin URI: http://wordpress.org/plugins/tours/
 * Description: A WordPress plugin for creating tours for your site.
 * Version: 1.0.0
 * Author: Automattic
 * Author URI: http://automattic.com/
 * Text Domain: tours
 * License: GPLv2 or later
 *
 * @package Tour
 */

defined( 'ABSPATH' ) || die();
define( 'TOURS_VERSION', '1.0' );

require __DIR__ . '/class-tours.php';
Tours::register_hooks();
