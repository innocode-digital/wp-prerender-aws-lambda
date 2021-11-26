<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Traits\DbTrait;
use WP_Error;

/**
 * Class Plugin
 *
 * @package Innocode\Prerender
 */
final class Plugin
{
    use DbTrait;

    const TYPE_POST = 'post';
    const TYPE_TERM = 'term';
    const TYPE_AUTHOR = 'author';
    const TYPE_FRONTPAGE = 'frontpage';
    const TYPE_POST_TYPE_ARCHIVE = 'post_type_archive';
    const TYPE_DATE_ARCHIVE = 'date_archive';

    /**
     * @const array Order is important as it's used in render callback.
     */
    const TYPES = [
        self::TYPE_FRONTPAGE,
        self::TYPE_POST_TYPE_ARCHIVE,
        self::TYPE_TERM,
        self::TYPE_POST,
        self::TYPE_AUTHOR,
        self::TYPE_DATE_ARCHIVE,
    ];

    /**
     * @var Prerender
     */
    private $prerender;
    /**
     * @var RESTController
     */
    private $rest_controller;
    /**
     * @var Query
     */
    private $query;

    /**
     * Plugin constructor.
     *
     * @param string      $key
     * @param string      $secret
     * @param string      $region
     * @param string|null $function
     * @param string|null $db_table
     */
    public function __construct(
        string $key,
        string $secret,
        string $region,
        string $function = null,
        string $db_table = null
    )
    {
        $db = new Db();

        if ( null !== $db_table ) {
            $db->set_table( $db_table );
        }

        $prerender = new Prerender( $key, $secret, $region, $function );
        $rest_controller = new RESTController();

        $prerender->set_db( $db );
        $prerender->set_return_url( $rest_controller->url() );
        $rest_controller->set_db( $db );

        $this->db = $db;
        $this->prerender = $prerender;
        $this->rest_controller = $rest_controller;
        $this->query = new Query();
    }

    /**
     * @return Prerender
     */
    public function get_prerender() : Prerender
    {
        return $this->prerender;
    }

    /**
     * @return RESTController
     */
    public function get_rest_controller() : RESTController
    {
        return $this->rest_controller;
    }

    /**
     * @return Query
     */
    public function get_query() : Query
    {
        return $this->query;
    }

    /**
     * Hooks registration.
     */
    public function run()
    {
        // Already in 'init' hook.
        $this->get_db()->init();

        $prerender = $this->get_prerender();

        Helpers::action( 'transition_post_status', [ $prerender, 'update_post' ] );
        Helpers::action( 'delete_post', [ $prerender, 'delete_post' ] );
        Helpers::action( 'saved_term', [ $prerender, 'update_term' ] );
        Helpers::action( 'delete_term', [ $prerender, 'delete_term' ] );

        $query = $this->get_query();

        foreach ( Plugin::get_types() as $type ) {
            if ( in_array( $type, Plugin::TYPES, true ) ) {
                Helpers::action( "innocode_prerender_$type", [ $prerender, $type ] );

                Helpers::action( "innocode_prerender_is_$type", [ $query, "is_$type" ] );
                Helpers::action( "innocode_prerender_{$type}_id", [ $query, "{$type}_id" ] );
            } else {
                Helpers::action( 'innocode_prerender_custom_type', [ $prerender, 'custom_type' ] );
            }
        }

        $rest_controller = $this->get_rest_controller();

        Helpers::action( 'rest_api_init', [ $rest_controller, 'register_routes' ] );
    }

    /**
     * @return array
     */
    public static function get_types() : array
    {
        return apply_filters( 'innocode_prerender_types', Plugin::TYPES );
    }

    /**
     * @param string     $type
     * @param string|int $id
     *
     * @return string|WP_Error
     */
    public function get_html( string $type, $id = 0 )
    {
        $type = Plugin::filter_type( $type );

        if ( is_wp_error( $type ) ) {
            return $type;
        }

        $converted_type_id = Plugin::convert_type_id( $type, $id );

        if ( is_wp_error( $converted_type_id ) ) {
            return $converted_type_id;
        }

        list( $type, $object_id ) = $converted_type_id;

        $entry = $this->get_prerender()->get_db()->get_entry( $type, $object_id );

        return $entry['html'] ?? '';
    }

    public function render()
    {
        foreach ( Plugin::get_types() as $type ) {
            if ( apply_filters( "innocode_prerender_is_$type", false ) ) {
                $id = apply_filters( "innocode_prerender_{$type}_id", 0 );

                echo $this->get_html( $type, $id );

                break;
            }
        }
    }

    /**
     * @param string $type
     *
     * @return string|WP_Error
     */
    public static function filter_type( string $type )
    {
        return in_array( $type, Plugin::get_types(), true )
            ? $type
            : new WP_Error(
                'innocode_prerender_invalid_type',
                __( 'Invalid type.', 'innocode-prerender' )
            );
    }

    /**
     * @param string     $type
     * @param string|int $id
     *
     * @return array|WP_Error
     */
    public static function convert_type_id( string $type, $id = 0 )
    {
        switch ( $type ) {
            case Plugin::TYPE_FRONTPAGE:
                $object_id = 0;

                break;
            case Plugin::TYPE_POST_TYPE_ARCHIVE:
                if ( ! post_type_exists( $id ) ) {
                    return new WP_Error(
                        'innocode_prerender_invalid_id',
                        __( 'Invalid ID.', 'innocode-prerender' )
                    );
                }

                $type .= "_$id";
                $object_id = 0;

                break;
            case Plugin::TYPE_DATE_ARCHIVE:
                $parsed = Helpers::parse_Ymd( $id );

                if ( false === $parsed['year'] ) {
                    // Something wrong as date should always include year.
                    return new WP_Error(
                        'innocode_prerender_invalid_date',
                        __( 'Invalid date.', 'innocode-prerender' )
                    );
                }

                $type .= "_{$parsed['year']}";

                if ( false !== $parsed['month'] ) {
                    $type .= $parsed['month'];

                    if ( false !== $parsed['day'] ) {
                        $type .= $parsed['day'];
                    }
                }

                $object_id = 0;

                break;
            case Plugin::TYPE_POST:
            case Plugin::TYPE_TERM:
            case Plugin::TYPE_AUTHOR:
                $object_id = (int) $id;

                break;
            default:
                $object_id = Plugin::filter_custom_id( $type, $id );

                if ( is_wp_error( $object_id ) ) {
                    return $object_id;
                }

                break;
        }

        return [ $type, $object_id ];
    }

    /**
     * @param string     $type
     * @param string|int $id
     *
     * @return string|int|WP_Error
     */
    public static function filter_custom_id( string $type, $id )
    {
        $object_id = new WP_Error(
            'innocode_prerender_custom_id_not_implemented',
            __( 'Custom object ID is not implemented.', 'innocode-prerender' )
        );

        return apply_filters( 'innocode_prerender_custom_id', $object_id, $type, $id );
    }
}
