<?php

namespace STS\Phpinfo\Support;

/**
 * @internal
 */
final class Str
{
    public static function slug(string $text): string
    {
        return strtolower(trim(preg_replace('/\W+/', '_', $text), '_'));
    }
}
