<?php

namespace Innocode\SSR;

use Aws\Lambda\LambdaClient;
use WP_Http;
use WP_Error;

/**
 * Class Render
 *
 * @package InnocodeWP\SSR
 */
final class Render
{
    /**
     * AWS Lambda function name
     */
    private const FUNCTION = 'wordpress-prerender';

    /**
     * WP hook to render post content
     */
    private const RENDER_HOOK = 'render_post_content';

    /**
     * Bind post content render with hooks
     */
    public static function register()
    {
        add_action( 'save_post', [ get_called_class(), 'schedule_post_render' ] );
        add_action( static::RENDER_HOOK, [ get_called_class(), 'post_render' ] );
    }

    /**
     * Schedule to render new post/page HTML content
     *
     * @param int $post_id
     */
    public static function schedule_post_render( int $post_id ): void
    {
        if (
            ! in_array( get_post_status( $post_id ), [
                'publish',
                'trash',
            ] ) ||
            wp_is_post_autosave( $post_id ) ||
            wp_is_post_revision( $post_id )
        ) {
            return;
        }

        wp_schedule_single_event( time(), static::RENDER_HOOK );
    }

    /**
     * Render post content
     *
     * @param int $post_id
     */
    public static function post_render( int $post_id ): void
    {
        Post::detele_prerender_meta( $post_id );
        static::run_aws_lambda_render( $post_id );
    }

    /**
     * Prepare to run AWS Lambda function
     *
     * @param int $post_id
     */
    private static function run_aws_lambda_render( int $post_id ): void
    {
        static::run_lambda(
            static::get_lambda_client(),
            [
                'post_id'       => $post_id,
                'post_url'      => get_permalink( $post_id ),
                'return_url'    => Rest::get_return_url(),
                'secret'        => Security::get_secret_hash()
            ]
        );
    }

    /**
     * Return AWS Lambda client
     *
     * @return LambdaClient
     */
    private static function get_lambda_client(): LambdaClient
    {
        return new LambdaClient( [
            'credentials' => [
                'key'    => defined( 'AWS_LAMBDA_WP_SSR_KEY' ) ? AWS_LAMBDA_WP_SSR_KEY : '',
                'secret' => defined( 'AWS_LAMBDA_WP_SSR_SECRET' ) ? AWS_LAMBDA_WP_SSR_SECRET : '',
            ],
            'region'      => defined( 'AWS_LAMBDA_WP_SSR_REGION' ) ? AWS_LAMBDA_WP_SSR_REGION : '',
            'version'     => 'latest',
        ] );
    }

    /**
     * Returns lambda function name
     *
     * @return string
     */
    private static function get_lambda_function_name(): string
    {
        return defined( 'AWS_LAMBDA_WP_SSR_FUNCTION' )
            ? AWS_LAMBDA_WP_SSR_FUNCTION
            : static::FUNCTION;
    }

    /**
     * Invoke AWS Lambda function
     *
     * @param LambdaClient $client
     * @param array $args
     *
     * @return bool|WP_Error
     */
    private static function run_lambda( LambdaClient $client, array $args )
    {
        $result = $client->invoke( [
            'FunctionName'   => static::get_lambda_function_name(),
            'Payload'        => json_encode( $args ),
            'InvocationType' => 'Event'
        ] );

        return $result[ 'StatusCode' ] != WP_Http::ACCEPTED
            ? new WP_Error( static::FUNCTION, $result[ 'FunctionError' ] )
            : true;
    }
}
