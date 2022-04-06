<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Interfaces\IntegrationInterface;
use Innocode\Prerender\Interfaces\TemplateInterface;
use Innocode\Prerender\Traits\DbTrait;
use Innocode\Prerender\Templates\Author;
use Innocode\Prerender\Templates\DateArchive;
use Innocode\Prerender\Templates\Frontpage;
use Innocode\Prerender\Templates\Post;
use Innocode\Prerender\Templates\PostTypeArchive;
use Innocode\Prerender\Templates\Term;
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

    const INTEGRATION_FLUSH_CACHE = 'flush_cache';
    const INTEGRATION_POLYLANG = 'polylang';

    /**
     * @var Prerender
     */
    protected $prerender;
    /**
     * @var RESTController
     */
    private $rest_controller;
    /**
     * @var Query
     */
    private $query;
    /**
     * @var TemplateInterface[]
     */
    private $templates = [];
    /**
     * @var IntegrationInterface[]
     */
    private $integrations = [];

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

        $this->templates[ Plugin::TYPE_POST ] = new Post();
        $this->templates[ Plugin::TYPE_TERM ] = new Term();
        $this->templates[ Plugin::TYPE_AUTHOR ] = new Author();
        $this->templates[ Plugin::TYPE_FRONTPAGE ] = new Frontpage();
        $this->templates[ Plugin::TYPE_POST_TYPE_ARCHIVE ] = new PostTypeArchive();
        $this->templates[ Plugin::TYPE_DATE_ARCHIVE ] = new DateArchive();

        $flush_cache_integration = new Integrations\FlushCache\Integration();
        $polylang_integration = new Integrations\Polylang\Integration( $this->templates );

        $flush_cache_integration->set_db( $db );

        $this->integrations[ Plugin::INTEGRATION_FLUSH_CACHE ] = $flush_cache_integration;
        $this->integrations[ Plugin::INTEGRATION_POLYLANG ] = $polylang_integration;
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
     * @return TemplateInterface[]
     */
    public function get_templates() : array
    {
        return $this->templates;
    }

    /**
     * @return IntegrationInterface[]
     */
    public function get_integrations() : array
    {
        return $this->integrations;
    }

    /**
     * Hooks registration.
     *
     * @return void
     */
    public function run() : void
    {
        register_activation_hook( INNOCODE_PRERENDER_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( INNOCODE_PRERENDER_FILE, [ $this, 'deactivate' ] );

        Helpers::hook( 'plugins_loaded', [ $this, 'run_integrations' ] );
        Helpers::hook( 'init', [ $this->get_db(), 'init' ] );
        Helpers::hook( 'init', [ $this, 'init' ] );
        Helpers::hook( 'rest_api_init', [ $this->get_rest_controller(), 'register_routes' ] );
        Helpers::hook( 'wp_head', [ $this, 'print_scripts' ], 1 );

        Helpers::hook( 'delete_expired_transients', [ SecretsManager::class, 'flush_expired' ] );

        $prerender = $this->get_prerender();

        Helpers::hook( 'transition_post_status', [ $prerender, 'update_post' ] );
        Helpers::hook( 'delete_post', [ $prerender, 'delete_post' ] );
        Helpers::hook( 'saved_term', [ $prerender, 'update_term' ] );
        Helpers::hook( 'delete_term', [ $prerender, 'delete_term' ] );

        Helpers::hook( 'innocode_prerender', [ $prerender, 'invoke_lambda' ] );

        foreach ( $this->get_templates() as $type => $template ) {
            Helpers::hook( "innocode_prerender_is_$type", [ $template, 'is_queried' ] );
            Helpers::hook( "innocode_prerender_{$type}_id", [ $template, 'get_id' ] );
            Helpers::hook( "innocode_prerender_{$type}_url", [ $template, 'get_link' ] );
        }
    }

    /**
     * @return void
     */
    public function run_integrations() : void
    {
        foreach ( $this->get_integrations() as $integration ) {
            $integration->run();
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

        $converted_type_id = Plugin::get_object_id( $type, $id );

        if ( is_wp_error( $converted_type_id ) ) {
            return $converted_type_id;
        }

        list( $type, $object_id ) = $converted_type_id;

        $prerender = $this->get_prerender();
        $db = $prerender->get_db();
        $html_version = $db->get_html_version();

        if (
            null !== ( $entry = $db->get_entry( $type, $object_id ) ) &&
            (
                $html_version() == $entry->get_version() ||
                (
                    ! $entry->has_version() &&
                    null !== $entry->get_updated() &&
                    time() <= $entry->get_updated()->getTimestamp() + SecretsManager::EXPIRATION
                )
            )
        ) {
            return $entry->get_html();
        }

        $prerender->schedule( $type, $object_id );

        return '';
    }

    /**
     * @param string $type
     *
     * @return string|WP_Error
     */
    public static function filter_type( string $type )
    {
        return in_array( $type, Plugin::get_types(), true )
            ? apply_filters( 'innocode_prerender_type', $type )
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
    public static function get_object_id( string $type, $id = 0 )
    {
        switch ( $type ) {
            case Plugin::TYPE_FRONTPAGE:
            case Plugin::TYPE_POST:
            case Plugin::TYPE_TERM:
            case Plugin::TYPE_AUTHOR:
                $object_id = (int) $id;

                break;
            case Plugin::TYPE_POST_TYPE_ARCHIVE:
                if ( ! post_type_exists( $id ) ) {
                    return new WP_Error(
                        'innocode_prerender_invalid_post_type',
                        __( 'Invalid post type.', 'innocode-prerender' )
                    );
                }

                $type .= "_$id";
                $object_id = (int) $id;

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

                $object_id = (int) $id;

                break;
            default:
                $custom_object_id_not_implemented = new WP_Error(
                    'innocode_prerender_custom_object_id_not_implemented',
                    __( 'Custom object ID is not implemented.', 'innocode-prerender' )
                );
                $object_id = apply_filters(
                    'innocode_prerender_custom_object_id',
                    $custom_object_id_not_implemented,
                    $type,
                    $id
                );

                if ( is_wp_error( $object_id ) ) {
                    return $object_id;
                }

                break;
        }

        return [ $type, $object_id ];
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

    /**
     * @return void
     */
    public function activate() : void
    {
        $this->get_db()->init();
    }

    /**
     * @return void
     */
    public function deactivate() : void
    {
        $this->get_db()->drop_table();
        SecretsManager::flush();
    }
}
