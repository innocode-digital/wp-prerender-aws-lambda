<?php

namespace Innocode\Prerender;

/**
 * Class Plugin
 *
 * @package InnocodeWP\Prerender
 */
class Plugin
{
    /**
     * Plugin initialization
     */
    public static function register(): void
    {
        add_action( 'init', function() {
            if( apply_filters( 'wp_enable_prerender', false ) ) {
                Render::register();
                Rest::register();
            }
        } );
    }
}
