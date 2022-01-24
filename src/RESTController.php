<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Traits\DbTrait;
use WP_Error;
use WP_Http;
use WP_HTTP_Response;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RESTController extends WP_REST_Controller
{
    use DbTrait;

    /**
     * RESTController constructor.
     */
    public function __construct()
    {
        $this->namespace = 'innocode/v1';
        $this->rest_base = 'prerender';
    }

    /**
     * Registers REST routes to save rendered HTML from AWS Lambda.
     *
     * @return void
     */
    public function register_routes() : void
    {
        register_rest_route(
            $this->namespace,
            $this->rest_base,
            [
                'methods'               => WP_REST_Server::EDITABLE,
                'callback'              => [ $this, 'save_item' ],
                'permission_callback'   => [ $this, 'save_item_permissions_check' ],
                'args'                  => [
                    'type'            => [
                        'description' => __( 'Type of the prerender.', 'innocode-prerender' ),
                        'type'        => 'string',
                        'enum'        => Plugin::get_types(),
                        'required'    => true,
                    ],
                    'id'              => [
                        'description' => __( 'Object identifier for the prerender.', 'innocode-prerender' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'html'            => [
                        'description' => __( 'HTML of the prerender.', 'innocode-prerender' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'version'         => [
                        'description'       => __( 'HTML version of the prerender.', 'innocode-prerender' ),
                        'type'              => 'string',
                        'required'          => true,
                        'validate_callback' => [ $this, 'validate_version' ],
                    ],
                    'secret'          => [
                        'description' => __( 'Secret for the callback.', 'innocode-prerender' ),
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Checks permissions to save post meta.
     *
     * @param WP_REST_Request $request
     *
     * @return bool|WP_Error
     */
    public function save_item_permissions_check( WP_REST_Request $request )
    {
        $type = $request->get_param( 'type' );
        $id = $request->get_param( 'id' );
        $secret = $request->get_param( 'secret' );

        $secret_hash = SecretsManager::get( $type, $id );

        if ( false === $secret_hash || ! wp_check_password( $secret, $secret_hash ) ) {
            return new WP_Error(
                'rest_innocode_prerender_cannot_save_html',
                __( 'Sorry, you are not allowed to save prerender HTML.', 'innocode-prerender' ),
                [ 'status' => WP_Http::UNAUTHORIZED ]
            );
        }

        return true;
    }

    /**
     * Saves rendered HTML.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function save_item( WP_REST_Request $request )
    {
        $type = $request->get_param( 'type' );
        $id = $request->get_param( 'id' );

        $converted_type_id = Plugin::convert_type_id( $type, $id );

        if ( is_wp_error( $converted_type_id ) ) {
            $converted_type_id->add_data( [ 'status' => WP_Http::BAD_REQUEST ] );

            return $converted_type_id;
        }

        /**
         * 'permission_callback' is also used after 'callback' in 'rest_send_allow_header' function through
         * 'rest_post_dispatch' hook with priority 10, so, secret should be in place after 'callback' but still
         * better to remove it after response returning as it cannot be used anymore after successful request.
         */
        add_filter( 'rest_post_dispatch', [ $this, 'delete_secret_hash' ], PHP_INT_MAX, 3 );

        list( $type, $object_id ) = $converted_type_id;

        $html = $request->get_param( 'html' );
        $version = $request->get_param( 'version' );

        $result = $this->get_db()->save_entry( $html, $version, $type, $object_id );

        $success_status = is_int( $result ) ? WP_Http::CREATED : WP_Http::OK;

        return new WP_REST_Response(
            $result,
            $result ? $success_status : WP_Http::INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Generates URL for callback from AWS Lambda.
     *
     * @return string
     */
    public function url() : string
    {
        return rest_url( "/$this->namespace/$this->rest_base/" );
    }

    /**
     * Removes secret before response returning.
     *
     * @param WP_HTTP_Response $result
     * @param WP_REST_Server $server
     * @param WP_REST_Request $request
     *
     * @return WP_HTTP_Response
     */
    public function delete_secret_hash(
        WP_HTTP_Response $result,
        WP_REST_Server $server,
        WP_REST_Request $request
    ) : WP_HTTP_Response
    {
        $type = $request->get_param( 'type' );
        $id = $request->get_param( 'id' );

        SecretsManager::delete( $type, $id );

        return $result;
    }

    /**
     * Checks if version is equal to current HTML version.
     *
     * @param string $param
     *
     * @return bool
     */
    public function validate_version( string $param ) : bool
    {
        $html_version = $this->get_db()->get_html_version();

        return $html_version() == $param;
    }
}
