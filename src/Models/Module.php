<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;

class Module
{
    public function __construct(
        protected string $name,
        protected Collection $configs
    )
    {}

    public function name(): string
    {
        return $this->name;
    }

    public function configs(): Collection
    {
        return $this->configs;
    }

    public function hasConfig($key): bool
    {
        return $this->configs->has(strtolower($key));
    }

    public function config($key, $which = "local"): string|null
    {
        return $this->configs->get($key)?->value($which);
    }
}