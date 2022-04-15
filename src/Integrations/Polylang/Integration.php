<?php

namespace Innocode\Prerender\Integrations\Polylang;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Helpers;
use Innocode\Prerender\Interfaces\IntegrationInterface;
use Innocode\Prerender\Plugin;

class Integration implements IntegrationInterface
{
    const TEMPLATES = [
        Plugin::TEMPLATE_FRONTPAGE,
        Plugin::TEMPLATE_POST_TYPE_ARCHIVE,
        Plugin::TEMPLATE_AUTHOR,
        Plugin::TEMPLATE_DATE_ARCHIVE,
    ];

    /**
     * @var AbstractTemplate[]
     */
    protected $templates = [];
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

        Helpers::hook( 'innocode_prerender_pre_update_post', [ $this, 'init_post_current_lang' ] );
        Helpers::hook( 'innocode_prerender_pre_delete_post', [ $this, 'init_post_current_lang' ] );
        Helpers::hook( 'innocode_prerender_pre_update_term', [ $this, 'init_term_current_lang' ] );
        Helpers::hook( 'innocode_prerender_pre_delete_term', [ $this, 'init_term_current_lang' ] );
        Helpers::hook( 'innocode_prerender_template', [ $this, 'filter_template' ] );
        Helpers::hook( 'innocode_prerender_update_post', [ $this, 'unset_current_lang' ] );
        Helpers::hook( 'innocode_prerender_delete_post', [ $this, 'unset_current_lang' ] );
        Helpers::hook( 'innocode_prerender_update_term', [ $this, 'unset_current_lang' ] );
        Helpers::hook( 'innocode_prerender_delete_term', [ $this, 'unset_current_lang' ] );
    }

    /**
     * @return AbstractTemplate[]
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
     * @param Plugin $plugin
     * @return void
     */
    public function init_templates( Plugin $plugin ) : void
    {
        if ( ! function_exists( 'pll_languages_list' ) ) {
            return;
        }

        $languages = pll_languages_list();
        $templates = [];

        foreach ( $plugin->get_templates() as $template ) {
            if ( in_array( $template->get_name(), static::TEMPLATES, true ) ) {
                foreach ( $languages as $lang ) {
                    $templates[] = new Template( $template, $lang );
                }
            }
        }

        $plugin->insert_templates( $templates, 0 );
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
     * @param string $template_name
     * @return string
     */
    public function filter_template( string $template_name ) : string
    {
        if ( ! in_array( $template_name, static::TEMPLATES, true ) ) {
            return $template_name;
        }

        if ( null === ( $current_lang = $this->get_current_lang() ) ) {
            return $template_name;
        }

        return "pll_{$template_name}_$current_lang";
    }

    /**
     * @return void
     */
    public function unset_current_lang() : void
    {
        $this->current_lang = null;
    }
}
