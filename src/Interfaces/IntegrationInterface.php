<?php

namespace Innocode\Prerender\Interfaces;

use Innocode\Prerender\Plugin;

interface IntegrationInterface
{
    /**
     * @param Plugin $plugin
     * @return void
     */
    public function run( Plugin $plugin ) : void;
}
