<?php

namespace STS\Phpinfo\Traits;

use STS\Phpinfo\Result;

/**
 * @mixin Result
 */
trait ConfigAliases
{
    protected $aliases = [
        'os',
        'hostname'
    ];

    protected function getOs()
    {
        return explode(" ",$this->config('System'))[0];
    }

    protected function getHostname()
    {
        return explode(" ",$this->config('System'))[1];
    }
}