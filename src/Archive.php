<?php

namespace Innocode\Prerender;

use WP_Query;

/**
 * Class Archive
 *
 * @package InnocodeWP\Prerender
 */
class Archive
{
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
        return wp_cache_set( "{$post_type}_archive_prerender", $content );
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

        return apply_filters( 'wp_archive_prerender', in_array( $post_id, $query->posts ), $post_id );
    }
}
