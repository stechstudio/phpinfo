<?php

namespace STS\Phpinfo\Parsers;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use InvalidArgumentException;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\PhpInfo;
use STS\Phpinfo\Support\Items;

class HtmlParser implements Parser
{
    protected DOMXpath $xpath;

    public function __construct(protected string $contents)
    {
        if (!static::canParse($contents)) {
            throw new InvalidArgumentException('Content provided does not appear to be valid phpinfo() HTML output');
        }
    }

    public static function canParse(string $contents): bool
    {
        return str_contains($contents, 'phpinfo()</title>')
            && str_contains($contents, '<h1 class="p">PHP Version ');
    }

    public function parse(): PhpInfo
    {
        $version = str_replace('PHP Version ', '', $this->xpath()->query('//body//h1')[0]->nodeValue);

        $modules = items($this->xpath()->query('//body//h2'))
            ->reject(fn(DOMElement $heading) => $heading->nodeValue === 'PHP License')
            ->map(fn(DOMElement $heading) => new Module($heading->nodeValue, $this->findGroupedConfigsFor($heading)));

        // General info comes from the second table, prepend it
        $modules->prepend(
            new Module('General', items([
                new Group(
                    items($this->xpath()->query('//body//table[2]/tr'))
                        ->map(fn(DOMElement $row) => new Config(
                            trim($row->firstChild->nodeValue),
                            trim($row->lastChild->nodeValue),
                        ))
                ),
            ]))
        );

        // Credits (third h1)
        $creditsH1 = $this->xpath()->query('//body//h1')[2] ?? null;
        if ($creditsH1) {
            $modules->push(
                new Module($creditsH1->nodeValue, $this->findGroupedConfigsFor($creditsH1))
            );
        }

        // License (last h2)
        $lastH2 = items($this->xpath()->query('//body//h2'))->last();
        if ($lastH2?->nodeValue === 'PHP License') {
            $lastTd = items($this->xpath()->query('//body//table//td'))->last();
            $modules->push(
                new Module(
                    $lastH2->nodeValue,
                    items([
                        Group::noteOnly(
                            items($lastTd->childNodes)->map(fn($n) => $n->nodeValue)->implode("\n\n")
                        ),
                    ])
                )
            );
        }

        return new PhpInfo($version, $modules);
    }

    protected function findGroupedConfigsFor(DOMElement $heading): Items
    {
        $groups = items();
        $current = $heading;

        while ($current = $this->nextTableSibling($current)) {
            $firstRowIndex = 0;
            $title = null;

            // Single-column first row could be a title or a note
            if ($current->childNodes[0]->childNodes->length === 1) {
                if (strlen($current->childNodes[0]->childNodes[0]->nodeValue) > 50) {
                    // This is a note — attach to the most recent group
                    $groups->last()?->addNote(
                        items($current->childNodes[0]->childNodes[0]->childNodes)
                            ->map(fn($n) => $n->nodeValue)
                            ->filter()
                            ->implode("\n")
                    );
                    continue;
                } else {
                    $title = $current->childNodes[0]->childNodes[0]->nodeValue;
                    $firstRowIndex = 1;
                }
            }

            // Check for empty table
            if ($current->childNodes[$firstRowIndex] === null) {
                continue;
            }

            // Detect header row
            $headings = in_array($current->childNodes[$firstRowIndex]?->firstChild->nodeValue, ['Directive', 'Variable', 'Contribution', 'Module'])
                ? items($current->childNodes[$firstRowIndex]->childNodes)->map(fn($n) => $n->nodeValue)
                : items();

            // Single-value rows (some credits tables)
            if ($current->childNodes[$firstRowIndex]->childNodes->length === 1) {
                $groups->push(new Group(
                    items([new Config('Names', $current->childNodes[$firstRowIndex]->childNodes[0]->nodeValue)]),
                    $headings,
                    $title,
                ));
                continue;
            }

            $groups->push(new Group(
                items($current->childNodes)
                    ->filter(fn($node) => $node instanceof DOMElement && $node->nodeName === 'tr' && $node->childNodes->length > 1)
                    ->reject(fn(DOMElement $node) => in_array($node->firstChild->nodeValue, ['Directive', 'Variable', 'Contribution', 'Module']))
                    ->map(fn(DOMElement $row) => $this->rowToValues($row))
                    ->map(fn(array $values) => Config::fromValues($values)),
                $headings,
                $title,
            ));
        }

        return $groups;
    }

    protected function nextTableSibling(DOMNode $current): ?DOMElement
    {
        while ($current->nextSibling !== null) {
            $current = $current->nextSibling;

            if ($current instanceof DOMElement && in_array($current->nodeName, ['h1', 'h2'])) {
                return null;
            }

            if ($current instanceof DOMElement && $current->nodeName === 'table') {
                return $current;
            }
        }

        return null;
    }

    protected function rowToValues(DOMElement $row): array
    {
        return items($row->childNodes)
            ->reject(fn($node) => $node instanceof DOMText)
            ->map(fn(DOMElement $cell) => trim($cell->nodeValue))
            ->values()
            ->all();
    }

    protected function xpath(): DOMXPath
    {
        if (!isset($this->xpath)) {
            $document = new DOMDocument();
            $document->loadHTML(str_replace(["\r\n", "\n"], '', $this->contents), LIBXML_NOERROR);
            $this->xpath = new DOMXpath($document);
        }

        return $this->xpath;
    }
}
