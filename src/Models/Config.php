<?php

namespace STS\Phpinfo\Models;

use Illuminate\Support\Collection;
use JsonSerializable;
use STS\Phpinfo\Traits\Slugifies;

class Config implements JsonSerializable
{
    use Slugifies;

    public function __construct(
        protected string $name,
        protected $localValue,
        protected $masterValue = false
    )
    {}

    public static function fromValues(Collection $values)
    {
        return new static(
            $values->get(0),
            $values->get(1)  === "no value" ? null : $values->get(1),
            $values->get(2)  === "no value" ? null : $values->get(2, false),
        );
    }

    public function key(): string
    {
        return $this->slugify($this->name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function value($which = "local"): string|null
    {
        return $which === "master" ? $this->masterValue() : $this->localValue();
    }

    public function localValue(): string|null
    {
        return $this->localValue;
    }

    public function hasMasterValue(): bool
    {
        return $this->masterValue !== false;
    }

    public function masterValue(): string|null
    {
        return $this->hasMasterValue()
            ? $this->masterValue
            : null;
    }

    public function __toString()
    {
        return (string) $this->value();
    }

    public function jsonSerialize(): mixed
    {
        return [
            "key" => $this->key(),
            "name" => $this->name(),
            "hasMasterValue" => $this->hasMasterValue(),
            "localValue" => $this->localValue(),
            "masterValue" => $this->masterValue(),
        ];
    }
}