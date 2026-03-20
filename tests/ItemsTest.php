<?php

use STS\Phpinfo\Info;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Support\Items;

it('creates from array', function () {
    $items = new Items([1, 2, 3]);

    expect($items->count())->toBe(3)
        ->and($items->all())->toBe([1, 2, 3]);
});

it('creates from iterable', function () {
    $items = new Items((function () {
        yield 'a';
        yield 'b';
    })());

    expect($items->count())->toBe(2)
        ->and($items->all())->toBe(['a', 'b']);
});

it('creates empty', function () {
    $items = new Items;

    expect($items->isEmpty())->toBeTrue()
        ->and($items->isNotEmpty())->toBeFalse()
        ->and($items->count())->toBe(0);
});

it('pushes and prepends', function () {
    $items = new Items([2]);
    $items->push(3)->prepend(1);

    expect($items->all())->toBe([1, 2, 3]);
});

it('gets first without callback', function () {
    expect((new Items(['a', 'b']))->first())->toBe('a')
        ->and((new Items)->first())->toBeNull();
});

it('gets first with callback', function () {
    $items = new Items([1, 2, 3, 4]);

    expect($items->first(fn ($v) => $v > 2))->toBe(3)
        ->and($items->first(fn ($v) => $v > 10))->toBeNull();
});

it('gets last without callback', function () {
    expect((new Items(['a', 'b', 'c']))->last())->toBe('c')
        ->and((new Items)->last())->toBeNull();
});

it('gets last with callback', function () {
    $items = new Items([1, 2, 3, 4]);

    expect($items->last(fn ($v) => $v > 2))->toBe(4)
        ->and($items->last(fn ($v) => $v > 10))->toBeNull();
});

it('gets by index', function () {
    $items = new Items(['a', 'b', 'c']);

    expect($items->get(1))->toBe('b')
        ->and($items->get(99))->toBeNull();
});

it('maps without mutating', function () {
    $items = new Items([1, 2, 3]);
    $mapped = $items->map(fn ($v) => $v * 2);

    expect($mapped->all())->toBe([2, 4, 6])
        ->and($items->all())->toBe([1, 2, 3]);
});

it('flat maps', function () {
    $items = new Items([[1, 2], [3, 4]]);

    expect($items->flatMap(fn ($v) => $v)->all())->toBe([1, 2, 3, 4]);
});

it('flat maps with empty results', function () {
    $items = new Items([1, 2, 3]);

    expect($items->flatMap(fn () => [])->isEmpty())->toBeTrue();
});

it('filters with callback', function () {
    $items = new Items([1, 2, 3, 4, 5]);

    expect($items->filter(fn ($v) => $v > 3)->all())->toBe([4, 5]);
});

it('filters without callback removes falsy', function () {
    $items = new Items([0, 1, '', 'a', null, false, true]);

    expect($items->filter()->all())->toBe([1, 'a', true]);
});

it('rejects', function () {
    $items = new Items([1, 2, 3, 4, 5]);

    expect($items->reject(fn ($v) => $v > 3)->all())->toBe([1, 2, 3]);
});

it('iterates with each', function () {
    $items = new Items([1, 2, 3]);
    $collected = [];
    $result = $items->each(function ($v, $k) use (&$collected) {
        $collected[] = "{$k}:{$v}";
    });

    expect($collected)->toBe(['0:1', '1:2', '2:3'])
        ->and($result)->toBe($items);
});

it('implodes', function () {
    expect((new Items(['a', 'b', 'c']))->implode(', '))->toBe('a, b, c');
});

it('skips', function () {
    $items = new Items([1, 2, 3, 4, 5]);

    expect($items->skip(2)->all())->toBe([3, 4, 5])
        ->and($items->skip(10)->all())->toBe([]);
});

it('returns unique values', function () {
    expect((new Items([1, 2, 2, 3, 3, 3]))->unique()->all())->toBe([1, 2, 3]);
});

it('reindexes with values', function () {
    expect((new Items([10 => 'a', 20 => 'b']))->values()->all())->toBe(['a', 'b']);
});

it('has toArray as alias of all', function () {
    $items = new Items([1, 2, 3]);

    expect($items->toArray())->toBe($items->all());
});

it('contains a value with strict comparison', function () {
    $items = new Items([1, 2, 3]);

    expect($items->contains(2))->toBeTrue()
        ->and($items->contains(99))->toBeFalse()
        ->and($items->contains('1'))->toBeFalse();
});

it('contains with callback', function () {
    $items = new Items([1, 2, 3]);

    expect($items->contains(fn ($v) => $v > 2))->toBeTrue()
        ->and($items->contains(fn ($v) => $v > 10))->toBeFalse();
});

it('is iterable', function () {
    $collected = [];
    foreach (new Items([1, 2, 3]) as $item) {
        $collected[] = $item;
    }

    expect($collected)->toBe([1, 2, 3]);
});

it('is countable', function () {
    expect(new Items([1, 2, 3]))->toHaveCount(3);
});

it('is json serializable', function () {
    expect(json_encode(new Items([1, 2, 3])))->toBe('[1,2,3]');
});

it('handles nested json serializable', function () {
    expect(json_encode(new Items([new Items([1, 2])])))->toBe('[[1,2]]');
});

// ── Integration tests with model objects ─────────────────────────

it('filters and maps modules', function () {
    $names = Info::capture()->modules()
        ->filter(fn (Module $m) => strlen($m->name()) < 5)
        ->map(fn (Module $m) => $m->name());

    expect($names)->toBeInstanceOf(Items::class)
        ->and($names->count())->toBeGreaterThan(0);

    foreach ($names as $name) {
        expect(strlen($name))->toBeLessThan(5);
    }
});

it('iterates modules with each', function () {
    $info = Info::capture();
    $names = [];
    $info->modules()->each(function (Module $m) use (&$names) {
        $names[] = $m->name();
    });

    expect(count($names))->toBeGreaterThan(5)
        ->toBe($info->modules()->count());
});

it('gets first and last modules', function () {
    $info = Info::capture();

    expect($info->modules()->first()->name())->toBe('General')
        ->and($info->modules()->last())->not->toBeNull()
        ->and($info->modules()->last()->name())->not->toBe('General');
});

it('checks module containment', function () {
    $info = Info::capture();

    expect($info->modules()->contains(fn (Module $m) => $m->name() === 'General'))->toBeTrue()
        ->and($info->modules()->contains(fn (Module $m) => $m->name() === 'NonexistentModule'))->toBeFalse();
});

it('flat maps configs across modules', function () {
    $allConfigs = Info::capture()->modules()->flatMap(fn (Module $m) => $m->configs());

    expect($allConfigs)->toBeInstanceOf(Items::class)
        ->and($allConfigs->count())->toBeGreaterThan(50)
        ->and($allConfigs->first())->toBeInstanceOf(Config::class);
});

it('chains filter map first on configs', function () {
    $result = Info::capture()->configs()
        ->filter(fn (Config $c) => $c->hasMasterValue())
        ->map(fn (Config $c) => $c->name())
        ->first();

    expect($result)->not->toBeNull()->toBeString();
});

it('skips and counts modules', function () {
    $info = Info::capture();
    $skipped = $info->modules()->skip(2);

    expect($skipped->count())->toBe($info->modules()->count() - 2)
        ->and($skipped->first()->name())->not->toBe('General');
});

it('maps and implodes module keys', function () {
    $keys = Info::capture()->modules()
        ->map(fn (Module $m) => $m->key())
        ->implode(',');

    expect($keys)->toBeString()
        ->toContain('module_general')
        ->toContain(',');
});

it('rejects configs with master values', function () {
    $withoutMaster = Info::capture()->configs()
        ->reject(fn (Config $c) => $c->hasMasterValue());

    expect($withoutMaster->count())->toBeGreaterThan(0);

    foreach ($withoutMaster as $config) {
        expect($config->hasMasterValue())->toBeFalse();
    }
});

it('has unique module keys', function () {
    $info = Info::capture();
    $allKeys = $info->modules()->map(fn (Module $m) => $m->key());

    expect($allKeys->unique()->count())->toBe($allKeys->count());
});

it('returns model objects from toArray', function () {
    $arr = Info::capture()->modules()->toArray();

    expect($arr)->toBeArray()
        ->and($arr[0])->toBeInstanceOf(Module::class);
});
