<?php

namespace Innocode\SSR;

/**
 * Class Plugin
 *
 * @package InnocodeWP\SSR
 */
class Plugin
{
    /**
     * Hook that activates the plugin
     */
    private const SSR_HOOK = 'wp_ssr_enable_prerender';

    /**
     * Plugin initialization
     */
    public static function register(): void
    {
        add_action( 'init', function() {
            if( apply_filters( static::SSR_HOOK, false ) ) {
                Render::register();
                Rest::register();
            }
        } );
    }
}
