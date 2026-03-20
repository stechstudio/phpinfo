<?php

namespace STS\Phpinfo\Parsers;

use InvalidArgumentException;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\PhpInfo;

require_once __DIR__ . '/parse_text.php';

class TextParser implements Parser
{
    public function __construct(protected string $contents)
    {
        if (!static::canParse($contents)) {
            throw new InvalidArgumentException('Content provided does not appear to be valid phpinfo() text output');
        }
    }

    public static function canParse(string $contents): bool
    {
        $normalized = str_replace("\r\n", "\n", $contents);

        return str_starts_with($normalized, "phpinfo()\n");
    }

    public function parse(): PhpInfo
    {
        $data = pp_parse($this->contents);

        $modules = items(array_map(fn($m) => new Module(
            $m['name'],
            items(array_map(fn($g) => new Group(
                items(array_map(fn($c) => new Config(
                    $c['name'],
                    $c['localValue'],
                    $c['masterValue'],
                    $c['hasMasterValue'],
                ), $g['configs'])),
                !empty($g['headings']) ? items($g['headings']) : null,
                $g['name'],
                $g['note'],
            ), $m['groups']))
        ), $data['modules']));

        return new PhpInfo($data['version'], $modules);
    }
}
