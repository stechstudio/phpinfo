<?php

namespace STS\Phpinfo\Parsers;

use Illuminate\Support\Collection;
use STS\Phpinfo\Collections\Lines;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Result;

class TextParser extends Result
{
    protected Lines $lines;

    public static function canParse(string $contents): bool
    {
        return str_contains(str_replace("\r\n", "\n", $contents), "phpinfo()\nPHP Version")
            && count(explode("_______________________________________________________________________", $contents)) === 3;
    }

    protected function parse(): void
    {
        $this->contents = str_replace("\r\n", "\n", $this->contents);
        $this->lines = new Lines(explode("\n", $this->contents));

        // Our first line is just phpinfo()
        $this->lines->advance();

        $this->version = explode(" => ", $this->lines->consume())[1];

        // Ok now we start with General info
        $this->modules = collect([$this->processModule('General')]);

        // Now we have a divider
        $this->lines->advance();

        // Now go through all the rest
        $this->processModules();
        $this->lines->advance();

        $this->modules->push($this->processCredits());
        $this->modules->push($this->processLicense());
    }

    protected function processModules()
    {
        while($this->lines->isModuleName()) {
            $this->modules->push(
                $this->processModule($this->lines->consume())
            );
        }
    }

    protected function processModule($name)
    {
        return new Module($name, $this->processGroups());
    }

    protected function processGroups()
    {
        $groups = collect();

        while($group = $this->processGroup()) {
            $groups->push($group);
        }

        return $groups;
    }

    protected function processGroup(): Group|false
    {
        $configs = collect();
        $headings = collect();
        $name = null;
        $note = null;

        // If we have a group title, it comes first
        if($this->lines->isGroupTitle()) {
            $name = $this->lines->consume();
        }

        // Then headings, optionally
        if($this->lines->isTableHeading()) {
            $headings = collect(explode(" => ", $this->lines->consume()));
        }

        // We should have config entries with items by now, or else we can't proceed
        if(!$this->lines->hasItems()) {
            return false;
        }

        $count = $this->lines->items()->count();

        while($config = $this->processConfig()) {
            $configs->push($config);

            if($this->lines->items()->count() !== $count) {
                // The number of values just changed, we need to start a new group
                break;
            }
        }

        if($this->lines->isNote()) {
            $note = $this->lines->consumeUntil(fn($line) => $line === '')->filter()->implode("\n");
            $this->lines->advance();
        }

        return new Group($configs, $headings, $name, trim($note));
    }

    protected function processConfig(): Config|false
    {
        if(!$this->lines->hasItems()) {
            return false;
        }

        $items = $this->lines->consumeItems();

        // A value might be split across multiple lines, each ending with a comma
        while(str_ends_with($items->localValue(), ",")) {
            $items->appendLocalValue("\n" . $this->lines->consume());
        }

        // A value might be an Array dump, across multiple lines
        if(str_ends_with($items->localValue(), "Array")) {
            $items->appendLocalValue(
                "\n" . $this->lines->consumeUntil(fn($line) => $line == ")")->implode("\n")
            );
        }

        return Config::fromValues($items);
    }

    protected function processCredits(): Module
    {
        // Our credit groups are a bit odd. Some are simple with just a list of names, which can look
        // like a "note" to this parser. We're going to walk through each of these manually.

        $moduleName = $this->lines->consume();
        $groups = collect();

        // PHP Group
        $groups->push(Group::simple($this->lines->consume(), "Names", $this->lines->consume()));

        // Language Design & Concept
        $groups->push(Group::simple($this->lines->consume(), "Names", $this->lines->consume()));

        // PHP Authors
        $groups->push($this->processGroup());
        // SAPI Modules
        $groups->push($this->processGroup());
        // Module Authors
        $groups->push($this->processGroup());
        // PHP Documentation
        $groups->push($this->processGroup());

        // PHP Quality Assurance Team
        $groups->push(Group::simple($this->lines->consume(), "Names", $this->lines->consume()));

        // Websites and Infrastructure team
        $groups->push($this->processGroup());

        return new Module($moduleName, $groups);
    }

    protected function processLicense(): Module
    {
        return new Module(
            $this->lines->consume(),
            collect([
                Group::noteOnly(
                    $this->lines
                        ->consumeUntil(fn($line) => str_contains($line, 'license@php.net'))
                        ->implode("\n")
                )]
            )
        );
    }
}