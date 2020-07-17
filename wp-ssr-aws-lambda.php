<?php
/**
 * Plugin Name: WP SSR AWS Lambda
 * Description: Generates HTML for WordPress pages/posts via AWS Lambda
 * Version: 0.0.1
 * Author: Innocode
 * Author URI: https://innocode.com
 * Requires at least: 5.3.2
 * Tested up to: 5.4.2
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'WP_SSR_VERSION', '0.0.1' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if( class_exists( 'Innocode\SSR\Plugin' ) ) {
    Innocode\SSR\Plugin::register();
}
