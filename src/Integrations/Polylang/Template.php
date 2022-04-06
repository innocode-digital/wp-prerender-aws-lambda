<?php

namespace Innocode\Prerender\Integrations\Polylang;

use Innocode\Prerender\Interfaces\TemplateInterface;

class Template implements TemplateInterface
{
    /**
     * @var TemplateInterface
     */
    protected $template;
    /**
     * @var string
     */
    protected $lang;

    /**
     * @param TemplateInterface $template
     * @param string            $lang
     */
    public function __construct( TemplateInterface $template, string $lang )
    {
        $this->template = $template;
        $this->lang = $lang;
    }

    /**
     * @return TemplateInterface
     */
    public function get_template() : TemplateInterface
    {
        return $this->template;
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

        return pll_current_language() == $this->get_lang() && $this->get_template()->is_queried();
    }

    /**
     * @inheritDoc
     */
    public function get_id()
    {
        return $this->get_template()->get_id();
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        if ( null === ( $link = $this->get_template()->get_link( $id ) ) ) {
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
