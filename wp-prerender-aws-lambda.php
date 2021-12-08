<?php
/**
 * Plugin Name: AWS Lambda Prerender
 * Description: Generates HTML for client-side rendered content via AWS Lambda.
 * Version: 1.1.0
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

define( 'INNOCODE_PRERENDER_FILE', __FILE__ );

if (
    ! defined( 'AWS_LAMBDA_PRERENDER_KEY' ) ||
    ! defined( 'AWS_LAMBDA_PRERENDER_SECRET' ) ||
    ! defined( 'AWS_LAMBDA_PRERENDER_REGION' )
) {
    return;
}

$GLOBALS['innocode_prerender'] = new Prerender\Plugin(
    AWS_LAMBDA_PRERENDER_KEY,
    AWS_LAMBDA_PRERENDER_SECRET,
    AWS_LAMBDA_PRERENDER_REGION
);

if ( ! defined( 'AWS_LAMBDA_PRERENDER_FUNCTION' ) ) {
    define( 'AWS_LAMBDA_PRERENDER_FUNCTION', 'prerender-production-render' );
}

$GLOBALS['innocode_prerender']
    ->get_prerender()
    ->get_lambda()
    ->set_function( AWS_LAMBDA_PRERENDER_FUNCTION );

if ( ! defined( 'AWS_LAMBDA_PRERENDER_DB_TABLE' ) ) {
    define( 'AWS_LAMBDA_PRERENDER_DB_TABLE', 'innocode_prerender' );
}

$GLOBALS['innocode_prerender']
    ->get_db()
    ->set_table( AWS_LAMBDA_PRERENDER_DB_TABLE );

if ( ! defined( 'AWS_LAMBDA_PRERENDER_QUERY_ARG' ) ) {
    define( 'AWS_LAMBDA_PRERENDER_QUERY_ARG', 'innocode_prerender' );
}

$GLOBALS['innocode_prerender']
    ->get_query()
    ->set_name( AWS_LAMBDA_PRERENDER_QUERY_ARG );

$GLOBALS['innocode_prerender']->run();

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
