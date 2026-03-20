<?php

use STS\Phpinfo\Info;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\PhpInfo;

function htmlInfo(): PhpInfo
{
    static $info = null;

    return $info ??= Info::fromHtml(
        file_get_contents(__DIR__.'/fixtures/html-php83.html')
    );
}

it('can detect html content', function () {
    $html = file_get_contents(__DIR__.'/fixtures/html-php83.html');

    expect(HtmlParser::canParse($html))->toBeTrue()
        ->and(HtmlParser::canParse('not phpinfo'))->toBeFalse()
        ->and(HtmlParser::canParse(''))->toBeFalse();
});

it('rejects invalid content', function () {
    Info::fromHtml('not phpinfo content');
})->throws(InvalidArgumentException::class);

it('returns phpinfo instance', function () {
    expect(htmlInfo())->toBeInstanceOf(PhpInfo::class);
});

it('parses php version', function () {
    expect(htmlInfo()->version())
        ->not->toBeEmpty()
        ->toMatch('/^\d+\.\d+\.\d+/');
});

it('parses modules', function () {
    $modules = htmlInfo()->modules();

    expect($modules->count())->toBeGreaterThan(5)
        ->and($modules->first()->name())->toBe('General');
});

it('has general module with configs', function () {
    $general = htmlInfo()->module('General');

    expect($general)->not->toBeNull()
        ->and($general->configs()->count())->toBeGreaterThan(0);
});

it('has case insensitive module lookup', function () {
    $name = htmlInfo()->modules()->skip(1)->first()->name();

    expect(htmlInfo()->hasModule($name))->toBeTrue()
        ->and(htmlInfo()->hasModule(strtolower($name)))->toBeTrue()
        ->and(htmlInfo()->hasModule(strtoupper($name)))->toBeTrue();
});

it('returns null for missing module', function () {
    expect(htmlInfo()->module('nonexistent_module_xyz'))->toBeNull()
        ->and(htmlInfo()->hasModule('nonexistent_module_xyz'))->toBeFalse();
});

it('parses configs', function () {
    expect(htmlInfo()->configs()->count())->toBeGreaterThan(50);
});

it('can query individual configs', function () {
    expect(htmlInfo()->hasConfig('System'))->toBeTrue()
        ->and(htmlInfo()->config('System'))->not->toBeNull();
});

it('returns null for missing config', function () {
    expect(htmlInfo()->config('nonexistent_config_xyz'))->toBeNull()
        ->and(htmlInfo()->hasConfig('nonexistent_config_xyz'))->toBeFalse();
});

it('has working convenience methods', function () {
    $os = htmlInfo()->os();
    $hostname = htmlInfo()->hostname();

    expect($os)->not->toBeNull()
        ->and($hostname)->not->toBeNull()
        ->and(htmlInfo()->config('System'))->toStartWith($os);
});

it('handles local and master values', function () {
    $config = htmlInfo()->configs()->first(fn ($c) => $c->hasMasterValue());

    if ($config) {
        expect(htmlInfo()->config($config->name(), 'local'))->not->toBeNull();
    }

    expect(true)->toBeTrue();
});

it('parses groups with headings', function () {
    $groupWithHeadings = null;

    foreach (htmlInfo()->modules() as $module) {
        foreach ($module->groups() as $group) {
            if ($group->hasHeadings()) {
                $groupWithHeadings = $group;
                break 2;
            }
        }
    }

    if ($groupWithHeadings) {
        expect($groupWithHeadings->headings()->count())->toBeGreaterThan(0)
            ->and($groupWithHeadings->heading(0))->not->toBeNull();
    }

    expect(true)->toBeTrue();
});

it('handles svg elements in html', function () {
    $infoWithSvg = Info::fromHtml(file_get_contents(__DIR__.'/fixtures/html-php83-with-svg.html'));
    $infoWithout = Info::fromHtml(file_get_contents(__DIR__.'/fixtures/html-php83.html'));

    expect($infoWithSvg->modules()->count())->toBe($infoWithout->modules()->count())
        ->and($infoWithSvg->configs()->count())->toBe($infoWithout->configs()->count());
});

it('parses credits', function () {
    $credits = htmlInfo()->module('PHP Credits');

    expect($credits)->not->toBeNull()
        ->and($credits->name())->not->toBeEmpty()
        ->and($credits->groups()->count())->toBeGreaterThan(0);
});

it('parses license', function () {
    $license = htmlInfo()->module('PHP License');

    expect($license)->not->toBeNull()
        ->and($license->name())->not->toBeEmpty()
        ->and($license->groups()->count())->toBeGreaterThan(0);
});

it('is json serializable', function () {
    $json = json_encode(htmlInfo());
    $data = json_decode($json, true);

    expect($json)->not->toBeFalse()
        ->and($data)->toHaveKeys(['version', 'modules'])
        ->and($data['modules'])->toBeArray()->not->toBeEmpty()
        ->and($data['modules'][0])->toHaveKeys(['key', 'name', 'groups']);
});

it('has unique module keys', function () {
    $keys = htmlInfo()->modules()->map(fn ($m) => $m->key());

    expect($keys->unique()->count())->toBe($keys->count());
});
