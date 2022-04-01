<?php

namespace Innocode\Prerender\Interfaces;

interface TemplateInterface
{
    /**
     * @return bool
     */
    public function is_queried() : bool;

    /***
     * @return mixed
     */
    public function get_id();

    /**
     * @param string|int $id
     * @return string|null
     */
    public function get_link( $id = 0 ) : ?string;
}
