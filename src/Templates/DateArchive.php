<?php

namespace Innocode\Prerender\Templates;

use Innocode\Prerender\Helpers;
use Innocode\Prerender\Interfaces\TemplateInterface;

class DateArchive implements TemplateInterface
{
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
