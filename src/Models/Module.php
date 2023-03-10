<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;
use JsonSerializable;
use STS\Phpinfo\Traits\Slugifies;

class Module implements JsonSerializable
{
    use Slugifies;

    public function __construct(
        protected string $name,
        protected Collection $groups
    )
    {}

    public function key(): string
    {
        return "module_" . $this->slugify($this->name);
    }

    public function combinedKeyFor(Config $config): string
    {
        return $this->key() . "_" . $config->key();
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
        return $this->configs()->first(fn($config) => $config->key() === $this->slugify($key)) !== null;
    }

    public function config($key, $which = "local"): string|null
    {
        return $this->configs()
            ->first(fn($config) => $config->key() === $this->slugify($key))
            ?->value($which);
    }

    public function configs(): Collection
    {
        return $this->groups()->flatMap->configs();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "key" => $this->key(),
            "name" => $this->name(),
            "groups" => $this->groups()->values()
        ];
    }
}