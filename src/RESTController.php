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
     * @var string
     */
    protected $namespace;

    /**
     * @var string
     */
    protected $rest_base;

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
                'callback'              => [ $this, 'create_item' ],
                'permission_callback'   => [ $this, 'create_item_permissions_check' ],
                'args'                  => [
                    'type'      => [
                        'required'          => true,
                        'description'       => __( 'Type', 'innocode-prerender' ),
                        'validate_callback' => function ( $type ) {
                            return is_string( $type );
                        },
                        'sanitize_callback' => function ( $type ) {
                            return esc_attr( $type );
                        }
                    ],
                    'id'   => [
                        'required'          => true,
                        'description'       => __( 'ID', 'innocode-prerender' ),
                        'type'              => 'string',
                        'sanitize_callback' => function ( $id ) {
                            return esc_attr( $id );
                        }
                    ],
                    'html'   => [
                        'required'          => true,
                        'description'       => __( 'HTML', 'innocode-prerender' ),
                        'type'              => 'string',
                        'sanitize_callback' => function ( $html ) {
                            return esc_html( $html );
                        }
                    ],
                    'secret'    => [
                        'required'          => true,
                        'description'       => __( 'Secret', 'innocode-prerender' ),
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
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response
     */
    public function create_item( $request )
    {
        $id = $request->get_param( 'id' );
        $html = $request->get_param( 'html' );

        switch( $type = $request->get_param( 'type' ) ) {
            case 'frontpage':
                $number = innocode_wp_prerender_aws_lambda()->get_db()->save_entry( $html, $type );

                break;
            case 'archive':
                $number = innocode_wp_prerender_aws_lambda()->get_db()->save_entry( $html, "{$type}_$id" );

                break;
            case 'post':
            case 'term':
                $number = innocode_wp_prerender_aws_lambda()->get_db()->save_entry( $html, $type, $id );

                break;
            default:
                $number = false;

                break;
        }

        return new WP_REST_Response(
            $number,
            $number
                ? WP_Http::OK
                : WP_Http::INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Check permissions to save post meta
     *
     * @param WP_REST_Request $request
     *
     * @return bool|WP_Error
     */
    public function create_item_permissions_check( $request )
    {
        $is_secret_valid = (
            false !== ( $secret = get_transient( 'innocode_prerender_secret' ) )
            && wp_check_password( $secret, $request->get_param( 'secret' ) )
        );

        return $is_secret_valid
            ?: new WP_Error(
                'rest_innocode_aws_lambda_prerender_cannot_save_html',
                __( 'Sorry, you are not allowed to save prerender html', 'innocode-prerender' ),
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
