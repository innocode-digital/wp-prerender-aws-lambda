<?php

namespace Innocode\Prerender\Integrations\Polylang;

use Innocode\Prerender\Helpers;
use Innocode\Prerender\Interfaces\IntegrationInterface;
use Innocode\Prerender\Plugin;
use WP_Error;

class Integration implements IntegrationInterface
{
    const PREFIX = 'pll_';

    const TYPES = [
        Plugin::TYPE_FRONTPAGE,
        Plugin::TYPE_POST_TYPE_ARCHIVE,
        Plugin::TYPE_AUTHOR,
        Plugin::TYPE_DATE_ARCHIVE,
    ];

    /**
     * @var array
     */
    protected $types = [];
    /**
     * @var string|null
     */
    protected $current_lang;

    /**
     * @inheritDoc
     */
    public function run( Plugin $plugin ) : void
    {
        $this->init_templates( $plugin );

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
    }

    /**
     * @return string|null
     */
    public function get_current_lang() : ?string
    {
        return $this->current_lang;
    }

    /**
     * @return array
     */
    public function get_types() : array
    {
        return $this->types;
    }

    /**
     * @param Plugin $plugin
     * @return void
     */
    public function init_templates( Plugin $plugin ) : void
    {
        if ( ! function_exists( 'pll_languages_list' ) ) {
            return;
        }

        $languages = pll_languages_list();
        $templates = $plugin->get_templates();

        foreach ( static::TYPES as $parent_type ) {
            if ( isset( $templates[ $parent_type ] ) ) {
                foreach ( $languages as $lang ) {
                    $type = static::generate_type( $parent_type, $lang );
                    $template = new Template( $templates[ $parent_type ], $lang );
                    $plugin->set_template( $type, $template );
                    $this->types[] = $type;
                }
            }
        }
    }

    /**
     * @param array $types
     * @return array
     */
    public function add_types( array $types ) : array
    {
        return array_merge( $this->get_types(), $types );
    }

    /**
     * @param string $parent_type
     * @param string $lang
     * @return string
     */
    public static function generate_type( string $parent_type, string $lang ) : string
    {
        return static::PREFIX . "{$parent_type}_$lang";
    }

    /**
     * @param int|WP_Error $object_id
     * @param string       $type
     * @param string|int   $id
     *
     * @return int|WP_Error
     */
    public function get_custom_object_id( $object_id, string $type, $id )
    {
        if ( null === ( $parsed_type = static::parse_type( $type ) ) ) {
            return $object_id;
        }

        $converted_type_id = Plugin::get_object_id( $parsed_type, $id );

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
    public static function parse_type( string $type ) : ?string
    {
        $prefix_length = strlen( static::PREFIX );

        if ( substr( $type, 0, $prefix_length ) != static::PREFIX ) {
            return null;
        }

        $no_prefix = substr( $type, $prefix_length );

        if ( ! function_exists( 'pll_languages_list' ) ) {
            return null;
        }

        $languages = pll_languages_list();

        foreach ( $languages as $lang ) {
            $postfix = "_$lang";
            $postfix_length = strlen( $postfix );

            if ( ! $postfix_length ) {
                continue;
            }

            if ( substr( $no_prefix, -$postfix_length ) == $postfix ) {
                return substr( $no_prefix, 0, -$postfix_length );
            }
        }

        return null;
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
        if ( ! in_array( $type, static::TYPES, true ) ) {
            return $type;
        }

        if ( null === ( $current_lang = $this->get_current_lang() ) ) {
            return $type;
        }

        return static::generate_type( $type, $current_lang );
    }
}
