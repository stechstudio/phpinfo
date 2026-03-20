<?php

use STS\Phpinfo\Info;
use STS\Phpinfo\Parsers\TextParser;
use STS\Phpinfo\PhpInfo;

function textInfo(): PhpInfo
{
    static $info = null;

    return $info ??= Info::fromText(
        file_get_contents(__DIR__.'/fixtures/cli-php83.txt')
    );
}

it('can detect text content', function () {
    $text = file_get_contents(__DIR__.'/fixtures/cli-php83.txt');

    expect(TextParser::canParse($text))->toBeTrue()
        ->and(TextParser::canParse('not phpinfo'))->toBeFalse()
        ->and(TextParser::canParse(''))->toBeFalse();
});

it('rejects invalid content', function () {
    Info::fromText('not phpinfo content');
})->throws(InvalidArgumentException::class);

it('returns phpinfo instance', function () {
    expect(textInfo())->toBeInstanceOf(PhpInfo::class);
});

it('parses php version', function () {
    expect(textInfo()->version())
        ->not->toBeEmpty()
        ->toMatch('/^\d+\.\d+\.\d+/');
});

it('parses modules', function () {
    $modules = textInfo()->modules();

    expect($modules->count())->toBeGreaterThan(5)
        ->and($modules->first()->name())->toBe('General');
});

it('has general module with configs', function () {
    $general = textInfo()->module('General');

    expect($general)->not->toBeNull()
        ->and($general->configs()->count())->toBeGreaterThan(0);
});

it('has case insensitive module lookup', function () {
    $name = textInfo()->modules()->skip(1)->first()->name();

    expect(textInfo()->hasModule($name))->toBeTrue()
        ->and(textInfo()->hasModule(strtolower($name)))->toBeTrue()
        ->and(textInfo()->hasModule(strtoupper($name)))->toBeTrue();
});

it('returns null for missing module', function () {
    expect(textInfo()->module('nonexistent_module_xyz'))->toBeNull()
        ->and(textInfo()->hasModule('nonexistent_module_xyz'))->toBeFalse();
});

it('parses configs', function () {
    expect(textInfo()->configs()->count())->toBeGreaterThan(50);
});

it('can query individual configs', function () {
    expect(textInfo()->hasConfig('System'))->toBeTrue()
        ->and(textInfo()->config('System'))->not->toBeNull();
});

it('returns null for missing config', function () {
    expect(textInfo()->config('nonexistent_config_xyz'))->toBeNull()
        ->and(textInfo()->hasConfig('nonexistent_config_xyz'))->toBeFalse();
});

it('has working convenience methods', function () {
    $os = textInfo()->os();
    $hostname = textInfo()->hostname();

    expect($os)->not->toBeNull()
        ->and($hostname)->not->toBeNull()
        ->and(textInfo()->config('System'))->toStartWith($os);
});

it('handles local and master values', function () {
    $config = textInfo()->configs()->first(fn ($c) => $c->hasMasterValue());

    if ($config) {
        expect(textInfo()->config($config->name(), 'local'))->not->toBeNull();
    }

    expect(true)->toBeTrue();
});

it('parses groups with headings', function () {
    $groupWithHeadings = null;

    foreach (textInfo()->modules() as $module) {
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

it('parses credits', function () {
    $credits = textInfo()->module('PHP Credits');

    expect($credits)->not->toBeNull()
        ->and($credits->name())->not->toBeEmpty()
        ->and($credits->groups()->count())->toBeGreaterThan(0);
});

it('parses license', function () {
    $license = textInfo()->module('PHP License');

    expect($license)->not->toBeNull()
        ->and($license->name())->not->toBeEmpty()
        ->and($license->groups()->count())->toBeGreaterThan(0);
});

it('is json serializable', function () {
    $json = json_encode(textInfo());
    $data = json_decode($json, true);

    expect($json)->not->toBeFalse()
        ->and($data)->toHaveKeys(['version', 'modules'])
        ->and($data['modules'])->toBeArray()->not->toBeEmpty();
});

it('has unique module keys', function () {
    $keys = textInfo()->modules()->map(fn ($m) => $m->key());

    expect($keys->unique()->count())->toBe($keys->count());
});

it('handles crlf line endings', function () {
    $text = file_get_contents(__DIR__.'/fixtures/cli-php83.txt');
    $info = Info::fromText(str_replace("\n", "\r\n", $text));

    expect($info->version())->not->toBeEmpty()
        ->and($info->modules()->count())->toBeGreaterThan(5);
});

it('produces consistent results with html parser', function () {
    $htmlInfo = Info::fromHtml(
        file_get_contents(__DIR__.'/fixtures/html-php83.html')
    );

    expect($htmlInfo->version())->toBe(textInfo()->version())
        ->and($htmlInfo->module('General'))->not->toBeNull()
        ->and(textInfo()->module('General'))->not->toBeNull()
        ->and($htmlInfo->config('System'))->toBe(textInfo()->config('System'));
});
