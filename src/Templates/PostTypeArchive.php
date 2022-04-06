<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Interfaces\TemplateInterface;

class PostTypeArchive implements TemplateInterface
{
    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        return is_post_type_archive();
    }

    /**
     * @return string
     */
    public function get_id() : string
    {
        return get_query_var( 'post_type' );
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        return is_string( $id ) && $id && false !== ( $link = get_post_type_archive_link( $id ) )
            ? $link
            : null;
    }
}
