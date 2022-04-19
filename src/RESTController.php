<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Traits\DbTrait;
use WP_Error;
use WP_Http;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RESTController extends WP_REST_Controller
{
    use DbTrait;

    /**
     * @var array
     */
    protected $templates;

    /**
     * RESTController constructor.
     */
    public function __construct()
    {
        $this->namespace = 'innocode/v1';
        $this->rest_base = 'prerender';
    }

    /**
     * @param array $templates
     * @return void
     */
    public function set_templates( array $templates ) : void
    {
        $this->templates = $templates;
    }

    /**
     * @return array
     */
    public function get_templates() : array
    {
        return $this->templates;
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
                        'enum'        => $this->get_templates(),
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
        $template = $request->get_param( 'type' );
        $id = $request->get_param( 'id' );
        $secret = $request->get_param( 'secret' );

        $secret_hash = SecretsManager::get( (string) $template, (string) $id );

        if ( false === $secret_hash || ! wp_check_password( (string) $secret, $secret_hash ) ) {
            return new WP_Error(
                'rest_innocode_prerender_cannot_save_html',
                __( 'Sorry, you are not allowed to save prerender HTML.', 'innocode-prerender' ),
                [ 'status' => WP_Http::UNAUTHORIZED ]
            );
        }

        /**
         * 'permission_callback' is also used after 'callback' in 'rest_send_allow_header' function through
         * 'rest_post_dispatch' hook with priority 10, so, secret should be in place after 'callback' but still
         * better to remove it after response returning as it cannot be used anymore after successful request.
         */
        Helpers::hook( 'rest_pre_echo_response', [ $this, 'delete_secret_hash' ], PHP_INT_MAX );

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
        $template = $request->get_param( 'type' );
        $id = $request->get_param( 'id' );
        $html = $request->get_param( 'html' );
        $version = $request->get_param( 'version' );

        $entry = apply_filters( 'innocode_prerender_callback', null, $template, $id, $html, $version );

        if ( ! ( $entry instanceof Entry ) ) {
            return new WP_Error(
                'rest_innocode_prerender_invalid_callback',
                __( 'There is no callback for such request.', 'innocode-prerender' ),
                [ 'status' => WP_Http::BAD_REQUEST ]
            );
        }

        return $this->prepare_item_for_response( $entry, $request );
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
     * @param array           $result
     * @param WP_REST_Server  $server
     * @param WP_REST_Request $request
     *
     * @return array
     */
    public function delete_secret_hash( array $result, WP_REST_Server $server, WP_REST_Request $request ) : array
    {
        $template = $request->get_param( 'type' );
        $id = $request->get_param( 'id' );

        SecretsManager::delete( $template, $id );

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

    /**
     * @param Entry           $item
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function prepare_item_for_response( $item, $request ) : WP_REST_Response
    {
        return rest_ensure_response( [
            'id'      => $item->get_id(),
            'created' => $item->get_created()->format( 'Y-m-d\TH:i:s' ),
            'updated' => $item->get_updated()->format( 'Y-m-d\TH:i:s' ),
            'html'    => $item->get_html(),
            'version' => $item->get_version(),
        ] );
    }
}
