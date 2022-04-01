<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Interfaces\TemplateInterface;
use WP_Term;

class Term implements TemplateInterface
{
    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        return is_category() || is_tag() || is_tax();
    }

    /**
     * @return int
     */
    public function get_id() : int
    {
        $term = get_queried_object();

        return $term instanceof WP_Term ? $term->term_taxonomy_id : 0;
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        if ( ! is_int( $id ) || ! $id ) {
            return null;
        }

        $term = get_term_by( 'term_taxonomy_id', $id );

        if ( ! $term ) {
            return null;
        }

        $link = get_term_link( $term );

        return ! is_wp_error( $link ) ? $link : null;
    }
}
