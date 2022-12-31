<?php

namespace STS\Phpinfo\Parsers;

use Illuminate\Support\Collection;
use STS\Phpinfo\Info;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\General;
use STS\Phpinfo\Models\Module;

class TextParser extends Info
{
    protected function parse(): void
    {
        $parts = explode("_______________________________________________________________________", $this->contents);

        $this->parseGeneral($parts[0]);
        $this->parseModules($parts[1]);
    }

    protected function parseGeneral($contents): void
    {
        $lines = explode("\n", $contents);

        $this->version = $this->lineToValues($lines[1])->get(1);

        $this->general = General::make(array_slice($lines, 3))
            ->filter(fn($line) => str_contains($line, " => "))
            ->map(fn($line) => Config::fromValues($this->lineToValues($line)))
            ->keyBy(fn(Config $config) => strtolower($config->name()));
    }

    protected function parseModules($contents)
    {
        $contents = preg_replace_callback("/\n([^=]*)\n\n/", function ($matches) {
            return "\n----------\n" . trim($matches[1]) . "\n";
        }, trim($contents));

        $lines = array_slice(explode("\n----------\n", $contents), 1);

        $this->modules = collect($lines)
            ->map(fn($text) => collect(explode("\n", $text))->filter())
            ->map(fn($items) => new Module(
                $items->shift(),
                $items
                    ->filter(fn($line) => str_contains($line, "="))
                    ->map(fn($line) => Config::fromValues($this->lineToValues($line)))
            ))
            ->keyBy(fn(Module $module) => strtolower($module->name()));

        $this->configs = $this->modules->map->configs()->flatten()->keyBy(fn(Config $config) => strtolower($config->name()));
    }

    protected function lineToValues($line): Collection
    {
        return collect(explode(" => ", $line))
            ->map(fn($part) => trim($part));
    }
}