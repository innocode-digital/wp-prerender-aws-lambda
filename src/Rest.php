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
class Rest
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
                'callback'              => [ get_called_class(), 'save_prerender_content' ],
                'permission_callback'   => [ get_called_class(), 'check_permissions' ],
                'args'                  => [
                    'type'      => [
                        'required'          => true,
                        'description'       => __( 'Type', 'innocode-wp-ssr' ),
                        'validate_callback' => function ( $type ) {
                            return is_string( $type );
                        },
                        'sanitize_callback' => function ( $type ) {
                            return esc_attr( $type );
                        }
                    ],
                    'id'   => [
                        'required'          => true,
                        'description'       => __( 'ID', 'innocode-wp-ssr' ),
                        'type'              => 'string',
                        'sanitize_callback' => function ( $id ) {
                            return esc_attr( $id );
                        }
                    ],
                    'content'   => [
                        'required'          => true,
                        'description'       => __( 'Content', 'innocode-wp-ssr' ),
                        'type'              => 'string',
                        'sanitize_callback' => function ( $content ) {
                            return esc_html( $content );
                        }
                    ],
                    'secret'    => [
                        'required'          => true,
                        'description'       => __( 'Secret', 'innocode-wp-ssr' ),
                        'validate_callback' => function ( $secret ) {
                            return is_string( $secret );
                        },
                    ]
                ]
            ]
        );
    }

    /**
     * Save rendered content
     *
     * @param \WP_REST_Request $request
     *
     * @return \WP_REST_Response
     */
    public static function save_prerender_content( WP_REST_Request $request ): WP_REST_Response
    {
        $id = $request->get_param( 'id' );
        $content = $request->get_param( 'content' );

        switch( $type = $request->get_param( 'type' ) ) {
            case 'archive':
                $is_meta_updated = Archive::save_prerender_option( $id, $content );

                break;
            case 'term':
                $is_meta_updated = Term::save_prerender_meta( absint( $id ), $content );

                break;
            case 'post':
                $is_meta_updated = Post::save_prerender_meta( absint( $id ), $content );

                break;
            default:
                $is_meta_updated = false;

                break;
        }

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
