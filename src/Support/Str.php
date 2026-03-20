<?php

namespace STS\Phpinfo\Support;

final class Str
{
    public static function slug(string $text): string
    {
        return strtolower(trim(preg_replace('/\W+/', '_', $text), '_'));
    }
}
