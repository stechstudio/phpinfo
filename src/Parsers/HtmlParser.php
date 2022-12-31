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
                // We know that the general table rows only have two columns
                ->map(fn(DOMElement $row) => new Config(
                    trim($row->firstChild->nodeValue), trim($row->lastChild->nodeValue)
                ))
                // Key by lowercase name for easy lookups
                ->keyBy(fn(Config $config) => strtolower($config->name()))
        );

        // For modules, we start by looking at all <h2> tags
        $this->modules = collect($xpath->query('//body//h2'))
            // Don't need the license in our collection
            ->reject(fn(DOMElement $heading) => $heading->nodeValue === 'PHP License')
            // Create the Module instance with all configs listed below the heading
            ->map(fn(DOMElement $heading) => new Module($heading->nodeValue, $this->findConfigsFor($heading)))
            // Key by lowercase name for easy lookups
            ->keyBy(fn(Module $module) => strtolower($module->name()));

        // Gather all the module configs as one flat collection
        $this->configs = $this->modules->map->configs()
            ->flatten()
            ->keyBy(fn(Config $config) => strtolower($config->name()));
    }

    protected function findConfigsFor(DOMElement $heading): Collection
    {
        $configs = collect();
        $current = $heading;

        // Modules often have multiple tables, we need to keep looking at siblings until it's no longer a table
        while ($current->nextSibling->nodeName === "table") {
            $current = $current->nextSibling;

            $configs->push(
                collect($current->childNodes)
                    // We only want <tr> nodes
                    ->filter(fn($node) => $node instanceof DOMElement && $node->nodeName === "tr" && $node->childNodes->length > 1)
                    // Get rid of header rows
                    ->reject(fn(DOMElement $node) => in_array($node->firstChild->nodeValue, ['Directive', 'Variable']))
                    // Parse out the field values
                    ->map(fn(DOMElement $row) => $this->rowToValues($row))
                    // And turn into a Config object
                    ->map(fn(Collection $values) => Config::fromValues($values))
            );
        }

        // We'll flatten our table configs, and key by lowercase name for easy lookups
        return $configs->flatten()->keyBy(fn(Config $setting) => strtolower($setting->name()));
    }

    protected function rowToValues(DOMElement $row): Collection
    {
        return collect($row->childNodes)
            ->reject(fn($node) => $node instanceof DOMText)
            ->map(fn(DOMElement $cell) => trim($cell->nodeValue));
    }
}