<?php

namespace Innocode\Prerender\Abstracts;

abstract class AbstractTemplate
{
    /**
     * @return string
     */
    abstract public function get_name() : string;

    /**
     * @param string|int $id
     * @return array|null
     */
    public function get_type_id_pair( $id = 0 ) : ?array
    {
        return [ $this->get_name(), (int) $id ];
    }

    /**
     * @return bool
     */
    abstract public function is_queried() : bool;

    /**
     * @return mixed
     */
    abstract public function get_id();

    /**
     * @param string|int $id
     * @return string|null
     */
    abstract public function get_link( $id = 0 ) : ?string;
}
