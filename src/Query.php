<?php

namespace Innocode\Prerender;

use WP_Term;

class Query
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @param string $name
     *
     * @return void
     */
    public function set_name( string $name ) : void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function get_name() : string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function is_var_exists() : ?string
    {
        return null !== get_query_var( $this->get_name(), null );
    }

    /**
     * @param array $public_query_vars
     *
     * @return array
     */
    public function add_query_vars( array $public_query_vars ) : array
    {
        $public_query_vars[] = $this->get_name();

        return $public_query_vars;
    }

    /**
     * @return bool
     */
    public function is_post() : bool
    {
        return is_singular();
    }

    /**
     * @return bool
     */
    public function is_term() : bool
    {
        return is_category() || is_tag() || is_tax();
    }

    /**
     * @return bool
     */
    public function is_author() : bool
    {
        return is_author();
    }

    /**
     * @return bool
     */
    public function is_frontpage() : bool
    {
        return is_front_page() || is_home();
    }

    /**
     * @return bool
     */
    public function is_post_type_archive() : bool
    {
        return is_post_type_archive();
    }

    /**
     * @return bool
     */
    public function is_date_archive() : bool
    {
        return is_year() || is_month() || is_day();
    }

    /**
     * @return int
     */
    public function post_id() : int
    {
        return (int) get_the_ID();
    }

    /**
     * @return int
     */
    public function term_id() : int
    {
        $term = get_queried_object();

        return $term instanceof WP_Term ? $term->term_taxonomy_id : 0;
    }

    /**
     * @return int
     */
    public function author_id() : int
    {
        global $authordata;

        return $authordata->ID ?? 0;
    }

    /**
     * @return int
     */
    public function frontpage_id() : int
    {
        return 0;
    }

    /**
     * @return string
     */
    public function post_type_archive_id() : string
    {
        return get_query_var( 'post_type' );
    }

    /**
     * @return false|string
     */
    public function date_archive_id()
    {
        if ( is_day() ) {
            return get_the_date( 'Y' );
        } elseif ( is_month() ) {
            return get_the_date( 'm' );
        } elseif ( is_year() ) {
            return get_the_date( 'd' );
        }

        return false;
    }
}
