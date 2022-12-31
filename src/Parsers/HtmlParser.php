<?php

namespace STS\Phpinfo\Parsers;

use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;
use Illuminate\Support\Collection;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\General;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Info;

class HtmlParser extends Info
{
    protected function parse(): void
    {
        $document = new DOMDocument;
        $document->loadHTML(str_replace("\n", "", $this->contents));
        $xpath = new DOMXpath($document);

        $this->version = str_replace('PHP Version ', '', $xpath->query('//body//h1')[0]->nodeValue);

        $this->general = new General(
            collect($xpath->query('//body//table[2]/tr'))
                ->map(
                    fn(DOMElement $row) => new Config(
                        trim($row->firstChild->nodeValue), trim($row->lastChild->nodeValue)
                    )
                )->keyBy(fn(Config $config) => strtolower($config->name()))
        );

        $this->modules = collect($xpath->query('//body//h2'))
            ->reject(fn(DOMElement $heading) => $heading->nodeValue === 'PHP License')
            ->mapWithKeys(fn(DOMElement $heading) => [
                strtolower($heading->nodeValue) => new Module($heading->nodeValue, $this->findConfigsFor($heading))
            ]);

        $this->configs = $this->modules->map->configs()->flatten()->keyBy(fn(Config $config) => strtolower($config->name()));
    }

    protected function findConfigsFor(DOMElement $heading): Collection
    {
        $configs = collect();
        $current = $heading;

        while ($current->nextSibling->nodeName === "table") {
            $current = $current->nextSibling;

            $configs->push(
                collect($current->childNodes)
                    ->filter(fn($node) => $node instanceof DOMElement && $node->nodeName === "tr")
                    ->map(fn(DOMElement $row) => $this->rowToValues($row))
                    ->map(fn(Collection $values) => Config::fromValues($values))
                    ->reject(fn(Config $config) => in_array($config->name(), ['Directive','Variable']))
            );
        }

        return $configs->flatten()->keyBy(fn(Config $setting) => strtolower($setting->name()));
    }

    protected function rowToValues(DOMElement $row): Collection
    {
        return collect($row->childNodes)
            ->reject(fn($node) => $node instanceof DOMText)
            ->map(fn(DOMElement $cell) => trim($cell->nodeValue));
    }
}