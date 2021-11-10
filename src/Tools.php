<?php

namespace Innocode\Prerender;

use WP_Query;

/**
 * Class Tools
 *
 * @package Innocode\Prerender
 */
class Tools
{
    /**
     * @param int $post_id
     * @param string $post_type
     *
     * @return bool
     */
    public static function is_post_showed_in_archive( int $post_id, string $post_type ): bool
    {
        $query = new WP_Query( [
            'post_type' => $post_type,
            'fields'    => 'ids'
        ] );

        return apply_filters( 'innocode_prerender_showed_in_archive', in_array( $post_id, $query->posts ), $post_id );
    }

    /**
     * @param int $post_id
     * @param int $term_id
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

        return apply_filters( 'innocode_prerender_showed_in_term', in_array( $post_id, $query->posts ), $post_id );
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public static function check_type( string $type ): bool
    {
        $post_types = get_post_types();

        foreach( $post_types as $post_type ) {
            if( get_post_type_archive_link( $post_type ) ) {
                $types[] = "archive_$post_type";
            }
        }

        $types = apply_filters( 'innocode_prerender_types', array_merge(
            [
                'post',
                'term',
                'frontpage'
            ],
            $types )
        );

        return in_array( $type, $types );
    }
}
