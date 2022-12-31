<?php

namespace STS\Phpinfo\Parsers;

use DiDom\Document;
use DiDom\Element;
use Illuminate\Support\Collection;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\General;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Info;

class HtmlParser extends Info
{
    protected function parse(): void
    {
        $document = new Document(str_replace("\n", "", $this->contents));

        $this->version = str_replace('PHP Version ', '', $document->find('h1')[0]->text());

        $this->general = new General(
            collect($document->xpath('//body//table[2]/tr'))
                ->map(
                    fn(Element $row) => new Config(
                        trim($row->firstChild()->text()), trim($row->lastChild()->text())
                    )
                )->keyBy(fn(Config $config) => strtolower($config->name()))
        );

        $this->modules = collect($document->find('h2'))
            ->reject(fn(Element $heading) => $heading->text() === 'PHP License')
            ->mapWithKeys(
                fn(Element $heading) => [strtolower($heading->text()) => new Module($heading->text(), $this->findConfigsFor($heading))]
            );

        $this->configuration = $this->modules->map->configurations()->flatten()->keyBy(fn(Config $config) => strtolower($config->name()));
    }

    protected function findConfigsFor(Element $heading): Collection
    {
        $configs = collect();
        $current = $heading;

        while ($current->nextSibling()?->tagName() === "table") {
            $current = $current->nextSibling();

            $configs->push(
                collect($current->children())
                    ->map(fn(Element $row) => Config::parse($row))
                    ->reject(fn(Config $config) => in_array($config->name(), ['Directive','Variable']))
            );
        }

        return $configs->flatten()->keyBy(fn(Config $setting) => strtolower($setting->name()));
    }
}