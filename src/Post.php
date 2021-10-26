<?php

namespace Innocode\Prerender;

/**
 * Class Post
 *
 * @package InnocodeWP\Prerender
 */
class Post
{
    /**
     * Name of plugin post meta
     */
    const POST_META = 'prerender';

    /**
     * Save rendered content to plugin post meta
     *
     * @param $post_id
     * @param $content
     *
     * @return bool
     */
    public static function save_prerender_meta( int $post_id, string $content ): bool
    {
        return (bool) update_post_meta( $post_id, static::POST_META, $content );
    }

    /**
     * Flush plugin post meta with rendered content
     *
     * @param $post_id
     */
    public static function flush_prerender_meta( int $post_id ): void
    {
        static::save_prerender_meta( $post_id, '' );
    }
}
