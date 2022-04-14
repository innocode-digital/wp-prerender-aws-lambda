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
     * @param string $template
     * @param string $id
     * @return array
     */
    public static function init( string $template, string $id ) : array
    {
        list( $secret, $hash ) = static::generate();

        $is_set = static::set( $template, $id, $hash );

        return [ $is_set, $secret ];
    }

    /**
     * @return array
     */
    public static function generate() : array
    {
        $secret = wp_generate_password( 32 );
        $hash = wp_hash_password( $secret );

        return [ $secret, $hash ];
    }

    /**
     * @param string $template
     * @param string $id
     * @param string $hash
     *
     * @return bool
     */
    public static function set( string $template, string $id, string $hash ) : bool
    {
        return static::force_db_transient( static::METHOD_SET, $template, $id, $hash, static::EXPIRATION );
    }

    /**
     * @param string $template
     * @param string $id
     *
     * @return string|false
     */
    public static function get( string $template, string $id )
    {
        return static::force_db_transient( static::METHOD_GET, $template, $id );
    }

    /**
     * @param string $template
     * @param string $id
     *
     * @return bool
     */
    public static function delete( string $template, string $id ) : bool
    {
        return static::force_db_transient( static::METHOD_DELETE, $template, $id );
    }

    /**
     * Forces DB transient to make sure that it will be stored and to have possibility to expire.
     *
     * @param string $method
     * @param string $template
     * @param string $id
     * @param ...$args
     *
     * @return mixed
     */
    protected static function force_db_transient( string $method, string $template, string $id, ...$args )
    {
        $using_ext_object_cache = wp_using_ext_object_cache( false );

        $function = "{$method}_transient";
        $result = $function( static::key( $template, $id ), ...$args );

        wp_using_ext_object_cache( $using_ext_object_cache );

        return $result;
    }

    /**
     * @param string $template
     * @param string $id
     *
     * @return string
     */
    public static function key( string $template, string $id ) : string
    {
        return static::PREFIX . "$template-$id";
    }

    /**
     * @return bool
     */
    public static function flush() : bool
    {
        global $wpdb;

        return (bool) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . static::PREFIX ) . '%'
            )
        );
    }

    /**
     * @return bool
     */
    public static function flush_expired() : bool
    {
        if ( ! wp_using_ext_object_cache() ) {
            return false;
        }

        global $wpdb;

        return (bool) $wpdb->query(
            $wpdb->prepare(
                "DELETE a, b FROM $wpdb->options a, $wpdb->options b
                WHERE a.option_name LIKE %s
                AND a.option_name NOT LIKE %s
                AND b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
                AND b.option_value < %d",
                $wpdb->esc_like( '_transient_' . static::PREFIX ) . '%',
                $wpdb->esc_like( '_transient_timeout_' . static::PREFIX ) . '%',
                time()
            )
        );
    }
}
