<?php

namespace STS\Phpinfo;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use JsonSerializable;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\Parsers\TextParser;
use STS\Phpinfo\Traits\ConfigAliases;
use STS\Phpinfo\Traits\Slugifies;

abstract class Result implements JsonSerializable
{
    use Slugifies, ConfigAliases;

    protected string $version;
    protected Collection $modules;
    protected Collection $configs;

    public function __construct(protected string $contents)
    {
        if(!static::canParse($contents)) {
            throw new InvalidArgumentException('Content provided does not appear to be valid phpinfo() output');
        }

        $this->parse();
    }

    abstract public static function canParse(string $contents): bool;
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
        return $this->modules()
            ->first(fn($module) => $module->key() === $this->slugify($key));
    }

    public function modules(): Collection
    {
        return $this->modules;
    }

    public function hasConfig($key): bool
    {
        return $this->configs()->first(fn($config) => $config->key() === $this->slugify($key)) !== null;
    }

    public function config($key, $which = "local"): string|null
    {
        if(in_array($key, $this->aliases)) {
            $aliasMethod = "get" . ucfirst($key);
            return $this->$aliasMethod();
        }

        return $this->configs()
            ->first(fn($config) => $config->key() === $this->slugify($key))
            ?->value($which);
    }

    public function configs(): Collection
    {
        if(!isset($this->configs)) {
           $this->configs = $this->modules()->flatMap->configs();
        }

        return $this->configs;
    }

    public function render()
    {
        $info = $this;
        include(__DIR__ . "/../dist/default.php");
    }

    public function jsonSerialize(): mixed
    {
        return [
            'version' => $this->version(),
            'modules' => $this->modules()
        ];
    }
}