<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Plugin;
use WP_Term;

class Term extends AbstractTemplate
{
    /**
     * @inheritDoc
     */
    public function get_name() : string
    {
        return Plugin::TEMPLATE_TERM;
    }

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
        if ( ! $id ) {
            return null;
        }

        $term = get_term_by( 'term_taxonomy_id', (int) $id );

        if ( ! $term ) {
            return null;
        }

        $link = get_term_link( $term );

        return ! is_wp_error( $link ) ? $link : null;
    }
}
