<?php

namespace STS\Phpinfo\Parsers;

use InvalidArgumentException;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\PhpInfo;
use STS\Phpinfo\Support\Items;

class TextParser implements Parser
{
    protected array $lines;

    protected int $pos;

    protected int $len;

    public function __construct(protected string $contents)
    {
        if (! static::canParse($contents)) {
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
        $this->lines = explode("\n", str_replace("\r\n", "\n", $this->contents));
        $this->pos = 0;
        $this->len = count($this->lines);

        // Skip "phpinfo()"
        $this->advance();

        // PHP Version
        $version = '';
        if ($this->hasItems() && ($this->items()[0] ?? '') === 'PHP Version') {
            $version = $this->items()[1] ?? '';
            $this->advance();
        }

        $modules = items();

        // General module
        $general = $this->parseModule('General');
        if ($general->groups()->isNotEmpty()) {
            $modules->push($general);
        }

        // Skip divider
        if ($this->pos < $this->len && $this->isDivider()) {
            $this->advance();
        }

        // Module loop
        while ($this->pos < $this->len) {
            $line = $this->current();
            if ($line === null) {
                break;
            }
            if ($this->isDivider()) {
                $this->advance();

                continue;
            }
            if ($line === 'PHP Credits' || $line === 'PHP License') {
                break;
            }

            // Module name detection: no " => ", next line is blank, short text
            if (! $this->hasItems() && strlen($line) < 80 && ($this->pos + 1 >= $this->len || $this->lines[$this->pos + 1] === '')) {
                $moduleName = $line;
                $this->advance();
                $modules->push($this->parseModule($moduleName));
            } else {
                break;
            }
        }

        // Credits and License
        $this->parseCredits($modules);
        $this->parseLicense($modules);

        // Filter out empty modules (e.g. "Module Name" under Additional Modules)
        $modules = $modules->filter(fn (Module $m) => $m->groups()->isNotEmpty());

        return new PhpInfo($version, $modules);
    }

    private function current(): ?string
    {
        return $this->pos < $this->len ? $this->lines[$this->pos] : null;
    }

    private function advance(): void
    {
        do {
            $this->pos++;
        } while ($this->pos < $this->len && ($this->lines[$this->pos] === '' || $this->lines[$this->pos] === 'Configuration'));
    }

    private function isDivider(): bool
    {
        return $this->current() !== null && str_contains($this->current(), '_______________________________________________________________________');
    }

    private function hasItems(): bool
    {
        return $this->current() !== null && str_contains($this->current(), ' => ');
    }

    private function items(): array
    {
        return explode(' => ', $this->current() ?? '');
    }

    private function parseModule(string $name): Module
    {
        $groups = items();

        while ($this->pos < $this->len) {
            $group = $this->parseGroup();
            if ($group === null) {
                break;
            }
            $groups->push($group);
        }

        return new Module($name, $groups);
    }

    private function parseGroup(): ?Group
    {
        if ($this->pos >= $this->len) {
            return null;
        }
        $line = $this->lines[$this->pos] ?? null;
        if ($line === null) {
            return null;
        }

        // Stop at dividers or blank-followed-by-short (module names)
        if (str_contains($line, '_______________________________________________________________________')) {
            return null;
        }
        if ($line === '') {
            $this->pos++;

            return $this->parseGroup();
        }

        // Check if this looks like a module name (stop parsing groups)
        if (! str_contains($line, ' => ') && strlen($line) < 80 && ($this->pos + 1 >= $this->len || $this->lines[$this->pos + 1] === '')) {
            return null;
        }

        $groupName = null;
        $headings = [];
        $configs = [];
        $note = null;

        // Group title: no " => ", next line is NOT blank, short text, not a heading keyword
        if (! str_contains($line, ' => ') && strlen($line) < 80
            && ($this->pos + 1 < $this->len && $this->lines[$this->pos + 1] !== '')
            && ! in_array($line, ['Directive', 'Variable', 'Contribution', 'Module'])) {

            // But also check it's not a module name
            if (! str_contains($line, '                     ') && ($this->pos + 1 < $this->len && str_contains($this->lines[$this->pos + 1], ' => ') || in_array($this->lines[$this->pos + 1] ?? '', ['Directive', 'Variable']))) {
                $groupName = $line;
                do {
                    $this->pos++;
                } while ($this->pos < $this->len && $this->lines[$this->pos] === '');
                $line = $this->lines[$this->pos] ?? null;
                if ($line === null) {
                    return null;
                }
            }
        }

        // Table heading
        $parts = explode(' => ', $line);
        if (in_array($parts[0], ['Directive', 'Variable', 'Contribution', 'Module'])) {
            $headings = $parts;
            do {
                $this->pos++;
            } while ($this->pos < $this->len && $this->lines[$this->pos] === '');
            $line = $this->lines[$this->pos] ?? null;
        }

        // Determine expected column count
        $expectedCols = count($headings) ?: null;

        // Parse config rows
        while ($this->pos < $this->len) {
            $line = $this->lines[$this->pos];
            if ($line === '' || str_contains($line, '_______________________________________________________________________')) {
                break;
            }

            if (! str_contains($line, ' => ')) {
                // Could be a note or end of group
                if (strlen($line) > 50) {
                    $noteLines = [];
                    while ($this->pos < $this->len && $this->lines[$this->pos] !== '' && ! str_contains($this->lines[$this->pos], '_____')) {
                        $noteLines[] = $this->lines[$this->pos];
                        $this->pos++;
                    }
                    $note = trim(implode("\n", $noteLines));
                    break;
                }
                break;
            }

            $parts = explode(' => ', $line);
            $configName = $parts[0];
            $localValue = $parts[1] ?? null;
            $masterValue = null;
            $hasMaster = false;

            if ($expectedCols === 3 && count($parts) >= 3) {
                $masterValue = $parts[2];
                $hasMaster = true;
            } elseif ($expectedCols === null && count($parts) >= 3) {
                $masterValue = $parts[2];
                $hasMaster = true;
            }

            // Multi-line values (comma-continued)
            while ($localValue !== null && str_ends_with($localValue, ',') && $this->pos + 1 < $this->len) {
                $this->pos++;
                $localValue .= "\n".$this->lines[$this->pos];
            }

            // Multi-line Array dumps (e.g. $_SERVER['argv'] => Array\n(\n...\n))
            if ($localValue !== null && $localValue === 'Array') {
                $this->pos++;
                while ($this->pos < $this->len && trim($this->lines[$this->pos]) !== ')') {
                    $localValue .= "\n".$this->lines[$this->pos];
                    $this->pos++;
                }
                if ($this->pos < $this->len) {
                    $localValue .= "\n".$this->lines[$this->pos];
                }
            }

            $configs[] = new Config(
                $configName,
                ($localValue === 'no value') ? null : $localValue,
                $hasMaster ? (($masterValue === 'no value') ? null : $masterValue) : null,
                $hasMaster,
            );

            $this->pos++;
        }

        if (empty($configs) && $note === null && $groupName === null) {
            return null;
        }

        return new Group(
            items($configs),
            ! empty($headings) ? items($headings) : null,
            $groupName,
            $note,
        );
    }

    private function parseCredits(Items $modules): void
    {
        if ($this->pos >= $this->len || $this->lines[$this->pos] !== 'PHP Credits') {
            return;
        }

        $this->pos++; // skip "PHP Credits"
        while ($this->pos < $this->len && $this->lines[$this->pos] === '') {
            $this->pos++;
        }

        $groups = [];

        while ($this->pos < $this->len) {
            $line = $this->lines[$this->pos];
            if ($line === '' && ($this->pos + 1 >= $this->len || $this->lines[$this->pos + 1] === 'PHP License' || str_contains($this->lines[$this->pos + 1] ?? '', '____'))) {
                break;
            }
            if ($line === 'PHP License' || str_contains($line, '_______________________________________________________________________')) {
                break;
            }

            // Centered title (padded with spaces)
            if (str_contains($line, '                     ')) {
                $groupName = trim($line);
                $this->pos++;
                while ($this->pos < $this->len && $this->lines[$this->pos] === '') {
                    $this->pos++;
                }

                // Table heading?
                $headings = [];
                $firstWord = explode(' => ', $this->lines[$this->pos] ?? '')[0] ?? '';
                if (in_array($firstWord, ['Contribution', 'Module', 'Authors'])) {
                    $headings = explode(' => ', $this->lines[$this->pos]);
                    $this->pos++;
                    while ($this->pos < $this->len && $this->lines[$this->pos] === '') {
                        $this->pos++;
                    }
                }

                // Config rows
                $configs = [];
                while ($this->pos < $this->len && $this->lines[$this->pos] !== '' && ! str_contains($this->lines[$this->pos], '______')) {
                    if (str_contains($this->lines[$this->pos], ' => ')) {
                        $parts = explode(' => ', $this->lines[$this->pos]);
                        $configs[] = new Config($parts[0], $parts[1] ?? null);
                    } else {
                        break;
                    }
                    $this->pos++;
                }
                while ($this->pos < $this->len && $this->lines[$this->pos] === '') {
                    $this->pos++;
                }

                if (! empty($configs)) {
                    $groups[] = new Group(
                        items($configs),
                        ! empty($headings) ? items($headings) : null,
                        $groupName,
                    );
                }
            }
            // Simple title + value pair (e.g. "PHP Group" followed by names)
            elseif (! str_contains($line, ' => ') && strlen($line) < 80) {
                $groupName = $line;
                $this->pos++;
                while ($this->pos < $this->len && $this->lines[$this->pos] === '') {
                    $this->pos++;
                }
                $value = ($this->pos < $this->len && $this->lines[$this->pos] !== '' && ! str_contains($this->lines[$this->pos], '______')) ? $this->lines[$this->pos] : null;
                if ($value !== null) {
                    $this->pos++;
                }
                while ($this->pos < $this->len && $this->lines[$this->pos] === '') {
                    $this->pos++;
                }

                $groups[] = new Group(
                    $value ? items([new Config('Names', $value)]) : items(),
                    name: $groupName,
                );
            } else {
                break;
            }
        }

        if (! empty($groups)) {
            $modules->push(new Module('PHP Credits', items($groups)));
        }
    }

    private function parseLicense(Items $modules): void
    {
        if ($this->pos >= $this->len || $this->lines[$this->pos] !== 'PHP License') {
            return;
        }

        $this->pos++; // skip "PHP License"
        while ($this->pos < $this->len && $this->lines[$this->pos] === '') {
            $this->pos++;
        }

        $text = [];
        while ($this->pos < $this->len) {
            $text[] = $this->lines[$this->pos];
            $this->pos++;
        }

        $note = trim(implode("\n", $text));
        if ($note !== '') {
            $modules->push(new Module('PHP License', items([Group::noteOnly($note)])));
        }
    }
}
