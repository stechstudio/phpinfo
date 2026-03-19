<?php

namespace STS\Phpinfo\Parsers;

use STS\Phpinfo\PhpInfo;

interface Parser
{
    public static function canParse(string $contents): bool;

    public function parse(): PhpInfo;
}
