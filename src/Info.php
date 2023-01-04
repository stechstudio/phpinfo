<?php

namespace STS\Phpinfo;

use InvalidArgumentException;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\Parsers\TextParser;

abstract class Info
{
    public static function capture(): Result
    {
        ob_start();
        phpinfo();
        $contents = ob_get_clean();

        return PHP_SAPI === "cli"
            ? static::fromText($contents)
            : static::fromHtml($contents);
    }

    public static function fromHtml($contents): HtmlParser
    {
        return new HtmlParser($contents);
    }

    public static function fromText($contents): TextParser
    {
        return new TextParser($contents);
    }

    public static function detect($contents): Result
    {
        return match(true) {
            HtmlParser::canParse($contents) => new HtmlParser($contents),
            TextParser::canParse($contents) => new TextParser($contents),
            default => throw new InvalidArgumentException("Content provided does not appear to be valid phpinfo() output")
        };
    }

    public static function __callStatic($method, $arguments)
    {
        return static::capture()->$method(...$arguments);
    }
}