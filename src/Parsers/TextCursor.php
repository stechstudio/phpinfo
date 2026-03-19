<?php

namespace STS\Phpinfo\Parsers;

/**
 * A stateful cursor for walking through lines of text.
 * Used by TextParser to process CLI phpinfo() output.
 */
class TextCursor
{
    /** @var list<string> */
    protected array $lines;
    protected int $index = 0;

    public function __construct(string $content)
    {
        $this->lines = explode("\n", str_replace("\r\n", "\n", $content));
    }

    public function isAtEnd(): bool
    {
        return $this->index >= count($this->lines);
    }

    public function current(): ?string
    {
        return $this->lines[$this->index] ?? null;
    }

    public function next(): ?string
    {
        return $this->lines[$this->index + 1] ?? null;
    }

    public function previous(): ?string
    {
        return $this->lines[$this->index - 1] ?? null;
    }

    /**
     * Move one line forward regardless of content.
     */
    public function step(): ?string
    {
        $this->index++;

        return $this->current();
    }

    /**
     * Advance to the next meaningful line (skipping blank/ignored lines).
     */
    public function advance(): ?string
    {
        do {
            $this->index++;

            if ($this->isAtEnd()) {
                return null;
            }
        } while ($this->shouldSkip());

        return $this->current();
    }

    /**
     * Return the current line, then advance to the next meaningful line.
     */
    public function consume(): ?string
    {
        $current = $this->current();
        $this->advance();

        return $current;
    }

    /**
     * Consume lines until the predicate returns true.
     * Returns all consumed lines (including the one that matched).
     *
     * @return list<string>
     */
    public function consumeUntil(callable $predicate): array
    {
        $collected = [];

        do {
            $current = $this->current();
            $collected[] = $current;
            $this->step();
        } while (!$predicate($current) && !$this->isAtEnd());

        return $collected;
    }

    /**
     * Split the current line by the given delimiter.
     *
     * @return list<string|null>
     */
    public function items(string $delimiter = ' => '): array
    {
        $parts = explode($delimiter, $this->current() ?? '');

        // Edge case: "Features" line with no value
        if ($parts[0] === 'Features' && count($parts) === 1) {
            $parts[1] = null;
        }

        return $parts;
    }

    public function itemCount(string $delimiter = ' => '): int
    {
        return count($this->items($delimiter));
    }

    public function hasItems(string $delimiter = ' => '): bool
    {
        return $this->itemCount($delimiter) > 1;
    }

    /**
     * Return items from the current line, then advance.
     *
     * @return list<string|null>
     */
    public function consumeItems(string $delimiter = ' => '): array
    {
        $items = $this->items($delimiter);
        $this->advance();

        return $items;
    }

    /**
     * Search for a line matching the given content and jump to it.
     * Returns true if found, false otherwise.
     */
    public function jumpTo(string $content): bool
    {
        $index = array_search($content, $this->lines);

        if ($index !== false) {
            $this->index = $index;
            return true;
        }

        return false;
    }

    protected function shouldSkip(): bool
    {
        return $this->current() === ''
            || $this->current() === 'Configuration';
    }
}
