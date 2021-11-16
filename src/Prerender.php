<?php

namespace Innocode\Prerender;

/**
 * Class Prerender
 *
 * @package Innocode\Prerender
 */
class Prerender
{
    /**
     * @var Lambda
     */
    private $lambda;

    /**
     * @var DB
     */
    private $db;

    /**
     * @var Element
     */
    private $element = '#app';

    /**
     * @var RESTController
     */
    private $rest_controller;


    /**
     * Prerender constructor.
     *
     * @param Lambda         $lambda
     * @param Db             $db
     * @param RESTController $rest_controller
     */
    public function __construct( Lambda $lambda, Db $db, RESTController $rest_controller )
    {
        $this->lambda = $lambda;
        $this->db = $db;
        $this->rest_controller = $rest_controller;
    }

    /**
     * @return Db
     */
    public function get_db(): Db
    {
        return $this->db;
    }

    /**
     * @return string
     */
    public function get_element(): string
    {
        return $this->element;
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
        $this->get_db()->clear_entry( 'post', $post_id );
        wp_schedule_single_event( time(), 'innocode_prerender_post', [ $post_id ] );

        // Prerender frontpage
        $this->get_db()->clear_entry( 'frontpage' );
        wp_schedule_single_event( time(), 'innocode_prerender_frontpage' );

        // Prerender author page
        if( apply_filters( 'innocode_prerender_author_template', false ) ) {
            $author_id = get_post_field ( 'post_author', $post_id );
            $this->get_db()->clear_entry( 'author', $author_id );
            wp_schedule_single_event( time(), 'innocode_prerender_author', [ $author_id ] );
        }

        // Prerender post archive content
        if( $link = get_post_type_archive_link( $post_type = get_post_type( $post_id ) ) ) {
            if( Tools::is_post_showed_in_archive( $post_id, $post_type ) ) {
                $this->get_db()->clear_entry( "{$post_type}_archive" );
                wp_schedule_single_event( time(), 'innocode_prerender_archive', [ $post_type, $link ] );
            }

            // Prerender year, month and day archive
            if( 'post' == $post_type ) {
                $year = get_the_date( 'Y', $post_id );
                $month = get_the_date( 'm', $post_id );
                $day = get_the_date( 'j', $post_id );

                // Year archive
                $this->get_db()->clear_entry( "year_{$year}_archive" );
                wp_schedule_single_event( time(), 'innocode_prerender_archive', [ "year_$year", get_year_link( $year ) ] );

                // Month archive
                $this->get_db()->clear_entry( "month_{$month}_{$year}_archive" );
                wp_schedule_single_event( time(), 'innocode_prerender_archive', [ "month_{$month}_$year", get_month_link( $year, $month ) ] );

                // Day archive
                $this->get_db()->clear_entry( "day_{$day}_{$month}_{$year}_archive" );
                wp_schedule_single_event( time(), 'innocode_prerender_archive', [ "day_{$day}_{$month}_$year", get_day_link( $year, $month, $day ) ] );
            }
        }

        // Prerender post terms content
        global $wp_taxonomies;

        foreach( get_post_taxonomies( $post_id ) as $taxonomy ) {
            if( $wp_taxonomies[ $taxonomy ]->public ) {
                $post_terms = get_the_terms( $post_id, $taxonomy );

                foreach( $post_terms as $term ) {
                    if( Tools::is_post_showed_in_term( $post_id, $term->term_id ) ) {
                        $this->get_db()->clear_entry( 'term', $term->term_id );
                        wp_schedule_single_event( time(), 'innocode_prerender_term', [ $term->term_id, $taxonomy ] );
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
            $this->get_db()->clear_entry( 'term', $term_id );
            wp_schedule_single_event( time(), 'innocode_prerender_term', [ $term_id, $taxonomy_slug ] );

            // Prerender frontpage
            $this->get_db()->clear_entry( 'frontpage' );
            wp_schedule_single_event( time(), 'innocode_prerender_frontpage' );
        }
    }

    /**
     * @param int $id
     */
    public function delete_post_prerender( int $id )
    {
        $this->get_db()->delete_entry( 'post', $id );
    }

    /**
     * @param int $id
     */
    public function delete_term_prerender( int $id )
    {
        $this->get_db()->delete_entry( 'term', $id );
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
     * Render archive content
     */
    public function frontpage_render(): void
    {
        $this->render_with_lambda( [
            'type'          => 'frontpage',
            'id'            => '',
            'url'           => home_url( '/' )
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
     * Render author content
     *
     * @param int $author_id
     */
    public function author_render( int $author_id ): void
    {
        $this->render_with_lambda( [
            'type'          => 'author',
            'id'            => $author_id,
            'url'           => get_author_posts_url( $author_id )
        ] );
    }

    /**
     * Render custom content
     *
     * @param string $type
     * @param string $url
     * @param int    $id
     */
    public function render( string $type, string $url, int $id = 0 ): void
    {
        $this->render_with_lambda( [
            'type'          => esc_attr( $type ),
            'id'            => absint( $id ),
            'url'           => esc_url( $url ),
        ] );
    }

    /**
     * Render html markup with AWS Lambda function
     *
     * @param array $args
     */
    private function render_with_lambda( array $args ): void
    {
        $lambda = $this->get_lambda();

        if( false === $secret = get_transient( 'innocode_prerender_secret' ) ) {
            $secret = wp_generate_password( 24 );
            set_transient( 'innocode_prerender_secret', $secret, 15 * MINUTE_IN_SECONDS );
        }

        $lambda(
            wp_parse_args( $args, [
                    'return_url'    => $this->rest_controller->get_return_url(),
                    'secret'        => wp_hash_password( $secret ),
                    'element'       => apply_filters( 'innocode_prerender_element', $this->get_element() )
                ]
            )
        );
    }
}
