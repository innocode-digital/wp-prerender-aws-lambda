<?php

namespace Innocode\Prerender\Integrations\Polylang;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Plugin;

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
        $parent = $this->get_parent();

        if ( null === ( $link = $parent->get_link( $id ) ) ) {
            return null;
        }

        $template = $parent->get_name();
        $lang = $this->get_lang();

        if ( $template == Plugin::TEMPLATE_FRONTPAGE ) {
            return function_exists( 'pll_home_url' ) ? pll_home_url( $lang ) : null;
        }

        if ( $template == Plugin::TEMPLATE_POST_TYPE_ARCHIVE && $id == 'post' ) {
            $posts_page = (int) get_option( 'page_for_posts' );

            if ( $posts_page ) {
                $posts_page_lang = function_exists( 'pll_get_post' )
                    ? pll_get_post( $posts_page, $lang )
                    : null;

                if ( ! $posts_page_lang ) {
                    return null;
                }

                return false !== ( $link = get_permalink( (int) $id ) ) ? $link : null;
            }
        }

        if ( ! function_exists( 'PLL' ) ) {
            return null;
        }

        $pll = PLL();

        if ( false === ( $language = $pll->model->get_language( $lang ) ) ) {
            return null;
        }

        return $pll->links_model->switch_language_in_link( $link, $language );
    }
}
