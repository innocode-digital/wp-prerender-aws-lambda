<?php
/**
 * Plugin Name: AWS Lambda Prerender
 * Description: Generates HTML for posts/pages via AWS Lambda.
 * Version: 0.5.0
 * Author: Innocode
 * Author URI: https://innocode.com
 * Tested up to: 5.8.2
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Innocode\Prerender;

if (
    defined( 'AWS_LAMBDA_PRERENDER_KEY' ) &&
    defined( 'AWS_LAMBDA_PRERENDER_SECRET' ) &&
    defined( 'AWS_LAMBDA_PRERENDER_REGION' )
) {
    $GLOBALS['innocode_prerender'] = new Prerender\Plugin(
        AWS_LAMBDA_PRERENDER_KEY,
        AWS_LAMBDA_PRERENDER_SECRET,
        AWS_LAMBDA_PRERENDER_REGION,
        defined( 'AWS_LAMBDA_PRERENDER_FUNCTION' )
            ? AWS_LAMBDA_PRERENDER_FUNCTION
            : null,
        defined( 'AWS_LAMBDA_PRERENDER_DB_TABLE' )
            ? AWS_LAMBDA_PRERENDER_DB_TABLE
            : null
    );
    $GLOBALS['innocode_prerender']->run();
}

if ( ! function_exists( 'innocode_prerender' ) ) {
    function innocode_prerender() : ?Prerender\Plugin {
        global $innocode_prerender;

        if ( is_null( $innocode_prerender ) ) {
            trigger_error(
                'Missing required constants',
                E_USER_ERROR
            );
        }

        return $innocode_prerender;
    }
}
