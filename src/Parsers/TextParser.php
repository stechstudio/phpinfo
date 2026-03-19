<?php

namespace STS\Phpinfo\Parsers;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\PhpInfo;

class TextParser implements Parser
{
    protected TextCursor $cursor;

    public function __construct(protected string $contents)
    {
        if (!static::canParse($contents)) {
            throw new InvalidArgumentException('Content provided does not appear to be valid phpinfo() text output');
        }
    }

    public static function canParse(string $contents): bool
    {
        return str_contains(str_replace("\r\n", "\n", $contents), "phpinfo()\nPHP Version")
            && count(explode('_______________________________________________________________________', $contents)) >= 2;
    }

    public function parse(): PhpInfo
    {
        $this->cursor = new TextCursor($this->contents);

        // First line is "phpinfo()"
        $this->cursor->advance();

        // PHP Version line
        $version = $this->cursor->consumeItems()[1] ?? '';

        // General info
        $modules = collect([$this->parseModule('General')]);

        // Divider
        $this->cursor->advance();

        // All other modules
        while ($this->isModuleName()) {
            $modules->push($this->parseModule($this->cursor->consume()));
        }

        $this->cursor->advance();

        // Credits and License (optional sections)
        $this->parseCredits($modules);
        $this->parseLicense($modules);

        return new PhpInfo($version, $modules);
    }

    // ── Module / Group / Config parsing ──────────────────────────────

    protected function parseModule(string $name): Module
    {
        return new Module($name, $this->parseGroups());
    }

    protected function parseGroups(): Collection
    {
        $groups = collect();

        while ($group = $this->parseGroup()) {
            $groups->push($group);
        }

        return $groups;
    }

    protected function parseGroup(): Group|false
    {
        $configs = collect();
        $headings = collect();
        $name = null;
        $note = null;

        if ($this->isGroupTitle()) {
            $name = $this->cursor->consume();
        }

        if ($this->isTableHeading()) {
            $headings = collect(explode(' => ', $this->cursor->consume()));
        }

        if (!$this->cursor->hasItems()) {
            return false;
        }

        $count = $this->cursor->itemCount();

        while ($config = $this->parseConfig()) {
            $configs->push($config);

            if ($this->cursor->itemCount() !== $count) {
                break;
            }
        }

        if ($this->isNote()) {
            $note = implode("\n", array_filter($this->cursor->consumeUntil(fn($line) => $line === '')));
            $this->cursor->advance();
        }

        return new Group($configs, $headings, $name, $note !== null ? trim($note) : null);
    }

    protected function parseConfig(): Config|false
    {
        if (!$this->cursor->hasItems()) {
            return false;
        }

        $items = $this->cursor->consumeItems();

        // Multi-line values ending with comma
        while (isset($items[1]) && str_ends_with($items[1], ',')) {
            $items[1] .= "\n" . $this->cursor->consume();
        }

        // Multi-line Array dumps
        if (isset($items[1]) && str_ends_with($items[1], 'Array')) {
            $arrayLines = $this->cursor->consumeUntil(fn($line) => $line === ')');
            $items[1] .= "\n" . implode("\n", $arrayLines);
            $this->cursor->advance();
        }

        return Config::fromValues($items);
    }

    // ── Credits & License ────────────────────────────────────────────

    protected function parseCredits(Collection $modules): void
    {
        if (!$this->cursor->jumpTo('PHP Credits')) {
            return;
        }

        $moduleName = $this->cursor->consume();
        $groups = collect();

        // PHP Group
        $groups->push(Group::simple($this->cursor->consume(), 'Names', $this->cursor->consume()));
        // Language Design & Concept
        $groups->push(Group::simple($this->cursor->consume(), 'Names', $this->cursor->consume()));

        // PHP Authors, SAPI Modules, Module Authors, PHP Documentation
        for ($i = 0; $i < 4; $i++) {
            if ($group = $this->parseGroup()) {
                $groups->push($group);
            }
        }

        // PHP Quality Assurance Team
        $groups->push(Group::simple($this->cursor->consume(), 'Names', $this->cursor->consume()));
        // Websites and Infrastructure team
        if ($group = $this->parseGroup()) {
            $groups->push($group);
        }

        $modules->push(new Module($moduleName, $groups));
    }

    protected function parseLicense(Collection $modules): void
    {
        if (!$this->cursor->jumpTo('PHP License')) {
            return;
        }

        $modules->push(new Module(
            $this->cursor->consume(),
            collect([
                Group::noteOnly(
                    implode("\n", $this->cursor->consumeUntil(
                        fn($line) => str_contains($line, 'license@php.net')
                    ))
                ),
            ])
        ));
    }

    // ── Line type detection ──────────────────────────────────────────

    protected function isModuleName(): bool
    {
        return !$this->cursor->hasItems()
            && $this->cursor->next() === ''
            && !$this->isGroupTitle()
            && strlen($this->cursor->current() ?? '') < 50;
    }

    protected function isGroupTitle(): bool
    {
        $current = $this->cursor->current();

        if ($current === null) {
            return false;
        }

        // Obvious signals
        if (str_contains($current, '                     ') || $current === 'Module Name') {
            return true;
        }

        // Exceptions
        if ($current === 'PHP License') {
            return false;
        }

        return !$this->cursor->hasItems()
            && $this->cursor->next() !== ''
            && strlen($current) < 50;
    }

    protected function isTableHeading(): bool
    {
        $items = $this->cursor->items();

        return in_array($items[0] ?? null, ['Directive', 'Variable', 'Contribution', 'Module']);
    }

    protected function isNote(): bool
    {
        $current = $this->cursor->current();

        return $current !== null
            && !$this->cursor->hasItems()
            && !str_contains($current, '_______________________________________________________________________')
            && !$this->isGroupTitle()
            && strlen($current) > 50;
    }
}
