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
     *
     * @return void
     */
    public function set_option( string $option ) : void
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

    /**
     * @return bool
     */
    public function init() : bool
    {
        if ( null !== $this() ) {
            return false;
        }

        return $this->bump();
    }

    /**
     * @return bool
     */
    public function bump() : bool
    {
        return $this->update( static::generate() );
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    public function update( string $value ) : bool
    {
        return update_option( $this->get_option(), $value );
    }

    /**
     * @return bool
     */
    public function delete() : bool
    {
        return delete_option( $this->get_option() );
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
