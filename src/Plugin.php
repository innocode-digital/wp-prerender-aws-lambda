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
     * @var Lambda
     */
    protected $lambda;
    /**
     * @var Queue
     */
    protected $queue;
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
        $queue = new Queue();
        $rest_controller = new RESTController();

        $queue->set_db( $db );
        $rest_controller->set_db( $db );

        $this->db = $db;
        $this->lambda = new Lambda( $key, $secret, $region );
        $this->queue = $queue;
        $this->rest_controller = $rest_controller;
        $this->query = new Query();

        $this->templates[ Plugin::TYPE_POST ] = new Post();
        $this->templates[ Plugin::TYPE_TERM ] = new Term();
        $this->templates[ Plugin::TYPE_AUTHOR ] = new Author();
        $this->templates[ Plugin::TYPE_FRONTPAGE ] = new Frontpage();
        $this->templates[ Plugin::TYPE_POST_TYPE_ARCHIVE ] = new PostTypeArchive();
        $this->templates[ Plugin::TYPE_DATE_ARCHIVE ] = new DateArchive();

        $this->integrations[ Plugin::INTEGRATION_FLUSH_CACHE ] = new Integrations\FlushCache\Integration();
        $this->integrations[ Plugin::INTEGRATION_POLYLANG ] = new Integrations\Polylang\Integration();
    }

    /**
     * @return Lambda
     */
    public function get_lambda() : Lambda
    {
        return $this->lambda;
    }

    /**
     * @return Queue
     */
    public function get_queue() : Queue
    {
        return $this->queue;
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
     * @param string            $type
     * @param TemplateInterface $template
     * @return void
     */
    public function add_template( string $type, TemplateInterface $template ) : void
    {
        $this->templates[ $type ] = $template;
    }

    /**
     * @param string $type
     * @return void
     */
    public function remove_template( string $type ) : void
    {
        unset( $this->templates[ $type ] );
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

        $queue = $this->get_queue();

        Helpers::hook( 'transition_post_status', [ $queue, 'update_post' ] );
        Helpers::hook( 'delete_post', [ $queue, 'delete_post' ] );
        Helpers::hook( 'saved_term', [ $queue, 'update_term' ] );
        Helpers::hook( 'delete_term', [ $queue, 'delete_term' ] );

        Helpers::hook( 'innocode_prerender', [ $this, 'invoke_lambda' ] );
    }

    /**
     * @return void
     */
    public function run_integrations() : void
    {
        foreach ( $this->get_integrations() as $integration ) {
            $integration->run( $this );
        }
    }

    /**
     * @return void
     */
    public function init() : void
    {
        $this->get_rest_controller()->set_types( array_keys( $this->get_templates() ) );
    }

    /**
     * Invokes AWS Lambda function.
     *
     * @param string     $type
     * @param string|int $id
     * @param ...$args
     *
     * @return void
     */
    public function invoke_lambda( string $type, $id = 0, ...$args ) : void
    {
        $templates = $this->get_templates();

        if ( ! isset( $templates[ $type ] ) || null === ( $url = $templates[ $type ]->get_link( $id ) ) ) {
            return;
        }

        list( $is_secret_set, $secret ) = SecretsManager::init( $type, (string) $id );

        if ( ! $is_secret_set ) {
            return;
        }

        $lambda = $this->get_lambda();
        $html_version = $this->get_db()->get_html_version();

        $lambda( wp_parse_args( $args, [
            'type'       => $type,
            'id'         => $id,
            'url'        => add_query_arg( $this->get_query()->get_name(), 'true', $url ),
            'variable'   => apply_filters( 'innocode_prerender_variable', 'innocodePrerender' ),
            'selector'   => apply_filters( 'innocode_prerender_selector', '#app' ),
            'return_url' => $this->get_rest_controller()->url(),
            'secret'     => $secret,
            'version'    => $html_version(),
        ] ) );
    }

    /**
     * @return void
     */
    public function render() : void
    {
        foreach ( $this->get_templates() as $type => $template ) {
            if ( $template->is_queried() ) {
                echo $this->get_html( $type, $template->get_id() );
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
        $converted_type_id = Plugin::get_object_id( $type, $id );

        if ( is_wp_error( $converted_type_id ) ) {
            return $converted_type_id;
        }

        list( $type, $object_id ) = $converted_type_id;

        $queue = $this->get_queue();
        $db = $queue->get_db();
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

        $queue->schedule( $type, $id );

        return '';
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
