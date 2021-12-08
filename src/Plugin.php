<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Traits\DbTrait;
use WP_Error;

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
     * @note Order is important as it's used in render callback.
     *
     * @const array
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
     * @param string $key
     * @param string $secret
     * @param string $region
     */
    public function __construct( string $key, string $secret, string $region )
    {
        $db = new Db();
        $prerender = new Prerender( $key, $secret, $region );
        $rest_controller = new RESTController();

        $prerender->set_db( $db );
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
     *
     * @return void
     */
    public function run() : void
    {
        Helpers::hook( 'plugins_loaded', [ $this, 'add_flush_cache_actions' ] );
        Helpers::hook( 'init', [ $this->get_db(), 'init' ] );
        Helpers::hook( 'init', [ $this, 'init' ] );
        Helpers::hook( 'rest_api_init', [ $this->get_rest_controller(), 'register_routes' ] );
        Helpers::hook( 'wp_head', [ $this, 'print_scripts' ], 1 );

        $prerender = $this->get_prerender();

        Helpers::hook( 'transition_post_status', [ $prerender, 'update_post' ] );
        Helpers::hook( 'delete_post', [ $prerender, 'delete_post' ] );
        Helpers::hook( 'saved_term', [ $prerender, 'update_term' ] );
        Helpers::hook( 'delete_term', [ $prerender, 'delete_term' ] );
    }

    /**
     * @return void
     */
    public function add_flush_cache_actions() : void
    {
        $bump_html_version = [ $this->get_db()->get_html_version(), 'bump' ];
        $flush_secrets = [ SecretsManager::class, 'flush' ];

        if ( function_exists( 'flush_cache_add_button' ) ) {
            flush_cache_add_button(
                __( 'Prerender version', 'innocode-prerender' ),
                $bump_html_version
            );
            flush_cache_add_button(
                __( 'Prerender secrets', 'innocode-prerender' ),
                $flush_secrets
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
        }
    }

    /**
     * @return void
     */
    public function init() : void
    {
        $prerender = $this->get_prerender();
        $rest_controller = $this->get_rest_controller();
        $query = $this->get_query();

        $prerender->set_return_url( $rest_controller->url() );
        $prerender->set_query_arg( $query->get_name() );

        foreach ( Plugin::get_types() as $type ) {
            if ( in_array( $type, Plugin::TYPES, true ) ) {
                Helpers::hook( "innocode_prerender_$type", [ $prerender, $type ] );

                Helpers::hook( "innocode_prerender_is_$type", [ $query, "is_$type" ] );
                Helpers::hook( "innocode_prerender_{$type}_id", [ $query, "{$type}_id" ] );
            } else {
                Helpers::hook( 'innocode_prerender_custom_type', [ $prerender, 'custom_type' ] );
            }
        }
    }

    /**
     * @return array
     */
    public static function get_types() : array
    {
        return apply_filters( 'innocode_prerender_types', Plugin::TYPES );
    }

    /**
     * @return void
     */
    public function render() : void
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

        $prerender = $this->get_prerender();
        $db = $prerender->get_db();
        $entry = $db->get_entry( $type, $object_id );
        $html_version = $db->get_html_version();

        if ( ! isset( $entry['version'] ) || $html_version() !== $entry['version'] ) {
            $prerender->schedule( $type, $object_id );

            return '';
        }

        return $entry['html'] ?? '';
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
     * @return int|WP_Error
     */
    public static function filter_custom_id( string $type, $id )
    {
        $object_id = new WP_Error(
            'innocode_prerender_custom_id_not_implemented',
            __( 'Custom object ID is not implemented.', 'innocode-prerender' )
        );

        return apply_filters( 'innocode_prerender_custom_id', $object_id, $type, $id );
    }

    /**
     * @return void
     */
    public function print_scripts() : void
    {
        if ( ! $this->get_query()->is_exists() ) {
            return;
        }

        echo "<script>window.__INNOCODE_PRERENDER__ = true;</script>\n";
    }
}
