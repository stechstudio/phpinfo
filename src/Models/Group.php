<?php

namespace STS\Phpinfo\Models;

use JsonSerializable;
use STS\Phpinfo\Support\Items;
use STS\Phpinfo\Support\Str;

class Group implements JsonSerializable
{
    public function __construct(
        protected Items $configs,
        protected ?Items $headings = null,
        protected ?string $name = null,
        protected ?string $note = null,
    ) {}

    public static function simple(string $name, string $configName, string $contents): static
    {
        return new static(
            items([new Config($configName, $contents)]),
            name: $name,
        );
    }

    public static function noteOnly(string $note): static
    {
        return (new static(items()))->addNote($note);
    }

    public function addNote(string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function key(): string
    {
        return $this->name
            ? 'group_'.Str::slug($this->name)
            : 'group_'.md5($this->configs()->map(fn ($c) => $c->name())->implode(','));
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function note(): ?string
    {
        return $this->note;
    }

    public function configs(): Items
    {
        return $this->configs;
    }

    public function hasHeadings(): bool
    {
        return $this->headings !== null && $this->headings->count() > 0;
    }

    public function headings(): Items
    {
        return $this->hasHeadings() ? $this->headings : items();
    }

    public function heading(int $index): ?string
    {
        return $this->headings()?->get($index);
    }

    public function shortHeading(int $index): ?string
    {
        return $this->shorten($this->headings()?->get($index));
    }

    public function jsonSerialize(): mixed
    {
        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'headings' => $this->headings(),
            'shortHeadings' => $this->headings()->map(fn ($heading) => $this->shorten($heading)),
            'configs' => $this->configs()->values(),
            'note' => $this->note(),
        ];
    }

    protected function shorten(?string $heading): ?string
    {
        if ($heading === null) {
            return null;
        }

        return trim(str_replace('Value', '', $heading));
    }
}
