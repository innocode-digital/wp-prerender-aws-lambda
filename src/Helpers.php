<?php

namespace Innocode\Prerender;

use ReflectionException;
use ReflectionMethod;

/**
 * Class Tools
 *
 * @package Innocode\Prerender
 */
class Helpers
{
    /**
     * @param string $hook
     * @param array  $callback
     * @param int    $priority
     */
    public static function action( string $hook, array $callback, int $priority = 10 )
    {
        try {
            $method = new ReflectionMethod( $callback[0], $callback[1] );

            add_action( $hook, $callback, $priority, $method->getNumberOfParameters() );
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
