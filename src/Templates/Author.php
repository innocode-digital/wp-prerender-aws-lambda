<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Interfaces\TemplateInterface;

class Author implements TemplateInterface
{
    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        return is_author();
    }

    /**
     * @return int
     */
    public function get_id() : int
    {
        global $authordata;

        return $authordata->ID ?? 0;
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        return is_int( $id ) && $id ? get_author_posts_url( $id ) : null;
    }
}
