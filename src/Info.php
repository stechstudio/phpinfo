<?php

namespace STS\Phpinfo;

use Illuminate\Support\Collection;
use STS\Phpinfo\Models\General;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\Parsers\TextParser;

abstract class Info
{
    protected string $version;
    protected General $general;
    protected Collection $modules;
    protected Collection $configuration;

    public function __construct(protected string $contents)
    {
        $this->parse();
    }

    public static function capture(): static
    {
        ob_start();
        phpinfo();
        $contents = ob_get_clean();

        return PHP_SAPI === "cli"
            ? static::fromText($contents)
            : static::fromHtml($contents);
    }

    public static function fromHtml($contents): static
    {
        return new HtmlParser($contents);
    }

    public static function fromText($contents): static
    {
        return new TextParser($contents);
    }

    abstract protected function parse(): void;

    public function version(): string
    {
        return $this->version;
    }

    public function general($key): string
    {
        return $this->general->get($key)?->value();
    }

    public function generals(): Collection
    {
        return $this->general;
    }

    public function hasModule($key): bool
    {
        return $this->modules->has(strtolower($key));
    }

    public function hasConfig($key): bool
    {
        return $this->configuration->has(strtolower($key));
    }

    public function module($key): Module|null
    {
        return $this->modules->get($key);
    }

    public function config($key, $which = "local"): string|null
    {
        return $this->configuration->get($key)?->value($which);
    }

    public function modules(): Collection
    {
        return $this->modules;
    }

    public function configurations(): Collection
    {
        return $this->configuration;
    }
}