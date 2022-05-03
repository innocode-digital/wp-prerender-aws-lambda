<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Plugin;

class PostTypeArchive extends AbstractTemplate
{
    /**
     * @inheritDoc
     */
    public function get_name() : string
    {
        return Plugin::TEMPLATE_POST_TYPE_ARCHIVE;
    }

    /**
     * @inheritDoc
     */
    public function get_type_id_pair( $id = 0 ) : ?array
    {
        if ( ! post_type_exists( $id ) ) {
            return null;
        }

        return [ "{$this->get_name()}_$id", 0 ];
    }

    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        return is_post_type_archive() || is_home() && ! is_front_page();
    }

    /**
     * @return string
     */
    public function get_id() : string
    {
        return get_query_var( 'post_type' );
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        return $id && false !== ( $link = get_post_type_archive_link( $id ) ) ? $link : null;
    }
}
