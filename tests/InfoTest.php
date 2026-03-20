<?php

use STS\Phpinfo\Info;
use STS\Phpinfo\PhpInfo;

it('creates from html', function () {
    $info = Info::fromHtml(file_get_contents(__DIR__.'/fixtures/html-php83.html'));

    expect($info)->toBeInstanceOf(PhpInfo::class);
});

it('creates from text', function () {
    $info = Info::fromText(file_get_contents(__DIR__.'/fixtures/cli-php83.txt'));

    expect($info)->toBeInstanceOf(PhpInfo::class);
});

it('detects html format', function () {
    $info = Info::detect(file_get_contents(__DIR__.'/fixtures/html-php83.html'));

    expect($info)->toBeInstanceOf(PhpInfo::class);
});

it('detects text format', function () {
    $info = Info::detect(file_get_contents(__DIR__.'/fixtures/cli-php83.txt'));

    expect($info)->toBeInstanceOf(PhpInfo::class);
});

it('throws for invalid content on detect', function () {
    Info::detect('this is not phpinfo output');
})->throws(InvalidArgumentException::class);

it('captures phpinfo', function () {
    $info = Info::capture();

    expect($info)->toBeInstanceOf(PhpInfo::class)
        ->and($info->version())->not->toBeEmpty()
        ->and($info->modules()->count())->toBeGreaterThan(5);
});

it('captures with info constants', function () {
    $full = Info::capture(INFO_ALL);
    $general = Info::capture(INFO_GENERAL);

    expect($full->modules()->count())->toBeGreaterThan($general->modules()->count())
        ->and($general->version())->not->toBeEmpty();
});

it('captures with info modules excludes environment', function () {
    $info = Info::capture(INFO_MODULES);

    expect($info->hasModule('Environment'))->toBeFalse()
        ->and($info->modules()->count())->toBeGreaterThan(0);
});

it('has prettyphpinfo function', function () {
    expect(function_exists('prettyphpinfo'))->toBeTrue();
});

it('prettyphpinfo produces html output', function () {
    ob_start();
    prettyphpinfo(INFO_GENERAL);
    $output = ob_get_clean();

    expect($output)->toContain('phpinfo()')
        ->toContain('</html>');
});

it('delegates static calls to capture', function () {
    expect(Info::version())
        ->not->toBeEmpty()
        ->toMatch('/^\d+\.\d+\.\d+/');
});

it('delegates static modules call', function () {
    expect(Info::modules()->count())->toBeGreaterThan(5);
});
