<?php

namespace STS\Phpinfo;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use JsonSerializable;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\Parsers\TextParser;

abstract class Result implements JsonSerializable
{

    protected string $version;
    protected Collection $modules;
    protected Collection $configs;

    public function __construct(protected string $contents)
    {
        if(!static::canParse($contents)) {
            throw new InvalidArgumentException('Contents provided does not appear to be valid phpinfo() output');
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
        return $this->modules->get(strtolower($key));
    }

    public function modules(): Collection
    {
        return $this->modules;
    }

    public function hasConfig($key): bool
    {
        return $this->configs()->has(strtolower($key));
    }

    public function config($key, $which = "local"): string|null
    {

        return $this->configs()->get(strtolower($key))?->value($which);
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
        include(__DIR__ . "/resources/views/default.php");
    }

    public function jsonSerialize()
    {
        return [
            'version' => $this->version(),
            'modules' => $this->modules()
        ];
    }
}