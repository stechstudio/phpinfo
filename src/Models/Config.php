<?php

namespace STS\Phpinfo\Models;

use JsonSerializable;
use STS\Phpinfo\Support\Str;

class Config implements JsonSerializable
{
    public function __construct(
        protected string $name,
        protected ?string $localValue = null,
        protected ?string $masterValue = null,
        protected bool $hasMasterValue = false,
    ) {}

    public static function fromValues(array $values): static
    {
        $hasThreeColumns = count($values) >= 3;

        return new static(
            name: $values[0],
            localValue: ($values[1] ?? null) === 'no value' ? null : ($values[1] ?? null),
            masterValue: $hasThreeColumns ? (($values[2] ?? null) === 'no value' ? null : ($values[2] ?? null)) : null,
            hasMasterValue: $hasThreeColumns,
        );
    }

    public function key(): string
    {
        return $this->name === 'Names'
            ? 'config_names_'.md5((string) $this->localValue())
            : 'config_'.Str::slug($this->name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value(string $which = 'local'): ?string
    {
        return match ($which) {
            'local' => $this->localValue(),
            'master' => $this->masterValue(),
            default => throw new \InvalidArgumentException("Invalid value type '{$which}'. Expected 'local' or 'master'."),
        };
    }

    public function localValue(): ?string
    {
        return $this->localValue;
    }

    public function hasMasterValue(): bool
    {
        return $this->hasMasterValue;
    }

    public function masterValue(): ?string
    {
        return $this->hasMasterValue ? $this->masterValue : null;
    }

    public function __toString(): string
    {
        return (string) $this->value();
    }

    public function jsonSerialize(): mixed
    {
        return [
            'key' => $this->key(),
            'name' => $this->name(),
            'hasMasterValue' => $this->hasMasterValue(),
            'localValue' => $this->localValue() ?? 'no value',
            'masterValue' => $this->hasMasterValue() ? ($this->masterValue() ?? 'no value') : null,
        ];
    }
}
