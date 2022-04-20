<?php

namespace Innocode\Prerender;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Interfaces\IntegrationInterface;
use Innocode\Prerender\Traits\DbTrait;
use Innocode\Prerender\Templates\Author;
use Innocode\Prerender\Templates\DateArchive;
use Innocode\Prerender\Templates\Frontpage;
use Innocode\Prerender\Templates\Post;
use Innocode\Prerender\Templates\PostTypeArchive;
use Innocode\Prerender\Templates\Term;

final class Plugin
{
    use DbTrait;

    const TEMPLATE_AUTHOR = 'author';
    const TEMPLATE_DATE_ARCHIVE = 'date_archive';
    const TEMPLATE_FRONTPAGE = 'frontpage';
    const TEMPLATE_POST = 'post';
    const TEMPLATE_POST_TYPE_ARCHIVE = 'post_type_archive';
    const TEMPLATE_TERM = 'term';

    const INTEGRATION_FLUSH_CACHE = 'flush_cache';
    const INTEGRATION_BATCACHE = 'batcache';
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
     * @var AbstractTemplate[]
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

        /**
         * @note Order is important.
         */
        $this->templates[] = new Frontpage();
        $this->templates[] = new PostTypeArchive();
        $this->templates[] = new Term();
        $this->templates[] = new Author();
        $this->templates[] = new DateArchive();
        $this->templates[] = new Post();

        $this->integrations[ Plugin::INTEGRATION_FLUSH_CACHE ] = new Integrations\FlushCache\Integration();
        $this->integrations[ Plugin::INTEGRATION_BATCACHE ] = new Integrations\Batcache\Integration();
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
     * @return AbstractTemplate[]
     */
    public function get_templates() : array
    {
        return $this->templates;
    }

    /**
     * @param string $name
     * @return AbstractTemplate|null
     */
    public function find_template( string $name ) : ?AbstractTemplate
    {
        foreach ( $this->get_templates() as $template ) {
            if ( $template->get_name() == $name ) {
                return $template;
            }
        }

        return null;
    }

    /**
     * @param AbstractTemplate[] $templates
     * @param int                $position
     * @return void
     */
    public function insert_templates( array $templates, int $position = -1 ) : void
    {
        array_splice( $this->templates, $position, 0, $templates );
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

        Helpers::hook( 'innocode_prerender_schedule', [ $this, 'clear_entry' ] );
        Helpers::hook( 'innocode_prerender', [ $this, 'invoke_lambda' ] );
        Helpers::hook( 'innocode_prerender_callback', [ $this, 'save_entry' ] );
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
        $this->get_rest_controller()->set_templates( array_map( function ( AbstractTemplate $template ) {
            return $template->get_name();
        }, $this->get_templates() ) );
    }

    /**
     * @param string     $template_name
     * @param string|int $id
     * @return void
     */
    public function clear_entry( string $template_name, $id = 0 ) : void
    {
        if (
            null === ( $template = $this->find_template( $template_name ) ) ||
            null === ( $type_id_pair = $template->get_type_id_pair( $id ) )
        ) {
            return;
        }

        list( $type, $object_id ) = $type_id_pair;

        $this->get_db()->clear_entry( $type, $object_id );
    }

    /**
     * Invokes AWS Lambda function.
     *
     * @param string     $template_name
     * @param string|int $id
     * @param ...$args
     *
     * @return void
     */
    public function invoke_lambda( string $template_name, $id = 0, ...$args ) : void
    {
        if (
            null === ( $template = $this->find_template( $template_name ) ) ||
            null === ( $url = $template->get_link( $id ) )
        ) {
            return;
        }

        list( $is_secret_set, $secret ) = SecretsManager::init( $template_name, (string) $id );

        if ( ! $is_secret_set ) {
            return;
        }

        $lambda = $this->get_lambda();
        $html_version = $this->get_db()->get_html_version();

        error_log( print_r( wp_parse_args( $args, [
            'type'       => $template_name,
            'id'         => $id,
            'url'        => add_query_arg( $this->get_query()->get_name(), 'true', $url ),
            'variable'   => apply_filters( 'innocode_prerender_variable', 'innocodePrerender' ),
            'selector'   => apply_filters( 'innocode_prerender_selector', '#app' ),
            'return_url' => $this->get_rest_controller()->url(),
            'secret'     => $secret,
            'version'    => $html_version(),
        ] ), true ) );

        $lambda( wp_parse_args( $args, [
            'type'       => $template_name,
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
     * @param Entry|null $entry
     * @param string     $template_name
     * @param string     $id
     * @param string     $html
     * @param string     $version
     * @return Entry|null
     */
    public function save_entry( ?Entry $entry, string $template_name, string $id, string $html, string $version ) : ?Entry
    {
        if (
            null === ( $template = $this->find_template( $template_name ) ) ||
            null === ( $type_id_pair = $template->get_type_id_pair( $id ) )
        ) {
            return null;
        }

        list( $type, $object_id ) = $type_id_pair;

        $db = $this->get_db();

        if ( ! $db->save_entry( $html, $version, $type, $object_id ) ) {
            return null;
        }

        return $db->get_entry( $type, $object_id );
    }

    /**
     * @return void
     */
    public function render() : void
    {
        foreach ( $this->get_templates() as $template ) {
            if ( $template->is_queried() ) {
                echo $this->get_html( $template );
                break;
            }
        }
    }

    /**
     * @param AbstractTemplate $template
     *
     * @return string
     */
    public function get_html( AbstractTemplate $template ) : string
    {
        $id = $template->get_id();

        if ( null === ( $type_id_pair = $template->get_type_id_pair( $id ) ) ) {
            return '';
        }

        list( $type, $object_id ) = $type_id_pair;

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

        $queue->schedule( $template->get_name(), $id );

        return '';
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
