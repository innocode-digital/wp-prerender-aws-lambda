<?php

namespace Innocode\SSR;

/**
 * Class Security
 *
 * @package InnocodeWP\SSR
 */
class Security
{
    /**
     * Length of the plugin secret
     */
    private const SECRET_LENGTH = 24;

    /**
     * Plugin secret transient option name
     */
    private const TRANSIENT = 'wp_ssr_secret';

    /**
     * Generate and return secret. Update it every 15 minutes
     *
     * @return string
     */
    private static function get_secret(): string
    {
        if( false === $secret = get_transient( static::TRANSIENT ) ) {
            $secret = wp_generate_password( static::SECRET_LENGTH );
            set_transient( static::TRANSIENT, $secret, 15 * MINUTE_IN_SECONDS );
        }

        return $secret;
    }

    /**
     * Returns secret hash
     *
     * @return string
     */
    public static function get_secret_hash(): string
    {
        return wp_hash_password( static::get_secret() );
    }

    /**
     * Check the plaintext secret with the encrypted hash
     *
     * @param string $secret_hash
     *
     * @return bool
     */
    public static function check_secret_hash( string $secret_hash ): bool
    {
        return wp_check_password( static::get_secret(), $secret_hash );
    }
}
