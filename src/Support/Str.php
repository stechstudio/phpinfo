<?php

namespace STS\Phpinfo\Support;

/**
 * @internal
 *
 * Note: pp_slug() in src/Parsers/parse_text.php is an identical implementation
 * for the standalone (dependency-free) parser. Keep both in sync.
 */
final class Str
{
    public static function slug(string $text): string
    {
        return strtolower(trim(preg_replace('/\W+/', '_', $text), '_'));
    }
}
