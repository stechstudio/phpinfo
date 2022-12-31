<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;

class Module
{
    public function __construct(
        protected string $name,
        protected Collection $configuration
    )
    {}

    public function name(): string
    {
        return $this->name;
    }

    public function configurations(): Collection
    {
        return $this->configuration;
    }

    public function hasConfig($key): bool
    {
        return $this->configuration->has(strtolower($key));
    }

    public function config($key, $which = "local"): string|null
    {
        return $this->configuration->get($key)?->value($which);
    }
}