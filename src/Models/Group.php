<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;

class Group
{
    public function __construct(
        protected Collection $configs,
        protected ?Collection $headings = null
    )
    {}

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
        return $this->headings;
    }
}