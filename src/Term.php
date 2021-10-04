<?php

namespace Innocode\SSR;

use WP_Query;

/**
 * Class Term
 *
 * @package InnocodeWP\SSR
 */
class Term
{
    /**
     * Name of plugin term meta
     */
    const TERM_META = 'prerender';

    /**
     * Hook that modify term prerender logic
     */
    const SSR_PRERENDER_HOOK = 'wp_ssr_term_prerender';

    /**
     * Save rendered content to plugin term meta
     *
     * @param $post_type
     * @param $content
     *
     * @return bool
     */
    public static function save_prerender_meta( int $term_id, string $content ): bool
    {
        return (bool) update_term_meta( $term_id, static::TERM_META , $content );
    }

    /**
     * Flush plugin term meta with rendered content
     *
     * @param $term_id
     */
    public static function flush_prerender_meta( int $term_id ): void
    {
        static::save_prerender_meta( $term_id, '' );
    }

    /**
     * @param $post_id
     * @param $term_id
     *
     * @return bool
     */
    public static function is_post_showed_in_term( int $post_id, int $term_id ): bool
    {
        $query = new WP_Query( [
            'post_type' => get_post_type( $post_id ),
            'fields'    => 'ids',
            'tax_query' => [
                [
                    'taxonomy'  => get_term( $term_id )->taxonomy,
                    'terms'     => $term_id
                ]
            ]
        ] );

        return apply_filters( static::SSR_PRERENDER_HOOK, in_array( $post_id, $query->posts ), $post_id );
    }
}
