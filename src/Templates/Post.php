<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Plugin;

class Post extends AbstractTemplate
{
    /**
     * @inheritDoc
     */
    public function get_name() : string
    {
        return Plugin::TEMPLATE_POST;
    }

    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        return is_singular();
    }

    /**
     * @return int
     */
    public function get_id() : int
    {
        return (int) get_the_ID();
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        return $id && false !== ( $link = get_permalink( (int) $id ) ) ? $link : null;
    }
}
