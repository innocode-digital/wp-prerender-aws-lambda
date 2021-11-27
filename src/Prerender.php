<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Traits\DbTrait;
use WP_Post;

/**
 * Class Prerender
 *
 * @package Innocode\Prerender
 */
class Prerender
{
    use DbTrait;

    /**
     * @var Lambda
     */
    protected $lambda;
    /**
     * @var string
     */
    protected $selector = '#app';
    /**
     * @var string
     */
    protected $return_url;

    /**
     * Prerender constructor.
     *
     * @param string      $key
     * @param string      $secret
     * @param string      $region
     * @param string|null $function
     */
    public function __construct(
        string $key,
        string $secret,
        string $region,
        string $function = null
    )
    {
        $this->lambda = new Lambda( $key, $secret, $region );

        if ( null !== $function ) {
            $this->lambda->set_function( $function );
        }
    }

    /**
     * @return Lambda
     */
    public function get_lambda() : Lambda
    {
        return $this->lambda;
    }

    /**
     * @return string
     */
    public function get_selector() : string
    {
        return apply_filters( 'innocode_prerender_selector', $this->selector );
    }

    /**
     * @param string $return_url
     */
    public function set_return_url( string $return_url )
    {
        $this->return_url = $return_url;
    }

    /**
     * @return string
     */
    public function get_return_url() : string
    {
        return $this->return_url;
    }

    /**
     * Updates Post/Page HTML.
     *
     * @param string $new_status
     * @param string $old_status
     * @param WP_Post $post
     */
    public function update_post( string $new_status, string $old_status, WP_Post $post ) : void
    {
        if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
            return;
        }

        if ( 'publish' != $new_status ) {
            $this->delete_post( $post->ID );

            return;
        }

        $this->schedule_post( $post->ID );
        $this->update_post_related( $post->ID );
    }

    /**
     * Updates Term HTML.
     *
     * @param int    $term_id
     * @param int    $tt_id
     * @param string $taxonomy_name
     */
    public function update_term( int $term_id, int $tt_id, string $taxonomy_name ) : void
    {
        $taxonomy = get_taxonomy( $taxonomy_name );

        if ( ! $taxonomy || ! $taxonomy->public ) {
            return;
        }

        $this->schedule_term( $term_id, $taxonomy->name );
        $this->update_term_related();
    }

    /**
     * @param int $post_id
     */
    public function delete_post( int $post_id ) : void
    {
        if ( $this->get_db()->delete_entry( 'post', $post_id ) ) {
            $this->update_post_related( $post_id );
        }
    }

    /**
     * @param int $term_id
     */
    public function delete_term( int $term_id ) : void
    {
        if ( $this->get_db()->delete_entry( 'term', $term_id ) ) {
            $this->update_term_related();
        }
    }

    /**
     * @param int $post_id
     */
    public function update_post_related( int $post_id ) : void
    {
        $this->schedule_frontpage();

        $user_id = get_post_field( 'post_author', $post_id );

        $this->schedule_author( $user_id );

        $post_type = get_post_type( $post_id );
        $post_type_archive_link = get_post_type_archive_link( $post_type );

        if (
            $post_type_archive_link &&
            untrailingslashit( $post_type_archive_link ) != untrailingslashit( home_url() )
        ) {
            $this->schedule_post_type_archive( $post_type );
        }

        if ( 'post' == $post_type ) {
            $year = get_the_date( 'Y', $post_id );
            $month = get_the_date( 'm', $post_id );
            $day = get_the_date( 'd', $post_id );

            $this->schedule_date_archive( $year );
            $this->schedule_date_archive( $year . $month );
            $this->schedule_date_archive( $year . $month . $day );
        }

        foreach( get_post_taxonomies( $post_id ) as $taxonomy_name ) {
            $taxonomy = get_taxonomy( $taxonomy_name );

            if ( ! $taxonomy || ! $taxonomy->public ) {
                continue;
            }

            $terms = get_the_terms( $post_id, $taxonomy_name );

            if ( empty( $terms ) || is_wp_error( $terms ) ) {
                continue;
            }

            foreach ( $terms as $term ) {
                $this->schedule_term( $term->term_id, $term->taxonomy );
            }
        }
    }

    public function update_term_related() : void
    {
        $this->schedule_frontpage();

        // @TODO: What should we do if post shows term data e.g. name somewhere in content?
    }

    /**
     * @param string     $type
     * @param string|int $object_id_or_subtype
     * @param array      $args
     */
    public function schedule( string $type, $object_id_or_subtype = 0, array $args = [] ) : void
    {
        if ( ! in_array( $type, Plugin::get_types(), true ) ) {
            return;
        }

        $object_id = is_int( $object_id_or_subtype ) ? $object_id_or_subtype : 0;
        $subtype = is_string( $object_id_or_subtype ) ? $object_id_or_subtype : '';

        $this->get_db()->clear_entry( $type . ( $subtype ? "_$subtype" : '' ), $object_id );

        if ( $object_id ) {
            array_unshift( $args, $object_id );
        }

        if ( $subtype ) {
            array_unshift( $args, $subtype );
        }

        wp_clear_scheduled_hook( "innocode_prerender_$type", $args );
        wp_schedule_single_event( time(), "innocode_prerender_$type", $args );
    }

    /**
     * Prerenders Post/Page.
     *
     * @param int $post_id
     */
    public function schedule_post( int $post_id ) : void
    {
        $this->schedule( Plugin::TYPE_POST, $post_id );
    }

    /**
     * Prerenders Term.
     *
     * @param int    $term_id
     * @param string $taxonomy
     */
    public function schedule_term( int $term_id, string $taxonomy ) : void
    {
        $this->schedule( Plugin::TYPE_TERM, $term_id, [ $taxonomy ] );
    }

    /**
     * Prerenders Author Page.
     *
     * @param int $user_id
     */
    public function schedule_author( int $user_id ) : void
    {
        $this->schedule( Plugin::TYPE_AUTHOR, $user_id );
    }

    /**
     * Prerenders Frontpage.
     */
    public function schedule_frontpage() : void
    {
        $this->schedule( Plugin::TYPE_FRONTPAGE );
    }

    /**
     * Prerenders Post Type Archive.
     *
     * @param string $post_type
     */
    public function schedule_post_type_archive( string $post_type ) : void
    {
        $this->schedule( Plugin::TYPE_POST_TYPE_ARCHIVE, $post_type );
    }

    /**
     * Prerenders Date Archive.
     *
     * @param string $date
     */
    public function schedule_date_archive( string $date ) : void
    {
        $this->schedule( Plugin::TYPE_DATE_ARCHIVE, $date );
    }

    /**
     * Renders Post/Page.
     *
     * @param int $post_id
     */
    public function post( int $post_id ) : void
    {
        $this->invoke_lambda( Plugin::TYPE_POST, $post_id, get_permalink( $post_id ) );
    }

    /**
     * Renders Term.
     */
    public function term( int $term_id, string $taxonomy ) : void
    {
        $this->invoke_lambda( Plugin::TYPE_TERM, $term_id, get_term_link( $term_id, $taxonomy ) );
    }

    /**
     * Renders Author Page.
     *
     * @param int $user_id
     */
    public function author( int $user_id ) : void
    {
        $this->invoke_lambda( Plugin::TYPE_AUTHOR, $user_id, get_author_posts_url( $user_id ) );
    }

    /**
     * Renders Frontpage.
     */
    public function frontpage() : void
    {
        $this->invoke_lambda( Plugin::TYPE_FRONTPAGE, '', home_url( '/' ) );
    }

    /**
     * Renders Post Type Archive.
     *
     * @param string $post_type
     */
    public function post_type_archive( string $post_type ) : void
    {
        $this->invoke_lambda( Plugin::TYPE_POST_TYPE_ARCHIVE, $post_type, get_post_type_archive_link( $post_type ) );
    }

    /**
     * Renders Year, Month, Day Archives.
     *
     * @param string $date
     */
    public function date_archive( string $date ) : void
    {
        $parsed = Helpers::parse_Ymd( $date );

        if ( false === $parsed['year'] ) {
            // Something wrong as date should always include year.
            return;
        }

        if ( false !== $parsed['month'] ) {
            $url = false !== $parsed['day']
                ? get_day_link( $parsed['year'], $parsed['month'], $parsed['day'] )
                : get_month_link( $parsed['year'], $parsed['month'] );
        } else {
            $url = get_year_link( $parsed['year'] );
        }

        $this->invoke_lambda( Plugin::TYPE_DATE_ARCHIVE, $date, $url );
    }

    /**
     * Renders custom content.
     *
     * @param string     $type
     * @param string|int $id
     * @param string     $url
     */
    public function custom_type( string $type, $id, string $url ) : void
    {
        $type = Plugin::filter_type( $type );

        if ( is_wp_error( $type ) ) {
            return;
        }

        $object_id = Plugin::filter_custom_id( $type, $id );

        if ( is_wp_error( $object_id ) ) {
            return;
        }

        $this->invoke_lambda( $type, $id, esc_url( $url ) );
    }

    /**
     * Invokes AWS Lambda function.
     *
     * @param string $type
     * @param $id
     * @param string $url
     */
    protected function invoke_lambda( string $type, $id, string $url ) : void
    {
        $lambda = $this->get_lambda();

        $secret = wp_generate_password( 32, true, true );
        $secret_hash = wp_hash_password( $secret );

        set_transient( "innocode_prerender_secret_$type-$id", $secret_hash, 20 * MINUTE_IN_SECONDS );

        $lambda( [
            'type'       => $type,
            'id'         => $id,
            'url'        => $url,
            'selector'   => $this->get_selector(),
            'return_url' => $this->get_return_url(),
            'secret'     => $secret,
        ] );
    }
}
