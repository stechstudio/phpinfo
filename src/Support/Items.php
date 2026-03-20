<?php

namespace STS\Phpinfo\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

class Items implements Countable, IteratorAggregate, JsonSerializable
{
    protected array $items;

    public function __construct(iterable $items = [])
    {
        $this->items = is_array($items) ? $items : iterator_to_array($items, false);
    }

    public function push(mixed $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    public function prepend(mixed $item): static
    {
        array_unshift($this->items, $item);

        return $this;
    }

    public function first(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return $this->items[0] ?? null;
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return null;
    }

    public function last(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return $this->items ? end($this->items) : null;
        }

        $result = null;
        foreach ($this->items as $item) {
            if ($callback($item)) {
                $result = $item;
            }
        }

        return $result;
    }

    public function get(int $index): mixed
    {
        return $this->items[$index] ?? null;
    }

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function flatMap(callable $callback): static
    {
        $result = [];

        foreach ($this->items as $item) {
            $values = $callback($item);
            foreach ($values as $value) {
                $result[] = $value;
            }
        }

        return new static($result);
    }

    public function filter(?callable $callback = null): static
    {
        return new static(array_values(
            $callback ? array_filter($this->items, $callback) : array_filter($this->items)
        ));
    }

    public function reject(callable $callback): static
    {
        return $this->filter(fn($item) => !$callback($item));
    }

    public function implode(string $glue): string
    {
        return implode($glue, $this->items);
    }

    public function skip(int $count): static
    {
        return new static(array_slice($this->items, $count));
    }

    public function unique(): static
    {
        return new static(array_values(array_unique($this->items)));
    }

    public function values(): static
    {
        return new static(array_values($this->items));
    }

    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function jsonSerialize(): array
    {
        return array_values(array_map(
            fn($item) => $item instanceof JsonSerializable ? $item->jsonSerialize() : $item,
            $this->items
        ));
    }
}
