<?php

namespace Innocode\Prerender;

use ReflectionException;
use ReflectionMethod;

class Helpers
{
    /**
     * @param string $name
     * @param array  $callback
     * @param int    $priority
     *
     * @return void
     */
    public static function hook( string $name, array $callback, int $priority = 10 ) : void
    {
        try {
            $method = new ReflectionMethod( $callback[0], $callback[1] );

            add_filter( $name, $callback, $priority, $method->getNumberOfParameters() );
        } catch ( ReflectionException $exception ) {}
    }

    /**
     * @param string $date
     *
     * @return array
     */
    public static function parse_Ymd( string $date ) : array
    {
        switch ( strlen( $date ) ) {
            case 4:
                $format = 'Y';

                break;
            case 6:
                $format = 'Ym';

                break;
            default:
                $format = 'Ymd';

                break;
        }

        return date_parse_from_format( $format, $date );
    }
}
