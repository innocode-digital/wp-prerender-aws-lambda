<?php

namespace Innocode\Prerender\Integrations\Polylang;

use Innocode\Prerender\Abstracts\AbstractTemplate;

class Template extends AbstractTemplate
{
    /**
     * @var AbstractTemplate
     */
    protected $parent;
    /**
     * @var string
     */
    protected $lang;

    public function __construct( AbstractTemplate $parent, string $lang )
    {
        $this->parent = $parent;
        $this->lang = $lang;
    }

    /**
     * @return AbstractTemplate
     */
    public function get_parent() : AbstractTemplate
    {
        return $this->parent;
    }

    /**
     * @return string
     */
    public function get_lang() : string
    {
        return $this->lang;
    }

    /**
     * @inheritDoc
     */
    public function get_name() : string
    {
        return "pll_{$this->get_parent()->get_name()}_{$this->get_lang()}";
    }

    /**
     * @inheritDoc
     */
    public function get_type_id_pair( $id = 0 ) : ?array
    {
        if ( null === ( $type_id_pair = $this->get_parent()->get_type_id_pair( $id ) ) ) {
            return null;
        }

        list( $type, $object_id ) = $type_id_pair;

        return [ str_replace( $this->get_parent()->get_name(), $this->get_name(), $type ), $object_id ];
    }

    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        if ( ! function_exists( 'pll_current_language' ) ) {
            return false;
        }

        return pll_current_language() == $this->get_lang() && $this->get_parent()->is_queried();
    }

    /**
     * @inheritDoc
     */
    public function get_id()
    {
        return $this->get_parent()->get_id();
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        if ( null === ( $link = $this->get_parent()->get_link( $id ) ) ) {
            return null;
        }

        $lang = $this->get_lang();

        if ( is_front_page() ) {
            return function_exists( 'pll_home_url' ) ? pll_home_url( $lang ) : $link;
        }

        if ( is_home() ) {
            $page_for_posts = (int) get_option( 'page_for_posts' );

            if ( $page_for_posts ) {
                return function_exists( 'pll_get_post' ) ? pll_get_post( $page_for_posts, $lang ) : $link;
            }
        }

        if ( ! function_exists( 'PLL' ) ) {
            return $link;
        }

        $pll = PLL();

        if ( false === ( $language = $pll->model->get_language( $lang ) ) ) {
            return $link;
        }

        return $pll->links_model->switch_language_in_link( $link, $language );
    }
}
