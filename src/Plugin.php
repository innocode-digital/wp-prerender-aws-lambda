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
     * @var Element
     */
    private $element = '#app';

    /**
     * @var Lambda
     */
    private $lambda;

    /**
     * @var RESTController
     */
    private $rest_controller;

    /**
     * @param string      $key
     * @param string      $secret
     * @param string      $region
     * @param string|null $function
     */
    public function __construct( string $key, string $secret, string $region, string $function = null )
    {
        $this->lambda = new Lambda( $key, $secret, $region );
        $this->db = new Db();

        if ( null !== $function ) {
            $this->lambda->set_function( $function );
        }

        $this->rest_controller = new RESTController();
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

    public function run()
    {
        if( apply_filters( 'wp_enable_prerender', false ) ) {
            add_action( 'save_post', [ $this, 'schedule_post_render' ] );
            add_action( 'saved_term', [ $this, 'schedule_term_render' ], 10, 3 );
            add_action( 'wp_prerender_archive_content', [ $this, 'archive_render' ], 10, 2 );
            add_action( 'wp_prerender_post_content', [ $this, 'post_render' ] );
            add_action( 'wp_prerender_term_content', [ $this, 'term_render' ], 10, 2 );
            add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        }
    }

    public function register_rest_routes()
    {
        $this->get_rest_controller()->register_routes();
    }

    /**
     * Schedule to render post/page HTML content
     *
     * @param int $post_id
     */
    public function schedule_post_render( int $post_id ): void
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

        // Prerender post content
        $this->get_db()->clear_entry( $post_id, 'post' );
        wp_schedule_single_event( time(), 'wp_prerender_post_content', [ $post_id ] );

        // Prerender post archive content
        if( $link = get_post_type_archive_link( $post_type = get_post_type( $post_id ) ) ) {
            if( Tools::is_post_showed_in_archive( $post_id, $post_type ) ) {
                $this->get_db()->clear_entry( 0, "{$post_type}_archive" );
                wp_schedule_single_event( time(), 'wp_prerender_archive_content', [ $post_type, $link ] );
            }
        }

        // Prerender post terms content
        global $wp_taxonomies;

        foreach( get_post_taxonomies( $post_id ) as $taxonomy ) {
            if( $wp_taxonomies[ $taxonomy ]->public ) {
                $post_terms = get_the_terms( $post_id, $taxonomy );

                foreach( $post_terms as $term ) {
                    if( Tools::is_post_showed_in_term( $post_id, $term->term_id ) ) {
                        $this->get_db()->clear_entry( $term->term_id, 'term' );
                        wp_schedule_single_event( time(), 'wp_prerender_term_content', [ $term->term_id, $taxonomy ] );
                    }
                }
            }
        }
    }

    /**
     * Schedule to render term HTML content
     *
     * @param int $term_id
     * @param int $tax_id
     * @param string $taxonomy_slug
     */
    public function schedule_term_render( int $term_id, int $tax_id, string $taxonomy_slug ): void
    {
        $taxonomy = get_taxonomy( $taxonomy_slug );

        if( $taxonomy && $taxonomy->public ) {
            $this->get_db()->clear_entry( $term->term_id, 'term' );
            wp_schedule_single_event( time(), 'wp_prerender_term_content', [ $term_id, $taxonomy_slug ] );
        }
    }

    /**
     * Render archive content
     */
    public function archive_render( string $post_type, string $archive_url ): void
    {
        $this->render_with_lambda( [
            'type'          => 'archive',
            'id'            => $post_type,
            'url'           => $archive_url
        ] );
    }

    /**
     * Render archive content
     */
    public function term_render( int $term_id, string $taxonomy ): void
    {
        $this->render_with_lambda( [
            'type'          => 'term',
            'id'            => $term_id,
            'url'           => get_term_link( $term_id, $taxonomy )
        ] );
    }

    /**
     * Render post content
     *
     * @param int $post_id
     */
    public function post_render( int $post_id ): void
    {
        $this->render_with_lambda( [
            'type'          => 'post',
            'id'            => $post_id,
            'url'           => get_permalink( $post_id )
        ] );
    }

    /**
     * Render html markup with AWS Lambda function
     *
     * @param array $args
     */
    public function render_with_lambda( array $args ): void
    {
        $lambda = $this->get_lambda();

        if( false === $secret = get_transient( 'wp_prerender_secret' ) ) {
            $secret = wp_generate_password( 24 );
            set_transient( 'wp_prerender_secret', $secret, 15 * MINUTE_IN_SECONDS );
        }

        $lambda(
            wp_parse_args( $args, [
                    'return_url'    => $this->rest_controller->get_return_url(),
                    'secret'        => wp_hash_password( $secret ),
                    'element'       => apply_filters( 'wp_prerender_element', $this->element )
                ]
            )
        );
    }
}
