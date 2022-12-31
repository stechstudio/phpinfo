<?php

namespace STS\Phpinfo;

use Illuminate\Support\Collection;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\General;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\Parsers\TextParser;

abstract class Info
{
    protected string $version;
    protected General $general;
    protected Collection $modules;
    protected Collection $configs;

    public function __construct(protected string $contents)
    {
        $this->parse();

        // Gather all the module configs as one flat collection
        $this->configs = $this->modules->map->configs()
            ->flatten()
            ->keyBy(fn(Config $config) => strtolower($config->name()));
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

    public function hasModule($key): bool
    {
        return $this->modules->has(strtolower($key));
    }

    public function module($key): Module|null
    {
        return $this->modules->get(strtolower($key));
    }

    public function modules(): Collection
    {
        return $this->modules;
    }

    public function hasConfig($key): bool
    {
        return $this->configs->has(strtolower($key));
    }

    public function config($key, $which = "local"): string|null
    {
        return $this->configs->get(strtolower($key))?->value($which);
    }

    public function configs(): Collection
    {
        return $this->configs;
    }
}