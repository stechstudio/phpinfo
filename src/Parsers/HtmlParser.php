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
use STS\Phpinfo\Result;

class HtmlParser extends Result
{
    protected DOMXpath $xpath;

    public static function canParse(string $contents): bool
    {
        return str_contains($contents, 'phpinfo()</title>')
            && str_contains($contents, '<h1 class="p">PHP Version ');
    }

    protected function parse(): void
    {
        //dd(new Module('Credits', $this->findGroupedConfigsFor($this->xpath()->query('//body//h1')[2])));

        $this->version = str_replace('PHP Version ', '', $this->xpath()->query('//body//h1')[0]->nodeValue);

        // For modules, we start by looking at all <h2> tags
        $this->modules = collect($this->xpath()->query('//body//h2'))
            // Don't need the license in our collection
            ->reject(fn(DOMElement $heading) => $heading->nodeValue === 'PHP License')
            // Create the Module instance with all configs listed below the heading
            ->map(fn(DOMElement $heading) => new Module($heading->nodeValue, $this->findGroupedConfigsFor($heading)));

        $this->modules->prepend(
            new Module('General', collect([
                new Group(
                    collect($this->xpath()->query('//body//table[2]/tr'))
                    // We know that the general table rows only have two columns
                    ->map(fn(DOMElement $row) => new Config(
                        trim($row->firstChild->nodeValue), trim($row->lastChild->nodeValue)
                    ))
                )
            ]))
        );

        $this->modules->push(
            new Module('Credits', $this->findGroupedConfigsFor($this->xpath()->query('//body//h1')[2]))
        );
    }

    protected function findGroupedConfigsFor(DOMElement $heading): Collection
    {
        $groups = collect();
        $current = $heading;

        // Modules often have multiple tables, we need to keep looking at siblings until it's no longer a table
        while ($current->nextSibling->nodeName === "table") {
            $current = $current->nextSibling;
            $firstRowIndex = 0;
            $note = null;
            $title = null;

            // If there is a single column in our first row, it could be a title (credits) OR a license note (like mbstring)
            if($current->childNodes[0]->childNodes->length === 1) {
                // The only way to really know it check the length, titles will be short
                if(strlen($current->childNodes[0]->childNodes[0]->nodeValue) > 50) {
                    // This is a note, add it to our most recent group
                    $groups->last()->addNote(
                        // A note might have multiple child nodes due to <br> tags, gather them up with line break characters
                        collect($current->childNodes[0]->childNodes[0]->childNodes)
                            ->map->nodeValue
                            ->filter()
                            ->implode("\n")
                    );

                    continue;
                } else {
                    // This is a group title
                    $title = $current->childNodes[0]->childNodes[0]->nodeValue;
                    $firstRowIndex = 1;
                }
            }

            // See if this table has a header row
            $headings = in_array($current->childNodes[$firstRowIndex]?->firstChild->nodeValue, ['Directive', 'Variable', 'Contribution', 'Module'])
                ? collect($current->childNodes[$firstRowIndex]->childNodes)->map->nodeValue
                : collect();

            // We don't want to handle empty tables
            if($current->childNodes[$firstRowIndex] === null) {
                continue;
            }

            // See if this table just has values (some credits tables are like this)
            if($current->childNodes[$firstRowIndex]->childNodes->length === 1) {
                $groups->push(
                    new Group(
                        collect([
                            new Config('Names', $current->childNodes[$firstRowIndex]->childNodes[0]->nodeValue)
                        ]),
                        $headings,
                        $title
                    )
                );

                continue;
            }

            $groups->push(
                new Group(
                    collect($current->childNodes)
                        // We only want <tr> nodes
                        ->filter(fn($node) => $node instanceof DOMElement && $node->nodeName === "tr" && $node->childNodes->length > 1)
                        // Get rid of header rows
                        ->reject(fn(DOMElement $node) => in_array($node->firstChild->nodeValue, ['Directive', 'Variable', 'Contribution', 'Module']))
                        // Parse out the field values
                        ->map(fn(DOMElement $row) => $this->rowToValues($row))
                        // And turn into a Config object
                        ->map(fn(Collection $values) => Config::fromValues($values)),
                    $headings,
                    $title
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
            $document->loadHTML(str_replace(["\r\n","\n"], "", $this->contents));
            $this->xpath = new DOMXpath($document);
        }

        return $this->xpath;
    }
}