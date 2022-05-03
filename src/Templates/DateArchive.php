<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Abstracts\AbstractTemplate;
use Innocode\Prerender\Helpers;
use Innocode\Prerender\Plugin;

class DateArchive extends AbstractTemplate
{
    /**
     * @inheritDoc
     */
    public function get_name() : string
    {
        return Plugin::TEMPLATE_DATE_ARCHIVE;
    }

    /**
     * @inheritDoc
     */
    public function get_type_id_pair( $id = 0 ) : ?array
    {
        $parsed = Helpers::parse_Ymd( $id );

        if ( false === $parsed['year'] ) {
            return null;
        }

        $type = $this->get_name();
        $type .= "_{$parsed['year']}";

        if ( false !== $parsed['month'] ) {
            $type .= $parsed['month'];

            if ( false !== $parsed['day'] ) {
                $type .= $parsed['day'];
            }
        }

        return [ $type, 0 ];
    }

    /**
     * @inheritDoc
     */
    public function is_queried() : bool
    {
        return is_year() || is_month() || is_day();
    }

    /**
     * @return false|string
     */
    public function get_id()
    {
        if ( is_day() ) {
            return get_the_date( 'Ymd' );
        } elseif ( is_month() ) {
            return get_the_date( 'Ym' );
        } elseif ( is_year() ) {
            return get_the_date( 'Y' );
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function get_link( $id = 0 ) : ?string
    {
        $parsed = Helpers::parse_Ymd( $id );

        if ( false === $parsed['year'] ) {
            // Something wrong as date should always include year.
            return null;
        }

        if ( false === $parsed['month'] ) {
            return get_year_link( $parsed['year'] );
        }

        return false !== $parsed['day']
            ? get_day_link( $parsed['year'], $parsed['month'], $parsed['day'] )
            : get_month_link( $parsed['year'], $parsed['month'] );
    }
}
