<?php

namespace Innocode\Prerender;

use Aws\Lambda\LambdaClient;
use Aws\Result;

/**
 * Class Lambda
 *
 * @package Innocode\Prerender
 */
class Lambda
{
    /**
     * @var string
     */
    private $function;

    /**
     * @var LambdaClient
     */
    private $client;

    /**
     * Lambda constructor.
     *
     * @param string $key
     * @param string $secret
     * @param string $region
     */
    public function __construct( string $key, string $secret, string $region )
    {
        $this->client = new LambdaClient( [
            'credentials' => [
                'key'    => $key,
                'secret' => $secret,
            ],
            'region'      => $region,
            'version'     => 'latest',
        ] );
    }

    /**
     * @param string $function
     */
    public function set_function( string $function )
    {
        $this->function = $function;
    }

    /**
     * @return string
     */
    private function get_function(): string
    {
        return $this->function;
    }

    /**
     * @return LambdaClient
     */
    private function get_client(): LambdaClient
    {
        return $this->client;
    }

    /**
     * @param array $args
     *
     * @return Result
     */
    public function __invoke( array $args ): Result
    {
        return $this->get_client()->invoke( [
            'FunctionName'      => $this->get_function(),
            'Payload'           => json_encode( $args ),
            'InvocationType'    => 'Event'
        ] );
    }
}
