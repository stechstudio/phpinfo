<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;
use JsonSerializable;

class Group implements JsonSerializable
{
    public function __construct(
        protected Collection $configs,
        protected ?Collection $headings = null,
        protected $title = null
    )
    {}

    public function title(): string|null
    {
        return $this->title;
    }

    public function configs(): Collection
    {
        return $this->configs;
    }

    public function hasHeadings(): bool
    {
        return $this->headings?->count() > 0;
    }

    public function headings(): Collection
    {
        return $this->hasHeadings()
            ? $this->headings
            : collect();
    }

    public function heading($index): string|null
    {
        return $this->headings->get($index);
    }

    public function shortHeading($index): string|null
    {
        return $this->shorten($this->headings->get($index));
    }

    protected function shorten($heading): string|null
    {
        return trim(str_replace("Value", "", $heading));
    }

    public function jsonSerialize(): mixed
    {
        return [
            "title" => $this->title(),
            "headings" => $this->headings(),
            "shortHeadings" => $this->headings()->map(fn($heading) => $this->shorten($heading)),
            "configs" => $this->configs()->values()
        ];
    }
}