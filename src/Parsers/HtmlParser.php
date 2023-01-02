<?php

namespace STS\Phpinfo\Parsers;

use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;
use Illuminate\Support\Collection;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Info;

class HtmlParser extends Info
{
    protected DOMXpath $xpath;

    public static function canParse(string $contents): bool
    {
        return str_contains($contents, 'phpinfo()</title>')
            && str_contains($contents, '<h1 class="p">PHP Version ');
    }

    protected function parse(): void
    {
        $this->version = str_replace('PHP Version ', '', $this->xpath()->query('//body//h1')[0]->nodeValue);

        // For modules, we start by looking at all <h2> tags
        $this->modules = collect($this->xpath()->query('//body//h2'))
            // Don't need the license in our collection
            ->reject(fn(DOMElement $heading) => $heading->nodeValue === 'PHP License')
            // Create the Module instance with all configs listed below the heading
            ->map(fn(DOMElement $heading) => new Module($heading->nodeValue, $this->findGroupedConfigsFor($heading)))
            // Key by lowercase name for easy lookups
            ->keyBy(fn(Module $module) => strtolower($module->name()));

        $this->modules->prepend(
            new Module('General', collect([
                new Group(
                    collect($this->xpath()->query('//body//table[2]/tr'))
                    // We know that the general table rows only have two columns
                    ->map(fn(DOMElement $row) => new Config(
                        trim($row->firstChild->nodeValue), trim($row->lastChild->nodeValue)
                    ))
                )
            ])), 'general'
        );
    }

    protected function findGroupedConfigsFor(DOMElement $heading): Collection
    {
        $groups = collect();
        $current = $heading;

        // Modules often have multiple tables, we need to keep looking at siblings until it's no longer a table
        while ($current->nextSibling->nodeName === "table") {
            $current = $current->nextSibling;

            // See if this table has a header row
            $headings = in_array($current->childNodes[0]->firstChild->nodeValue, ['Directive', 'Variable'])
                ? collect($current->childNodes[0]->childNodes)->map->nodeValue
                : collect();

            $groups->push(
                new Group(
                    collect($current->childNodes)
                        // We only want <tr> nodes
                        ->filter(fn($node) => $node instanceof DOMElement && $node->nodeName === "tr" && $node->childNodes->length > 1)
                        // Get rid of header rows
                        ->reject(fn(DOMElement $node) => in_array($node->firstChild->nodeValue, ['Directive', 'Variable']))
                        // Parse out the field values
                        ->map(fn(DOMElement $row) => $this->rowToValues($row))
                        // And turn into a Config object
                        ->map(fn(Collection $values) => Config::fromValues($values)),
                    $headings
                )
            );
        }

        return $groups;
    }

    protected function rowToValues(DOMElement $row): Collection
    {
        return collect($row->childNodes)
            ->reject(fn($node) => $node instanceof DOMText)
            ->map(fn(DOMElement $cell) => trim($cell->nodeValue));
    }

    protected function xpath(): DOMXPath
    {
        if(!isset($this->xpath)) {
            $document = new DOMDocument;
            $document->loadHTML(str_replace("\n", "", $this->contents));
            $this->xpath = new DOMXpath($document);
        }

        return $this->xpath;
    }
}