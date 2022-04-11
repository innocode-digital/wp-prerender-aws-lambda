<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Interfaces\TemplateInterface;

class Post implements TemplateInterface
{
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
        error_log( print_r( [ $id, is_int( $id ), get_permalink( $id ) ], true ) );

        return is_int( $id ) && $id && false !== ( $link = get_permalink( $id ) )
            ? $link
            : null;
    }
}
