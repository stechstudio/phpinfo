<?php

namespace STS\Phpinfo\Parsers;

use Illuminate\Support\Collection;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Result;

class TextParser extends Result
{
    public static function canParse(string $contents): bool
    {
        return str_contains(str_replace("\r\n","\n",$contents), "phpinfo()\nPHP Version")
            && count(explode("_______________________________________________________________________", $contents)) === 3;
    }

    protected function parse(): void
    {
        $this->contents = str_replace("\r\n","\n", $this->contents);

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
            new Module('General', collect([
                new Group(
                    collect(array_slice($lines, 3))
                    // We only care about key/value pairs
                    ->filter(fn($line) => str_contains($line, " => "))
                    // Parse out the key/value and create a Config instance
                    ->map(fn($line) => Config::fromValues($this->lineToValues($line)))
                )
            ]))
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
            ->map(fn($lines) => new Module(
                $lines->shift(),
                $this->splitIntoGroups($lines)
            ));
    }

    protected function splitIntoGroups(Collection $lines): Collection
    {
        return $lines
            ->map(fn($line) => $this->lineToValues($line))
            // Break our lines into groups based on how many variables they have
            ->partition(fn(Collection $values) => $values->count() === 2)
            // Sometimes we get a partitioned group that is empty, get ride of those
            ->filter(fn(Collection $groupedValues) => $groupedValues->count())
            // Now turn our grouped values into Group instances
            ->map(fn(Collection $groupedValues) => $this->buildGroup($groupedValues));
    }

    protected function buildGroup(Collection $lines): Group
    {
        $headings = in_array($lines->first()->first(), ['Directive', 'Variable'])
            ? $lines->first()
            : collect();

        return new Group(
            $lines
                ->reject(fn(Collection $values) => in_array($values->first(), ['Directive', 'Variable']))
                ->map(fn(Collection $values) => Config::fromValues($values)),
            $headings
        );
    }

    protected function lineToValues($line): Collection
    {
        return collect(explode(" => ", $line))
            ->map(fn($part) => trim($part));
    }
}