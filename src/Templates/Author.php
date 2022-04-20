<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Plugin;

class Author extends AbstractTemplate
{
    /**
     * @inheritDoc
     */
    public function get_name() : string
    {
        return Plugin::TEMPLATE_AUTHOR;
    }

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
        return $id ? get_author_posts_url( (int) $id ) : null;
    }
}
