<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;
use JsonSerializable;
use STS\Phpinfo\Support\Str;

class Module implements JsonSerializable
{
    public function __construct(
        protected string $name,
        protected Collection $groups,
    ) {}

    public function key(): string
    {
        return 'module_' . Str::slug($this->name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function groups(): Collection
    {
        return $this->groups;
    }

    public function configs(): Collection
    {
        return $this->groups()->flatMap->configs();
    }

    public function hasConfig(string $name): bool
    {
        return $this->findConfig($name) !== null;
    }

    public function config(string $name, string $which = 'local'): ?string
    {
        return $this->findConfig($name)?->value($which);
    }

    public function combinedKeyFor(Config $config): string
    {
        return $this->key() . '_' . $config->key();
    }

    public function jsonSerialize(): mixed
    {
        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'groups' => $this->groups()->values(),
        ];
    }

    protected function findConfig(string $name): ?Config
    {
        $slug = Str::slug($name);

        return $this->configs()
            ->first(fn(Config $config) => Str::slug($config->name()) === $slug);
    }
}
