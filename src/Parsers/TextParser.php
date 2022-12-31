<?php

namespace STS\Phpinfo\Parsers;

use Illuminate\Support\Collection;
use STS\Phpinfo\Info;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Module;

class TextParser extends Info
{
    public static function canParse(string $contents): bool
    {
        return str_contains($contents, "phpinfo()\nPHP Version")
            && count(explode("_______________________________________________________________________", $contents)) === 3;
    }

    protected function parse(): void
    {
        // phpinfo() helpfully gives us this big line, separating the general info, modules, and credits/license
        [$general, $modules, $credits] = explode("_______________________________________________________________________", $this->contents);

        $this->parseModules($modules);
        $this->parseGeneral($general);
    }

    protected function parseGeneral($contents): void
    {
        $lines = explode("\n", $contents);

        $this->version = $this->lineToValues($lines[1])->get(1);

        $this->modules->prepend(
            new Module('General',
                collect(array_slice($lines, 3))
                    // We only care about key/value pairs
                    ->filter(fn($line) => str_contains($line, " => "))
                    // Parse out the key/value and create a Config instance
                    ->map(fn($line) => Config::fromValues($this->lineToValues($line)))
                    // Key by lowercase name for easy lookups
                    ->keyBy(fn(Config $config) => strtolower($config->name()))
            ), 'general'
        );
    }

    protected function parseModules($contents)
    {
        // Find our module names (surrounded by line breaks, and no assignment "=>" character)
        // We're going to stick a line above each module name to make it easier to explode module
        // content up.
        $contents = preg_replace_callback("/\n([^=]*)\n\n/", function ($matches) {
            return "\n----------\n" . trim($matches[1]) . "\n";
        }, trim($contents));

        $lines = array_slice(explode("\n----------\n", $contents), 1);

        $this->modules = collect($lines)
            // Get lines, and filter out empty strings
            ->map(fn($text) => collect(explode("\n", $text))->filter())
            // Each array of lines will start with the module name, and then the configs
            ->map(fn($items) => new Module(
                $items->shift(),
                $items
                    // Get rid of notes, or anything that isn't config key/value(s)
                    ->filter(fn($line) => str_contains($line, "="))
                    // Now parse out our key/value(s) and create the Config
                    ->map(fn($line) => Config::fromValues($this->lineToValues($line)))
            ))
            // Key by lowercase name for easy lookups
            ->keyBy(fn(Module $module) => strtolower($module->name()));
    }

    protected function lineToValues($line): Collection
    {
        return collect(explode(" => ", $line))
            ->map(fn($part) => trim($part));
    }
}