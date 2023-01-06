<?php

namespace STS\Phpinfo\Collections;

use Illuminate\Support\Collection;

/**
 * Used by our TextParser to help walk through the CLI text version
 */
class Lines extends Collection
{
    protected int $index = 0;

    /**
     * Moves one line forward, regardless of what it is
     */
    public function step(): string|null
    {
        $this->index++;

        return $this->current();
    }

    /**
     * Advances to the next usable line and returns the new line
     */
    public function advance(): string|null
    {
        do {
            $this->index++;
        } while($this->shouldIgnore());

        return $this->current();
    }

    /**
     * Similar to the above advance() method, except this returns
     * the CURRENT line before advancing
     */
    public function consume(): string|null
    {
        $current = $this->current();

        $this->advance();

        return $current;
    }

    public function consumeUntil(callable $callback): Collection
    {
        $lines = new static;

        do {
            $current = $this->current();
            $lines->push($current);
            $this->step();
        } while(!$callback($current));

        return $lines;
    }

    public function shouldIgnore(): bool
    {
        return $this->currentIsBlank()
            // || str_contains($this->current(), '_______________________________________________________________________')
            || in_array($this->current(), ['Configuration']);
    }

    public function currentIsBlank(): bool
    {
        return $this->current() === '';
    }

    public function previousIsBlank(): bool
    {
        return $this->previous() === '';
    }

    public function nextIsBlank(): bool
    {
        return $this->next() === '';
    }

    public function current(): string|null
    {
        return $this->get($this->index);
    }

    public function previous(): string|null
    {
        return $this->get($this->index - 1);
    }

    public function next(): string|null
    {
        return $this->get($this->index + 1);
    }

    public function isDivider(): bool
    {
        return str_contains($this->current(), '_______________________________________________________________________');
    }

    public function isModuleName(): bool
    {
        return !$this->hasItems()
            && $this->nextIsBlank()
            && !$this->isGroupTitle()
            && strlen($this->current()) < 50;
    }

    public function isGroupTitle(): bool
    {
        // Some group titles have obvious signals
        if(
            str_contains($this->current(), "                     ")
            || in_array($this->current(), ["Module Name"])
        ) {
            return true;
        }

        // Some look like group titles but aren't
        if(in_array($this->current(), ["PHP License"])) {
            return false;
        }

        // Otherwise we have a pattern
        return !$this->hasItems()
            && !$this->nextIsBlank()
            && strlen($this->current()) < 50;
    }

    public function isTableHeading(): bool
    {
        return in_array($this->items()->first(), ['Directive', 'Variable', 'Contribution', 'Module']);
    }

    public function isNote(): bool
    {
        return !$this->hasItems()
            && !$this->isDivider()
            && !$this->isGroupTitle()
            && strlen($this->current()) > 50;
    }

    public function items(): Items
    {
        $items = Items::make(explode(" => ", $this->current()));

        // A few weird cases we need to fix
        if($items->first() == "Features" && $items->count() == 1) {
            $items->put(1, null);
        }

        return $items;
    }

    public function hasItems(): bool
    {
        return $this->items()->count() > 1;
    }

    public function consumeItems(): Items
    {
        $items = $this->items();

        $this->advance();

        return $items;
    }

    public function startAt($contents): string|null
    {
        if($index = $this->search($contents)) {
            $this->index = $index;
            return $this->current();
        }

        return null;
    }
}