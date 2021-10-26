<?php
/**
 * Plugin Name: WP Prerender AWS Lambda
 * Description: Generates HTML for WordPress pages/posts via AWS Lambda
 * Version: 0.2.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Requires at least: 5.4.2
 * Tested up to: 5.8.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'WP_PRERENDER_VERSION', '0.0.1' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if( class_exists( 'Innocode\Prerender\Plugin' ) ) {
    Innocode\Prerender\Plugin::register();
}
