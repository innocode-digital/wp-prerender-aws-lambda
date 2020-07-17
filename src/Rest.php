<?php

namespace Innocode\SSR;

use WP_Error;
use WP_Http;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Rest
 *
 * @package InnocodeWP\SSR
 */
final class Rest
{
    /**
     * REST namespace
     */
    private const NAMESPACE = 'innocode/v1';

    /**
     * REST route
     */
    private const ROUTE = '/ssr';

    /**
     * Bind functions with WP hooks
     */
    public static function register(): void
    {
        add_action( 'rest_api_init', [ get_called_class(), 'register_rest_route' ] );
    }

    /**
     * Register REST route to save rendered content from AWS Lambda
     */
    public static function register_rest_route(): void
    {
        register_rest_route(
            static::NAMESPACE,
            static::ROUTE,
            [
                'methods'               => WP_REST_Server::CREATABLE,
                'callback'              => [ get_called_class(), 'save_post_content' ],
                'permission_callback'   => [ get_called_class(), 'check_permissions' ],
                'args'                  => [
                    'post_id'           => [
                        'required'          => true,
                        'description'       => __( 'Post ID', 'innocode-wp-ssr' ),
                        'type'              => 'integer',
                        'validate_callback' => function ( $post_id ) {
                            return ! is_null( get_post( $post_id ) );
                        },
                        'sanitize_callback' => function ( $post_id ) {
                            return absint( $post_id );
                        }
                    ],
                    'content'           => [
                        'required'          => true,
                        'description'       => __( 'Post content', 'innocode-wp-ssr' ),
                        'type'              => 'string',
                        'sanitize_callback' => function ( $content ) {
                            return esc_html( $content );
                        }
                    ],
                    'secret'            => [
                        'required'          => true,
                        'validate_callback' => function ( $secret ) {
                            return is_string( $secret );
                        },
                    ]
                ]
            ]
        );
    }

    /**
     * Save rendered post content
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public static function save_post_content( WP_REST_Request $request ): WP_REST_Response
    {
        $is_meta_updated = Post::save_prerender_meta(
            $request->get_param( 'post_id' ),
            $request->get_param( 'content' )
        );

        return new WP_REST_Response(
            $is_meta_updated,
            $is_meta_updated
                ? WP_Http::OK
                : WP_Http::INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Check permissions to save post meta
     *
     * @param \WP_REST_Request $request
     *
     * @return bool
     */
    public static function check_permissions( WP_REST_Request $request ): bool
    {
        return Security::check_secret_hash( $request->get_param( 'secret' ) );
    }

    /**
     * Generate URL for callback from AWS Lambda
     *
     * @return string
     */
    public static function get_return_url(): string
    {
        return rest_url( static::NAMESPACE . static::ROUTE );
    }
}
