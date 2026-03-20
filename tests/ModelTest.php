<?php

use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;

// ── Config ────────────────────────────────────────────────────────

it('stores name and values', function () {
    $config = new Config('max_file_uploads', '20', '100', hasMasterValue: true);

    expect($config->name())->toBe('max_file_uploads')
        ->and($config->localValue())->toBe('20')
        ->and($config->masterValue())->toBe('100')
        ->and($config->hasMasterValue())->toBeTrue()
        ->and($config->value('local'))->toBe('20')
        ->and($config->value('master'))->toBe('100');
});

it('handles no master value', function () {
    $config = new Config('extension_dir', '/usr/lib/php');

    expect($config->localValue())->toBe('/usr/lib/php')
        ->and($config->hasMasterValue())->toBeFalse()
        ->and($config->masterValue())->toBeNull();
});

it('handles null values', function () {
    $config = new Config('some_setting', null, null, hasMasterValue: true);

    expect($config->localValue())->toBeNull()
        ->and($config->hasMasterValue())->toBeTrue()
        ->and($config->masterValue())->toBeNull();
});

it('creates from two column values', function () {
    $config = Config::fromValues(['BCMath support', 'enabled']);

    expect($config->name())->toBe('BCMath support')
        ->and($config->localValue())->toBe('enabled')
        ->and($config->hasMasterValue())->toBeFalse();
});

it('creates from three column values', function () {
    $config = Config::fromValues(['max_file_uploads', '20', '100']);

    expect($config->name())->toBe('max_file_uploads')
        ->and($config->localValue())->toBe('20')
        ->and($config->masterValue())->toBe('100')
        ->and($config->hasMasterValue())->toBeTrue();
});

it('handles no value in from values', function () {
    $config = Config::fromValues(['some_setting', 'no value', 'no value']);

    expect($config->localValue())->toBeNull()
        ->and($config->masterValue())->toBeNull();
});

it('casts to string', function () {
    expect((string) new Config('test', 'hello'))->toBe('hello')
        ->and((string) new Config('test', null))->toBe('');
});

it('has slugified key', function () {
    expect((new Config('Max File Uploads', '20'))->key())->toBe('config_max_file_uploads');
});

it('uses hash for names key', function () {
    expect((new Config('Names', 'John Doe'))->key())->toStartWith('config_names_');
});

it('is json serializable', function () {
    $config = new Config('test_setting', 'local_val', 'master_val', hasMasterValue: true);
    $data = json_decode(json_encode($config), true);

    expect($data['name'])->toBe('test_setting')
        ->and($data['localValue'])->toBe('local_val')
        ->and($data['masterValue'])->toBe('master_val')
        ->and($data['hasMasterValue'])->toBeTrue()
        ->and($data)->toHaveKey('key');
});

it('shows no value text in json', function () {
    $data = json_decode(json_encode(new Config('empty', null)), true);

    expect($data['localValue'])->toBe('no value');
});

// ── Group ─────────────────────────────────────────────────────────

it('stores configs and metadata', function () {
    $group = new Group(
        items([new Config('a', '1'), new Config('b', '2')]),
        items(['Directive', 'Local Value', 'Master Value']),
        'My Group',
        'A note'
    );

    expect($group->name())->toBe('My Group')
        ->and($group->note())->toBe('A note')
        ->and($group->configs()->count())->toBe(2)
        ->and($group->hasHeadings())->toBeTrue()
        ->and($group->heading(0))->toBe('Directive');
});

it('handles no headings', function () {
    $group = new Group(items());

    expect($group->hasHeadings())->toBeFalse()
        ->and($group->headings()->count())->toBe(0);
});

it('strips value from short headings', function () {
    $group = new Group(items(), items(['Directive', 'Local Value', 'Master Value']));

    expect($group->shortHeading(0))->toBe('Directive')
        ->and($group->shortHeading(1))->toBe('Local')
        ->and($group->shortHeading(2))->toBe('Master');
});

it('creates via simple factory', function () {
    $group = Group::simple('PHP Group', 'Names', 'Rasmus Lerdorf');

    expect($group->name())->toBe('PHP Group')
        ->and($group->configs()->count())->toBe(1)
        ->and($group->configs()->first()->name())->toBe('Names')
        ->and($group->configs()->first()->localValue())->toBe('Rasmus Lerdorf');
});

it('creates via note only factory', function () {
    $group = Group::noteOnly('This is just a note');

    expect($group->note())->toBe('This is just a note')
        ->and($group->configs()->count())->toBe(0);
});

it('adds note fluently', function () {
    $group = new Group(items());
    $result = $group->addNote('Added note');

    expect($result)->toBe($group)
        ->and($group->note())->toBe('Added note');
});

it('uses name in key when available', function () {
    $group = new Group(items(), null, 'Session');

    expect($group->key())->toStartWith('group_')
        ->toContain('session');
});

it('uses hash in key when no name', function () {
    $group = new Group(items([new Config('a', '1')]));

    expect($group->key())->toStartWith('group_');
});

it('is json serializable as group', function () {
    $group = new Group(
        items([new Config('test', 'value')]),
        items(['Directive', 'Value']),
        'Test Group',
        'A note'
    );
    $data = json_decode(json_encode($group), true);

    expect($data)->toHaveKey('key')
        ->and($data['name'])->toBe('Test Group')
        ->and($data['note'])->toBe('A note')
        ->and($data['configs'])->toHaveCount(1)
        ->and($data['headings'])->toHaveCount(2)
        ->and($data['shortHeadings'])->toHaveCount(2);
});

// ── Module ────────────────────────────────────────────────────────

it('stores name and groups', function () {
    $module = new Module('curl', items([new Group(items([new Config('a', '1')]))]));

    expect($module->name())->toBe('curl')
        ->and($module->groups()->count())->toBe(1);
});

it('has prefixed slugified key', function () {
    $module = new Module('Zend OPcache', items());

    expect($module->key())->toBe('module_zend_opcache');
});

it('flattens configs from groups', function () {
    $module = new Module('test', items([
        new Group(items([new Config('a', '1'), new Config('b', '2')])),
        new Group(items([new Config('c', '3')])),
    ]));

    expect($module->configs()->count())->toBe(3);
});

it('can query module configs', function () {
    $module = new Module('test', items([
        new Group(items([new Config('max_size', '100', '200', hasMasterValue: true)])),
    ]));

    expect($module->hasConfig('max_size'))->toBeTrue()
        ->and($module->hasConfig('nonexistent'))->toBeFalse()
        ->and($module->config('max_size'))->toBe('100')
        ->and($module->config('max_size', 'master'))->toBe('200');
});

it('generates combined key for config', function () {
    $config = new Config('max_size', '100');
    $module = new Module('test', items());

    expect($module->combinedKeyFor($config))
        ->toStartWith('module_test_')
        ->toContain('config_max_size');
});

it('is json serializable as module', function () {
    $module = new Module('curl', items([new Group(items([new Config('a', '1')]))]));
    $data = json_decode(json_encode($module), true);

    expect($data['key'])->toBe('module_curl')
        ->and($data['name'])->toBe('curl')
        ->and($data['groups'])->toHaveCount(1);
});
