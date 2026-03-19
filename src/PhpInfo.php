<?php

namespace STS\Phpinfo;

use Illuminate\Support\Collection;
use JsonSerializable;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Support\Str;

class PhpInfo implements JsonSerializable
{
    protected ?Collection $flatConfigs = null;

    public function __construct(
        protected string $version,
        protected Collection $modules,
    ) {}

    public function version(): string
    {
        return $this->version;
    }

    public function modules(): Collection
    {
        return $this->modules;
    }

    public function module(string $name): ?Module
    {
        $slug = Str::slug($name);

        return $this->modules()
            ->first(fn(Module $module) => Str::slug($module->name()) === $slug);
    }

    public function hasModule(string $name): bool
    {
        return $this->module($name) !== null;
    }

    public function configs(): Collection
    {
        return $this->flatConfigs ??= $this->modules()->flatMap->configs();
    }

    public function config(string $name, string $which = 'local'): ?string
    {
        return $this->findConfig($name)?->value($which);
    }

    public function hasConfig(string $name): bool
    {
        return $this->findConfig($name) !== null;
    }

    /**
     * The operating system name, extracted from the System config.
     */
    public function os(): ?string
    {
        $system = $this->config('System');

        return $system ? explode(' ', $system)[0] : null;
    }

    /**
     * The hostname, extracted from the System config.
     */
    public function hostname(): ?string
    {
        $system = $this->config('System');

        return $system ? (explode(' ', $system)[1] ?? null) : null;
    }

    public function render(): void
    {
        $info = $this;
        include __DIR__ . '/../dist/default.php';
    }

    public function jsonSerialize(): mixed
    {
        return [
            'version' => $this->version(),
            'modules' => $this->modules()->values(),
        ];
    }

    protected function findConfig(string $name): ?Config
    {
        $slug = Str::slug($name);

        return $this->configs()
            ->first(fn(Config $config) => Str::slug($config->name()) === $slug);
    }
}
