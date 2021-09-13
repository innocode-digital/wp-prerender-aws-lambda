<?php

namespace Innocode\SSR;

use WP_Query;

/**
 * Class Archive
 *
 * @package InnocodeWP\SSR
 */
class Archive
{
    /**
     * Name of plugin archive option
     */
    const ARCHIVE_OPTION = 'archive_prerender';

    /**
     * Hook that modify archive prerender logic
     */
    const SSR_PRERENDER_HOOK = 'wp_ssr_archive_prerender';

    /**
     * Save rendered content to plugin archive option
     *
     * @param $post_type
     * @param $content
     *
     * @return bool
     */
    public static function save_prerender_option( string $post_type, string $content ): bool
    {
        return update_option( "{$post_type}_" . static::ARCHIVE_OPTION , $content );
    }

    /**
     * Flush plugin archive option with rendered content
     *
     * @param $post_type
     */
    public static function flush_prerender_option( string $post_type ): void
    {
        static::save_prerender_option( $post_type, '' );
    }

    /**
     * @param $post_id
     * @param $post_type
     *
     * @return bool
     */
    public static function is_post_showed_in_archive( int $post_id, string $post_type ): bool
    {
        $query = new WP_Query( [
            'post_type' => $post_type,
            'fields'    => 'ids'
        ] );

        return apply_filters( static::SSR_PRERENDER_HOOK, in_array( $post_id, $query->posts ), $post_id );
    }
}
