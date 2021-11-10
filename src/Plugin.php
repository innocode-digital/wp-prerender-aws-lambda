<?php

namespace Innocode\Prerender;

/**
 * Class Plugin
 *
 * @package Innocode\Prerender
 */
class Plugin
{
    /**
     * @var DB
     */
    private $db;

    /**
     * @var Lambda
     */
    private $lambda;

    /**
     * @var Prerender
     */
    private $prerender;

    /**
     * @var RESTController
     */
    private $rest_controller;

    /**
     * Plugin constructor.
     *
     * @param string      $key
     * @param string      $secret
     * @param string      $region
     * @param string|null $function
     */
    public function __construct( string $key, string $secret, string $region, string $function )
    {
        $this->db = new Db();
        $this->lambda = new Lambda( $key, $secret, $region );
        $this->lambda->set_function( $function );
        $this->rest_controller = new RESTController();
        $this->prerender = new Prerender( $this->get_lambda(), $this->get_db(), $this->get_rest_controller() );
    }

    /**
     * @return Lambda
     */
    public function get_lambda(): Lambda
    {
        return $this->lambda;
    }

    /**
     * @return RESTController
     */
    public function get_rest_controller(): RESTController
    {
        return $this->rest_controller;
    }

    /**
     * @return Db
     */
    public function get_db(): Db
    {
        return $this->db;
    }

    /**
     * Hook registration
     */
    public function run()
    {
        add_action( 'save_post', [ $this->prerender, 'schedule_post_render' ] );
        add_action( 'delete_post', [ $this->prerender, 'delete_post_prerender' ] );
        add_action( 'saved_term', [ $this->prerender, 'schedule_term_render' ], 10, 3 );
        add_action( 'delete_term', [ $this->prerender, 'delete_term_prerender' ] );
        add_action( 'innocode_prerender_archive', [ $this->prerender, 'archive_render' ], 10, 2 );
        add_action( 'innocode_prerender_post', [ $this->prerender, 'post_render' ] );
        add_action( 'innocode_prerender_term', [ $this->prerender, 'term_render' ], 10, 2 );
        add_action( 'innocode_prerender_frontpage', [ $this->prerender, 'frontpage_render' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Register REST routes
     */
    public function register_rest_routes()
    {
        $this->get_rest_controller()->register_routes();
    }

    /**
     * @param string $type
     * @param int    $id
     *
     * @return string
     */
    public function get_html( string $type, int $id = 0 )
    {
        return $this->get_db()->get_html( $type, $id );
    }
}
