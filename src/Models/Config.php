<?php

namespace STS\Phpinfo\Models;

use DiDom\Element;

class Config
{
    public function __construct(
        protected string $name,
        protected $localValue,
        protected $masterValue = false
    )
    {}

    public static function parse(Element $row)
    {
        $values = collect($row->children())->map(fn($cell) => trim($cell->text()));

        return new static(
            $values->get(0),
            $values->get(1)  === "no value" ? null : $values->get(1),
            $values->get(2)  === "no value" ? null : $values->get(2, false),
        );
    }

    public function name()
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
}