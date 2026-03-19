<?php

namespace STS\Phpinfo;

use InvalidArgumentException;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\Parsers\TextParser;

abstract class Info
{
    public static function capture(): PhpInfo
    {
        ob_start();
        phpinfo();
        $contents = ob_get_clean();

        return PHP_SAPI === 'cli'
            ? static::fromText($contents)
            : static::fromHtml($contents);
    }

    public static function fromHtml(string $contents): PhpInfo
    {
        return (new HtmlParser($contents))->parse();
    }

    public static function fromText(string $contents): PhpInfo
    {
        return (new TextParser($contents))->parse();
    }

    public static function detect(string $contents): PhpInfo
    {
        return match (true) {
            HtmlParser::canParse($contents) => static::fromHtml($contents),
            TextParser::canParse($contents) => static::fromText($contents),
            default => throw new InvalidArgumentException('Content provided does not appear to be valid phpinfo() output'),
        };
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::capture()->$method(...$arguments);
    }
}
