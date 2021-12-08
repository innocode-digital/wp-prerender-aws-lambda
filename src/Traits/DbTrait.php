<?php

namespace Innocode\Prerender\Traits;

use Innocode\Prerender\Db;

trait DbTrait
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @param Db $db
     *
     * @return void
     */
    public function set_db( Db $db ) : void
    {
        $this->db = $db;
    }

    /**
     * @return Db
     */
    public function get_db() : Db
    {
        return $this->db;
    }
}
