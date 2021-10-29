<?php
/**
 * Plugin Name: WP Prerender AWS Lambda
 * Description: Generates HTML for WordPress pages/posts via AWS Lambda
 * Version: 0.3.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Requires at least: 5.4.2
 * Tested up to: 5.8.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'WP_PRERENDER_VERSION', '0.0.3' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if ( ! function_exists( 'innocode_wp_prerender_aws_lambda_init' ) ) {
    function innocode_wp_prerender_aws_lambda_init() {
        if (
            ! defined( 'AWS_LAMBDA_WP_PRERENDER_KEY' ) ||
            ! defined( 'AWS_LAMBDA_WP_PRERENDER_SECRET' ) ||
            ! defined( 'AWS_LAMBDA_WP_PRERENDER_REGION' ) ||
            ! class_exists( 'Innocode\Prerender\Plugin' )
        ) {
            return;
        }

        $GLOBALS['wp_prerender_aws_lambda'] = new Innocode\Prerender\Plugin(
            AWS_LAMBDA_WP_PRERENDER_KEY,
            AWS_LAMBDA_WP_PRERENDER_SECRET,
            AWS_LAMBDA_WP_PRERENDER_REGION,
            defined( 'AWS_LAMBDA_WP_PRERENDER_FUNCTION' )
                ? AWS_LAMBDA_WP_PRERENDER_FUNCTION
                : null
        );
        $GLOBALS['wp_prerender_aws_lambda']->run();
    }
}

add_action( 'init', 'innocode_wp_prerender_aws_lambda_init' );
