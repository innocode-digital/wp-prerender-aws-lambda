<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Traits\DbTrait;
use WP_Post;

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
    protected $variable = 'innocodePrerender';
    /**
     * @var string
     */
    protected $selector = '#app';
    /**
     * @var string
     */
    protected $return_url;
    /**
     * @var string
     */
    protected $query_arg;

    /**
     * Prerender constructor.
     *
     * @param string $key
     * @param string $secret
     * @param string $region
     */
    public function __construct( string $key, string $secret, string $region )
    {
        $this->lambda = new Lambda( $key, $secret, $region );
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
    public function get_variable() : string
    {
        return apply_filters( 'innocode_prerender_variable', $this->variable );
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
     *
     * @return void
     */
    public function set_return_url( string $return_url ) : void
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
     * @param string $query_arg
     *
     * @return void
     */
    public function set_query_arg( string $query_arg ) : void
    {
        $this->query_arg = $query_arg;
    }

    /**
     * @return string
     */
    public function get_query_arg() : string
    {
        return $this->query_arg;
    }

    /**
     * Updates Post/Page HTML.
     *
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     *
     * @return void
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

        do_action( 'innocode_prerender_pre_update_post', $post->ID );

        $this->schedule_post( $post->ID );
        $this->update_post_related( $post->ID );

        do_action( 'innocode_prerender_update_post', $post->ID );
    }

    /**
     * Updates Term HTML.
     *
     * @param int    $term_id
     * @param int    $tt_id
     * @param string $taxonomy_name
     *
     * @return void
     */
    public function update_term( int $term_id, int $tt_id, string $taxonomy_name ) : void
    {
        $taxonomy = get_taxonomy( $taxonomy_name );

        if ( ! $taxonomy || ! $taxonomy->public ) {
            return;
        }

        do_action( 'innocode_prerender_pre_update_term', $tt_id );

        $this->schedule_term( $tt_id );
        $this->update_term_related( $tt_id );

        do_action( 'innocode_prerender_update_term', $tt_id );
    }

    /**
     * @param int $post_id
     *
     * @return void
     */
    public function delete_post( int $post_id ) : void
    {
        do_action( 'innocode_prerender_pre_delete_post', $post_id );

        if ( $this->get_db()->delete_entry( 'post', $post_id ) ) {
            $this->update_post_related( $post_id );
        }

        do_action( 'innocode_prerender_delete_post', $post_id );
    }

    /**
     * @param int $term_taxonomy_id
     *
     * @return void
     */
    public function delete_term( int $term_taxonomy_id ) : void
    {
        do_action( 'innocode_prerender_pre_delete_term', $term_taxonomy_id );

        if ( $this->get_db()->delete_entry( 'term', $term_taxonomy_id ) ) {
            $this->update_term_related( $term_taxonomy_id );
        }

        do_action( 'innocode_prerender_delete_term', $term_taxonomy_id );
    }

    /**
     * @param int $post_id
     *
     * @return void
     */
    public function update_post_related( int $post_id ) : void
    {
        if ( $this->should_update_post_related( $post_id, Plugin::TYPE_FRONTPAGE ) ) {
            $this->schedule_frontpage();
        }

        $user_id = get_post_field( 'post_author', $post_id );

        if ( $this->should_update_post_related( $post_id, Plugin::TYPE_AUTHOR, $user_id ) ) {
            $this->schedule_author( $user_id );
        }

        $post_type = get_post_type( $post_id );

        $post_type_archive_link = get_post_type_archive_link( $post_type );

        if (
            $post_type_archive_link &&
            untrailingslashit( $post_type_archive_link ) != untrailingslashit( home_url() ) &&
            $this->should_update_post_related( $post_id, Plugin::TYPE_POST_TYPE_ARCHIVE, $post_type )
        ) {
            $this->schedule_post_type_archive( $post_type );
        }

        if ( 'post' == $post_type ) {
            $year = get_the_date( 'Y', $post_id );
            $month = get_the_date( 'm', $post_id );
            $day = get_the_date( 'd', $post_id );

            if ( $this->should_update_post_related( $post_id, Plugin::TYPE_DATE_ARCHIVE, $year . $month . $day ) ) {
                $this->schedule_date_archive( $year );
                $this->schedule_date_archive( $year . $month );
                $this->schedule_date_archive( $year . $month . $day );
            }
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
                if ( $this->should_update_post_related( $post_id, Plugin::TYPE_TERM, $term->term_taxonomy_id ) ) {
                    $this->schedule_term( $term->term_taxonomy_id );
                }
            }
        }
    }

    /**
     * @param int $term_taxonomy_id
     *
     * @return void
     */
    public function update_term_related( int $term_taxonomy_id ) : void
    {
        if ( $this->should_update_term_related( $term_taxonomy_id, Plugin::TYPE_FRONTPAGE ) ) {
            $this->schedule_frontpage();
        }

        // @TODO: What should we do if post shows term data e.g. name somewhere in content?
    }

    /**
     * @param int        $post_id
     * @param string     $related
     * @param string|int $id
     *
     * @return bool
     */
    public function should_update_post_related( int $post_id, string $related, $id = 0 ) : bool
    {
        return $this->should_update_related( Plugin::TYPE_POST, $post_id, $related, $id );
    }

    /**
     * @param int        $term_taxonomy_id
     * @param string     $related
     * @param string|int $id
     *
     * @return bool
     */
    public function should_update_term_related( int $term_taxonomy_id, string $related, $id = 0 ) : bool
    {
        return $this->should_update_related( Plugin::TYPE_TERM, $term_taxonomy_id, $related, $id );
    }

    /**
     * @param string     $type
     * @param int        $object_id
     * @param string     $related
     * @param string|int $id
     *
     * @return bool
     */
    public function should_update_related( string $type, int $object_id, string $related, $id = 0 ) : bool
    {
        return (bool) apply_filters( "innocode_prerender_should_update_{$type}_$related", true, $object_id, $id );
    }

    /**
     * Prerenders Post/Page.
     *
     * @param int $post_id
     *
     * @return void
     */
    public function schedule_post( int $post_id ) : void
    {
        $this->schedule( Plugin::TYPE_POST, $post_id );
    }

    /**
     * Prerenders Term.
     *
     * @param int $term_taxonomy_id
     *
     * @return void
     */
    public function schedule_term( int $term_taxonomy_id ) : void
    {
        $this->schedule( Plugin::TYPE_TERM, $term_taxonomy_id );
    }

    /**
     * Prerenders Author Page.
     *
     * @param int $user_id
     *
     * @return void
     */
    public function schedule_author( int $user_id ) : void
    {
        $this->schedule( Plugin::TYPE_AUTHOR, $user_id );
    }

    /**
     * Prerenders Frontpage.
     *
     * @return void
     */
    public function schedule_frontpage() : void
    {
        $this->schedule( Plugin::TYPE_FRONTPAGE );
    }

    /**
     * Prerenders Post Type Archive.
     *
     * @param string $post_type
     *
     * @return void
     */
    public function schedule_post_type_archive( string $post_type ) : void
    {
        $this->schedule( Plugin::TYPE_POST_TYPE_ARCHIVE, $post_type );
    }

    /**
     * Prerenders Date Archive.
     *
     * @param string $date
     *
     * @return void
     */
    public function schedule_date_archive( string $date ) : void
    {
        $this->schedule( Plugin::TYPE_DATE_ARCHIVE, $date );
    }

    /**
     * @param string     $type
     * @param string|int $id
     * @param array      $args
     *
     * @return void
     */
    public function schedule( string $type, $id = 0, array $args = [] ) : void
    {
        $type = Plugin::filter_type( $type );

        if ( is_wp_error( $type ) ) {
            return;
        }

        $object_id = is_int( $id ) ? $id : 0;
        $subtype = is_string( $id ) ? $id : '';

        if ( $object_id ) {
            array_unshift( $args, $object_id );
        }

        if ( $subtype ) {
            array_unshift( $args, $subtype );
        }

        array_unshift( $args, $type );

        if ( wp_next_scheduled( 'innocode_prerender', $args ) ) {
            return;
        }

        $this->get_db()->clear_entry( $type . ( $subtype ? "_$subtype" : '' ), $object_id );

        wp_schedule_single_event( time(), 'innocode_prerender', $args );
    }

    /**
     * Invokes AWS Lambda function.
     *
     * @param string     $type
     * @param string|int $id
     * @param ...$args
     *
     * @return void
     */
    public function invoke_lambda( string $type, $id = 0, ...$args ) : void
    {
        $type = Plugin::filter_type( $type );

        if ( is_wp_error( $type ) ) {
            return;
        }

        $url = apply_filters( "innocode_prerender_{$type}_url", '', $id );

        error_log( print_r( [ $type, $id, $url ], true ) );

        if ( ! $url ) {
            return;
        }

        list( $is_secret_set, $secret ) = SecretsManager::init( $type, (string) $id );

        error_log( print_r( [ $is_secret_set, $secret ], true ) );

        if ( ! $is_secret_set ) {
            return;
        }

        $html_version = $this->get_db()->get_html_version();
        $lambda = $this->get_lambda();

        error_log( print_r( wp_parse_args( $args, [
            'type'       => $type,
            'id'         => $id,
            'url'        => add_query_arg( $this->get_query_arg(), 'true', esc_url( $url ) ),
            'variable'   => $this->get_variable(),
            'selector'   => $this->get_selector(),
            'return_url' => $this->get_return_url(),
            'secret'     => $secret,
            'version'    => $html_version(),
        ] ), true ) );

        $lambda( wp_parse_args( $args, [
            'type'       => $type,
            'id'         => $id,
            'url'        => add_query_arg( $this->get_query_arg(), 'true', esc_url( $url ) ),
            'variable'   => $this->get_variable(),
            'selector'   => $this->get_selector(),
            'return_url' => $this->get_return_url(),
            'secret'     => $secret,
            'version'    => $html_version(),
        ] ) );
    }
}
