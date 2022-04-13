<?php

namespace Innocode\Prerender\Integrations\Polylang;

use Innocode\Prerender\Interfaces\TemplateInterface;

class Template implements TemplateInterface
{
    /**
     * @var TemplateInterface
     */
    protected $parent;
    /**
     * @var string
     */
    protected $lang;

    /**
     * @param TemplateInterface $parent
     * @param string            $lang
     */
    public function __construct( TemplateInterface $parent, string $lang )
    {
        $this->parent = $parent;
        $this->lang = $lang;
    }

    /**
     * @return TemplateInterface
     */
    public function get_parent() : TemplateInterface
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

        if ( ! function_exists( 'PLL' ) ) {
            return $link;
        }

        $pll = PLL();
        $lang = $this->get_lang();

        if ( false === ( $language = $pll->model->get_language( $lang ) ) ) {
            return $link;
        }

        return $pll->links_model->switch_language_in_link( $link, $language );
    }
}
