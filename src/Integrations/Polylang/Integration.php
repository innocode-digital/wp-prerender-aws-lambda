<?php

namespace Innocode\Prerender\Integrations\Polylang;

use Innocode\Prerender\Helpers;
use Innocode\Prerender\Interfaces\IntegrationInterface;
use Innocode\Prerender\Interfaces\TemplateInterface;
use Innocode\Prerender\Plugin;
use WP_Error;

class Integration implements IntegrationInterface
{
    const PREFIX = 'pll_';

    const TYPES = [
        Plugin::TYPE_AUTHOR,
        Plugin::TYPE_FRONTPAGE,
        Plugin::TYPE_POST_TYPE_ARCHIVE,
        Plugin::TYPE_DATE_ARCHIVE,
    ];

    /**
     * @var TemplateInterface[]
     */
    protected $templates = [];
    /**
     * @var string
     */
    protected $current_lang;

    /**
     * @param array $templates
     */
    public function __construct( array $templates )
    {
        $this->init_templates( $templates );
    }

    /**
     * @inheritDoc
     */
    public function run() : void
    {
        Helpers::hook( 'innocode_prerender_types', [ $this, 'add_types' ] );
        Helpers::hook( 'innocode_prerender_custom_object_id', [ $this, 'get_custom_object_id' ] );
        Helpers::hook( 'innocode_prerender_pre_update_post', [ $this, 'init_post_current_lang' ] );
        Helpers::hook( 'innocode_prerender_pre_delete_post', [ $this, 'init_post_current_lang' ] );
        Helpers::hook( 'innocode_prerender_pre_update_term', [ $this, 'init_term_current_lang' ] );
        Helpers::hook( 'innocode_prerender_pre_delete_term', [ $this, 'init_term_current_lang' ] );
        Helpers::hook( 'innocode_prerender_update_post', [ $this, 'unset_current_lang' ] );
        Helpers::hook( 'innocode_prerender_delete_post', [ $this, 'unset_current_lang' ] );
        Helpers::hook( 'innocode_prerender_update_term', [ $this, 'unset_current_lang' ] );
        Helpers::hook( 'innocode_prerender_delete_term', [ $this, 'unset_current_lang' ] );
        Helpers::hook( 'innocode_prerender_type', [ $this, 'filter_type' ] );

        foreach ( $this->get_templates() as $type => $template ) {
            Helpers::hook( "innocode_prerender_{$type}_url", [ $template, 'get_link' ] );
        }
    }

    /**
     * @param array $templates
     * @return void
     */
    public function init_templates( array $templates ) : void
    {
        if ( ! function_exists( 'pll_languages_list' ) ) {
            return;
        }

        $languages = pll_languages_list();

        foreach ( static::TYPES as $type ) {
            foreach ( $languages as $lang ) {
                $this->templates[ static::add_lang_to_type( $type, $lang ) ] = new Template( $templates[ $type ], $lang );
            }
        }
    }

    /**
     * @return TemplateInterface[]
     */
    public function get_templates() : array
    {
        return $this->templates;
    }

    /**
     * @return string|null
     */
    public function get_current_lang() : ?string
    {
        return $this->current_lang;
    }

    /**
     * @param string $type
     * @param string $lang
     * @return string
     */
    public static function add_lang_to_type( string $type, string $lang ) : string
    {
        return static::PREFIX . "{$type}_$lang";
    }

    /**
     * @param array $types
     * @return array
     */
    public function add_types( array $types ) : array
    {
        return array_merge( $types, array_keys( $this->get_templates() ) );
    }

    /**
     * @param int|WP_Error $object_id
     * @param string       $type
     * @param string|int   $id
     *
     * @return int|WP_Error
     */
    public function get_custom_object_id( $object_id, string $type, $id ) : int
    {
        $parsed_type = static::parse_type( $type );

        if ( is_wp_error( $parsed_type ) ) {
            return $object_id;
        }

        list( $basic_type ) = $parsed_type;

        $converted_type_id = Plugin::get_object_id( $basic_type, $id );

        if ( is_wp_error( $converted_type_id ) ) {
            return $converted_type_id;
        }

        list( , $object_id ) = $converted_type_id;

        return $object_id;
    }

    /**
     * @param string $type
     * @return array|WP_Error
     */
    public static function parse_type( string $type )
    {
        $prefix_length = strlen( static::PREFIX );

        if ( substr( $type, 0, $prefix_length ) != static::PREFIX ) {
            return new WP_Error(
                'innocode_prerender_polylang_invalid_prefix',
                __( 'Invalid prefix.', 'innocode-prerender' )
            );
        }

        $no_prefix = substr( $type, $prefix_length );

        if ( ! function_exists( 'pll_languages_list' ) ) {
            return new WP_Error(
                'innocode_prerender_polylang_not_installed',
                __( 'Polylang is not installed.', 'innocode-prerender' )
            );
        }

        $languages = pll_languages_list();

        foreach ( $languages as $lang ) {
            $postfix = "_$lang";
            $postfix_length = strlen( $postfix );

            if ( ! $postfix_length ) {
                continue;
            }

            if ( substr( $no_prefix, -$postfix_length ) == $lang ) {
                return [ substr( $no_prefix, 0, -$postfix_length ), $lang ];
            }
        }

        return new WP_Error(
            'innocode_prerender_polylang_invalid_language',
            __( 'Invalid language', 'innocode-prerender' )
        );
    }

    /**
     * @param int $post_id
     * @return void
     */
    public function init_post_current_lang( int $post_id ) : void
    {
        if ( ! function_exists( 'pll_get_post_language' ) ) {
            return;
        }

        if ( false === ( $lang = pll_get_post_language( $post_id ) ) ) {
            return;
        }

        $this->current_lang = $lang;
    }

    /**
     * @param int $tt_id
     * @return void
     */
    public function init_term_current_lang( int $tt_id ) : void
    {
        if ( ! function_exists( 'pll_get_term_language' ) ) {
            return;
        }

        $term = get_term_by( 'term_taxonomy_id', $tt_id );

        if ( ! $term ) {
            return;
        }

        if ( false === ( $lang = pll_get_term_language( $term->term_id ) ) ) {
            return;
        }

        $this->current_lang = $lang;
    }

    /**
     * @return void
     */
    public function unset_current_lang() : void
    {
        $this->current_lang = null;
    }

    /**
     * @param string $type
     * @return string
     */
    public function filter_type( string $type ) : string
    {
        if ( null === ( $current_lang = $this->get_current_lang() ) ) {
            return $type;
        }

        return static::add_lang_to_type( $type, $current_lang );
    }
}
