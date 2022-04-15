<?php

namespace Innocode\Prerender\Abstracts;

abstract class AbstractTemplate
{
    /**
     * Unique name of template.
     *
     * @return string
     */
    abstract public function get_name() : string;

    /**
     * Converts template to type (string) and id (int) to store object in database.
     *
     * @param string|int $id
     * @return array|null
     */
    public function get_type_id_pair( $id = 0 ) : ?array
    {
        return [ $this->get_name(), (int) $id ];
    }

    /**
     * Whether template is currently queried.
     *
     * @return bool
     */
    abstract public function is_queried() : bool;

    /**
     * Returns object id of currently queried template.
     *
     * @return mixed
     */
    abstract public function get_id();

    /**
     * Returns link to object.
     *
     * @param string|int $id
     * @return string|null
     */
    abstract public function get_link( $id = 0 ) : ?string;
}
