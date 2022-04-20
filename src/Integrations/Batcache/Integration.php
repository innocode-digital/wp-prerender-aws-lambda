<?php

namespace Innocode\Prerender\Integrations\Batcache;

use Innocode\Prerender\Entry;
use Innocode\Prerender\Helpers;
use Innocode\Prerender\Interfaces\IntegrationInterface;
use Innocode\Prerender\Plugin;

class Integration implements IntegrationInterface
{
    /**
     * @var Plugin
     */
    protected $plugin;

    /**
     * @return Plugin
     */
    public function get_plugin() : Plugin
    {
        return $this->plugin;
    }

    /**
     * @inheritDoc
     */
    public function run( Plugin $plugin ) : void
    {
        $this->plugin = $plugin;

        Helpers::hook( 'innocode_prerender_callback', [ $this, 'flush' ], PHP_INT_MAX );
    }

    /**
     * @param Entry|null $entry
     * @param string     $template_name
     * @param string     $id
     * @return Entry|null
     */
    public function flush( ?Entry $entry, string $template_name, string $id ) : ?Entry
    {
        error_log( print_r( [
            $entry,
            $template_name,
            $id,
            function_exists( 'batcache_clear_url' ),
            $this->get_plugin()->find_template( $template_name )
        ], true ) );

        if (
            ! function_exists( 'batcache_clear_url' ) ||
            ! ( $entry instanceof Entry ) ||
            null === ( $template = $this->get_plugin()->find_template( $template_name ) ) ||
            null === ( $url = $template->get_link( $id ) )
        ) {
            return $entry;
        }

        error_log( print_r( [
            $url,
            batcache_clear_url( $url ),
        ], true ) );

        return $entry;
    }
}
