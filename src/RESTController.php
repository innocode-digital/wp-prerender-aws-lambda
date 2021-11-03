<?php

namespace Innocode\Prerender;

use WP_Error;
use WP_Http;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class RESTController
 *
 * @package Innocode\Prerender
 */
class RESTController extends WP_REST_Controller
{
    /**
     * REST constructor
     */
    public function __construct()
    {
        $this->namespace = 'innocode/v1';
        $this->rest_base = '/prerender';
    }

    /**
     * Register REST route to save rendered content from AWS Lambda
     */
    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            $this->rest_base,
            [
                'methods'               => WP_REST_Server::CREATABLE,
                'callback'              => [ $this, 'save_prerender_content' ],
                'permission_callback'   => [ $this, 'check_permissions' ],
                'args'                  => [
                    'type'      => [
                        'required'          => true,
                        'description'       => __( 'Type', 'innocode-wp-prerender' ),
                        'validate_callback' => function ( $type ) {
                            return is_string( $type );
                        },
                        'sanitize_callback' => function ( $type ) {
                            return esc_attr( $type );
                        }
                    ],
                    'id'   => [
                        'required'          => true,
                        'description'       => __( 'ID', 'innocode-wp-prerender' ),
                        'type'              => 'string',
                        'sanitize_callback' => function ( $id ) {
                            return esc_attr( $id );
                        }
                    ],
                    'content'   => [
                        'required'          => true,
                        'description'       => __( 'Content', 'innocode-wp-prerender' ),
                        'type'              => 'string',
                        'sanitize_callback' => function ( $content ) {
                            return esc_html( $content );
                        }
                    ],
                    'secret'    => [
                        'required'          => true,
                        'description'       => __( 'Secret', 'innocode-wp-prerender' ),
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
    public function save_prerender_content( WP_REST_Request $request ): WP_REST_Response
    {
        $id = $request->get_param( 'id' );
        $content = $request->get_param( 'content' );

        switch( $type = $request->get_param( 'type' ) ) {
            case 'archive':
                $is_data_updated = $GLOBALS['wp_prerender_aws_lambda']->get_db()->save_entry( $content, "{$id}_archive" );

                break;
            case 'term':
                $is_data_updated = $GLOBALS['wp_prerender_aws_lambda']->get_db()->save_entry( $content, "term", $id );

                break;
            case 'post':
                $is_data_updated = $GLOBALS['wp_prerender_aws_lambda']->get_db()->save_entry( $content, "post", $id );

                break;
            default:
                $is_data_updated = false;

                break;
        }

        return new WP_REST_Response(
            $is_data_updated,
            $is_data_updated
                ? WP_Http::OK
                : WP_Http::INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Check permissions to save post meta
     *
     * @param \WP_REST_Request $request
     *
     * @return bool|WP_Error
     */
    public function check_permissions( WP_REST_Request $request )
    {
        $is_secret_valid = (
            false !== ( $secret_hash = get_transient( 'wp_prerender_secret' ) )
            && wp_check_password( $request->get_param( 'secret' ), $secret_hash )
        );

        return $is_secret_valid
            ?: new WP_Error(
                'rest_innocode_aws_lambda_prerender_cannot_save_content',
                __( 'Sorry, you are not allowed to save prerender content', 'innocode-wp-prerender' ),
                [
                    'status' => WP_Http::UNAUTHORIZED,
                ]
            );
    }

    /**
     * Generate URL for callback from AWS Lambda
     *
     * @return string
     */
    public function get_return_url(): string
    {
        return rest_url( "$this->namespace$this->rest_base" );
    }
}
