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
            'fields'    => 'ids',
            'tax_query' => [
                [
                    'taxonomy'  => get_term( $term_id )->taxonomy,
                    'terms'     => $term_id
                ]
            ]
        ] );

        return in_array( $post_id, $query->posts );
    }
}
