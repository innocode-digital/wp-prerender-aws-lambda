<?php

namespace Innocode\Prerender;

use DateTime;
use Exception;

class Entry
{
    /**
     * @var int
     */
    protected $id = 0;
    /**
     * @var DateTime
     */
    protected $created;
    /**
     * @var DateTime
     */
    protected $updated;
    /**
     * @var string
     */
    protected $type = '';
    /**
     * @var int
     */
    protected $object_id = 0;
    /**
     * @var string
     */
    protected $html = '';
    /**
     * @var string
     */
    protected $version = '';

    /**
     * @param array $data
     */
    public function __construct( array $data )
    {
        if ( isset( $data['ID'] ) ) {
            $this->id = (int) $data['ID'];
        }

        if ( isset( $data['created'] ) ) {
            try {
                $this->created = new DateTime( $data['created'], wp_timezone() );
            } catch ( Exception $exception ) {}
        }

        if ( isset( $data['updated'] ) ) {
            try {
                $this->updated = new DateTime( $data['updated'], wp_timezone() );
            } catch ( Exception $exception ) {}
        }

        if ( isset( $data['type'] ) ) {
            $this->type = $data['type'];
        }

        if ( isset( $data['object_id'] ) ) {
            $this->object_id = (int) $data['object_id'];
        }

        if ( isset( $data['html'] ) ) {
            $this->html = $data['html'];
        }

        if ( isset( $data['version'] ) ) {
            $this->version = $data['version'];
        }
    }

    /**
     * @return int
     */
    public function get_id() : int
    {
        return $this->id;
    }

    /**
     * @return DateTime|null
     */
    public function get_created() : ?DateTime
    {
        return $this->created;
    }

    /**
     * @return DateTime|null
     */
    public function get_updated() : ?DateTime
    {
        return $this->updated;
    }

    /**
     * @return string
     */
    public function get_type() : string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function get_object_id() : int
    {
        return $this->object_id;
    }

    /**
     * @return string
     */
    public function get_html() : string
    {
        return $this->html;
    }

    /**
     * @return string
     */
    public function get_version() : string
    {
        return $this->version;
    }

    /**
     * @return bool
     */
    public function has_version() : bool
    {
        return '' !== $this->get_version();
    }
}
