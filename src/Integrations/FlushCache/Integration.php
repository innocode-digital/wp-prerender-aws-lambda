<?php

namespace Innocode\Prerender\Integrations\FlushCache;

use Innocode\Prerender\Interfaces\IntegrationInterface;
use Innocode\Prerender\Plugin;
use Innocode\Prerender\SecretsManager;

class Integration implements IntegrationInterface
{
    /**
     * @inheritDoc
     */
    public function run( Plugin $plugin ) : void
    {
        $db = $plugin->get_db();

        $bump_html_version = [ $db->get_html_version(), 'bump' ];
        $flush_secrets = [ SecretsManager::class, 'flush' ];
        $clean_db = [ $db, 'drop_table' ];

        if ( function_exists( 'flush_cache_add_button' ) ) {
            flush_cache_add_button(
                __( 'Prerender version', 'innocode-prerender' ),
                $bump_html_version
            );
            flush_cache_add_button(
                __( 'Prerender secrets', 'innocode-prerender' ),
                $flush_secrets
            );
            flush_cache_add_button(
                __( 'Prerender database', 'innocode-prerender' ),
                $clean_db
            );
        }

        if ( function_exists( 'flush_cache_add_sites_action_link' ) ) {
            flush_cache_add_sites_action_link(
                __( 'Prerender version', 'innocode-prerender' ),
                $bump_html_version
            );
            flush_cache_add_sites_action_link(
                __( 'Prerender secrets', 'innocode-prerender' ),
                $flush_secrets
            );
            flush_cache_add_sites_action_link(
                __( 'Prerender database', 'innocode-prerender' ),
                $clean_db
            );
        }
    }
}
