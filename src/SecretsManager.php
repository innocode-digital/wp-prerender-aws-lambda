<?php

namespace Innocode\Prerender;

class SecretsManager
{
    const METHOD_SET = 'set';
    const METHOD_GET = 'get';
    const METHOD_DELETE = 'delete';

    const PREFIX = 'innocode_prerender_secret_';

    const EXPIRATION = 20 * MINUTE_IN_SECONDS;

    /**
     * @param string $type
     * @param string $id
     * @return array
     */
    public static function init( string $type, string $id ) : array
    {
        list( $secret, $hash ) = static::generate();

        $is_set = static::set( $type, $id, $hash );

        return [ $is_set, $secret ];
    }

    /**
     * @param string $type
     * @param string $id
     * @param string $hash
     *
     * @return bool
     */
    public static function set( string $type, string $id, string $hash ) : bool
    {
        return static::force_db_transient( static::METHOD_SET, $type, $id, $hash, static::EXPIRATION );
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return string|false
     */
    public static function get( string $type, string $id )
    {
        return static::force_db_transient( static::METHOD_GET, $type, $id );
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return bool
     */
    public static function delete( string $type, string $id ) : bool
    {
        return static::force_db_transient( static::METHOD_DELETE, $type, $id );
    }

    /**
     * Forces DB transient to make sure that it will be stored and to have possibility to expire.
     *
     * @param string $method
     * @param string $type
     * @param string $id
     * @param ...$args
     *
     * @return mixed
     */
    protected static function force_db_transient( string $method, string $type, string $id, ...$args )
    {
        $using_ext_object_cache = wp_using_ext_object_cache( false );

        $function = "{$method}_transient";
        $result = $function( static::key( $type, $id ), ...$args );

        wp_using_ext_object_cache( $using_ext_object_cache );

        return $result;
    }

    /**
     * @param string $type
     * @param string $id
     *
     * @return string
     */
    public static function key( string $type, string $id ) : string
    {
        return static::PREFIX . "$type-$id";
    }

    /**
     * @return array
     */
    public static function generate() : array
    {
        $secret = wp_generate_password( 32, true, true );
        $hash = wp_hash_password( $secret );

        return [ $secret, $hash ];
    }
}
