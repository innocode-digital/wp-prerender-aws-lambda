<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Plugin;

class Frontpage extends AbstractTemplate
{
    /**
     * @inheritDoc
     */
    public function get_name() : string
    {
        return Plugin::TEMPLATE_FRONTPAGE;
    }

    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        return is_front_page() || is_home();
    }

    /**
     * @return int
     */
    public function get_id() : int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        return home_url( '/' );
    }
}
