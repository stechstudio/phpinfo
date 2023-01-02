<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;
use JsonSerializable;

class Module implements JsonSerializable
{
    public function __construct(
        protected string $name,
        protected Collection $groups
    )
    {}

    public function key(): string
    {
        return strtolower($this->name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function groups(): Collection
    {
        return $this->groups;
    }

    public function hasConfig($key): bool
    {
        return $this->configs()->has(strtolower($key));
    }

    public function config($key, $which = "local"): string|null
    {
        return $this->configs()->get($key)?->value($which);
    }

    public function configs(): Collection
    {
        return $this->groups->flatMap->configs();
    }

    public function jsonSerialize()
    {
        return [
            "key" => $this->key(),
            "name" => $this->name(),
            "groups" => $this->groups()
        ];
    }
}