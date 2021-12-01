<?php

namespace Innocode\Prerender;

class Version
{
    /**
     * @var string
     */
    protected $option;

    /**
     * @param string $option
     */
    public function set_option( string $option )
    {
        $this->option = $option;
    }

    /**
     * @return string
     */
    public function get_option() : string
    {
        return $this->option;
    }

    public function init()
    {
        if ( null === $this() ) {
            $this->bump();
        }
    }

    public function bump()
    {
        $this->update( static::generate() );
    }

    /**
     * @param string $value
     */
    public function update( string $value )
    {
        update_option( $this->get_option(), $value );
    }

    /**
     * @return string|null
     */
    public function __invoke() : ?string
    {
        $option = $this->get_option();

        if ( ! $option ) {
            return null;
        }

        return get_option( $this->get_option(), null );
    }

    /**
     * @return string
     */
    public static function generate() : string
    {
        return md5( time() );
    }
}
