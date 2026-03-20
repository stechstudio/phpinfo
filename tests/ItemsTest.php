<?php

namespace STS\Phpinfo\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use STS\Phpinfo\Info;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;
use STS\Phpinfo\Support\Items;

class ItemsTest extends TestCase
{
    #[Test]
    public function it_creates_from_array(): void
    {
        $items = new Items([1, 2, 3]);

        $this->assertEquals(3, $items->count());
        $this->assertEquals([1, 2, 3], $items->all());
    }

    #[Test]
    public function it_creates_from_iterable(): void
    {
        $generator = function () { yield 'a'; yield 'b'; };
        $items = new Items($generator());

        $this->assertEquals(2, $items->count());
        $this->assertEquals(['a', 'b'], $items->all());
    }

    #[Test]
    public function it_creates_empty(): void
    {
        $items = new Items();

        $this->assertTrue($items->isEmpty());
        $this->assertFalse($items->isNotEmpty());
        $this->assertEquals(0, $items->count());
    }

    #[Test]
    public function push_and_prepend(): void
    {
        $items = new Items([2]);
        $items->push(3)->prepend(1);

        $this->assertEquals([1, 2, 3], $items->all());
    }

    #[Test]
    public function first_without_callback(): void
    {
        $this->assertEquals('a', (new Items(['a', 'b']))->first());
        $this->assertNull((new Items())->first());
    }

    #[Test]
    public function first_with_callback(): void
    {
        $items = new Items([1, 2, 3, 4]);

        $this->assertEquals(3, $items->first(fn($v) => $v > 2));
        $this->assertNull($items->first(fn($v) => $v > 10));
    }

    #[Test]
    public function last_without_callback(): void
    {
        $this->assertEquals('c', (new Items(['a', 'b', 'c']))->last());
        $this->assertNull((new Items())->last());
    }

    #[Test]
    public function last_with_callback(): void
    {
        $items = new Items([1, 2, 3, 4]);

        $this->assertEquals(4, $items->last(fn($v) => $v > 2));
        $this->assertNull($items->last(fn($v) => $v > 10));
    }

    #[Test]
    public function get_by_index(): void
    {
        $items = new Items(['a', 'b', 'c']);

        $this->assertEquals('b', $items->get(1));
        $this->assertNull($items->get(99));
    }

    #[Test]
    public function map(): void
    {
        $items = new Items([1, 2, 3]);
        $mapped = $items->map(fn($v) => $v * 2);

        $this->assertEquals([2, 4, 6], $mapped->all());
        $this->assertEquals([1, 2, 3], $items->all()); // original unchanged
    }

    #[Test]
    public function flat_map(): void
    {
        $items = new Items([[1, 2], [3, 4]]);
        $flat = $items->flatMap(fn($v) => $v);

        $this->assertEquals([1, 2, 3, 4], $flat->all());
    }

    #[Test]
    public function flat_map_with_empty_results(): void
    {
        $items = new Items([1, 2, 3]);
        $flat = $items->flatMap(fn() => []);

        $this->assertTrue($flat->isEmpty());
    }

    #[Test]
    public function filter_with_callback(): void
    {
        $items = new Items([1, 2, 3, 4, 5]);
        $filtered = $items->filter(fn($v) => $v > 3);

        $this->assertEquals([4, 5], $filtered->all());
    }

    #[Test]
    public function filter_without_callback_removes_falsy(): void
    {
        $items = new Items([0, 1, '', 'a', null, false, true]);
        $filtered = $items->filter();

        $this->assertEquals([1, 'a', true], $filtered->all());
    }

    #[Test]
    public function reject(): void
    {
        $items = new Items([1, 2, 3, 4, 5]);
        $rejected = $items->reject(fn($v) => $v > 3);

        $this->assertEquals([1, 2, 3], $rejected->all());
    }

    #[Test]
    public function each(): void
    {
        $items = new Items([1, 2, 3]);
        $collected = [];
        $result = $items->each(function ($v, $k) use (&$collected) {
            $collected[] = "{$k}:{$v}";
        });

        $this->assertEquals(['0:1', '1:2', '2:3'], $collected);
        $this->assertSame($items, $result); // returns self
    }

    #[Test]
    public function implode(): void
    {
        $items = new Items(['a', 'b', 'c']);

        $this->assertEquals('a, b, c', $items->implode(', '));
    }

    #[Test]
    public function skip(): void
    {
        $items = new Items([1, 2, 3, 4, 5]);

        $this->assertEquals([3, 4, 5], $items->skip(2)->all());
        $this->assertEquals([], $items->skip(10)->all());
    }

    #[Test]
    public function unique(): void
    {
        $items = new Items([1, 2, 2, 3, 3, 3]);

        $this->assertEquals([1, 2, 3], $items->unique()->all());
    }

    #[Test]
    public function values_reindexes(): void
    {
        $items = new Items([10 => 'a', 20 => 'b']);
        $reindexed = $items->values();

        $this->assertEquals(['a', 'b'], $reindexed->all());
    }

    #[Test]
    public function to_array_is_alias_of_all(): void
    {
        $items = new Items([1, 2, 3]);

        $this->assertEquals($items->all(), $items->toArray());
    }

    #[Test]
    public function contains_with_value(): void
    {
        $items = new Items([1, 2, 3]);

        $this->assertTrue($items->contains(2));
        $this->assertFalse($items->contains(99));
    }

    #[Test]
    public function contains_with_callback(): void
    {
        $items = new Items([1, 2, 3]);

        $this->assertTrue($items->contains(fn($v) => $v > 2));
        $this->assertFalse($items->contains(fn($v) => $v > 10));
    }

    #[Test]
    public function contains_uses_strict_comparison(): void
    {
        $items = new Items([1, 2, 3]);

        $this->assertFalse($items->contains('1'));
    }

    #[Test]
    public function it_is_iterable(): void
    {
        $items = new Items([1, 2, 3]);
        $collected = [];

        foreach ($items as $item) {
            $collected[] = $item;
        }

        $this->assertEquals([1, 2, 3], $collected);
    }

    #[Test]
    public function it_is_countable(): void
    {
        $items = new Items([1, 2, 3]);

        $this->assertCount(3, $items);
    }

    #[Test]
    public function it_is_json_serializable(): void
    {
        $items = new Items([1, 2, 3]);

        $this->assertEquals('[1,2,3]', json_encode($items));
    }

    #[Test]
    public function json_serialize_handles_nested_json_serializable(): void
    {
        $inner = new Items([1, 2]);
        $outer = new Items([$inner]);

        $this->assertEquals('[[1,2]]', json_encode($outer));
    }

    // ── Integration tests with model objects ─────────────────────────

    #[Test]
    public function filter_and_map_on_modules(): void
    {
        $info = Info::capture();
        $names = $info->modules()
            ->filter(fn(Module $m) => strlen($m->name()) < 5)
            ->map(fn(Module $m) => $m->name());

        $this->assertInstanceOf(Items::class, $names);
        $this->assertGreaterThan(0, $names->count());
        foreach ($names as $name) {
            $this->assertLessThan(5, strlen($name));
        }
    }

    #[Test]
    public function each_on_modules(): void
    {
        $info = Info::capture();
        $names = [];
        $info->modules()->each(function (Module $m) use (&$names) {
            $names[] = $m->name();
        });

        $this->assertGreaterThan(5, count($names));
        $this->assertEquals($info->modules()->count(), count($names));
    }

    #[Test]
    public function first_and_last_on_modules(): void
    {
        $info = Info::capture();

        $this->assertEquals('General', $info->modules()->first()->name());
        $this->assertNotNull($info->modules()->last());
        $this->assertNotEquals(
            $info->modules()->first()->name(),
            $info->modules()->last()->name()
        );
    }

    #[Test]
    public function contains_on_modules(): void
    {
        $info = Info::capture();

        $this->assertTrue(
            $info->modules()->contains(fn(Module $m) => $m->name() === 'General')
        );
        $this->assertFalse(
            $info->modules()->contains(fn(Module $m) => $m->name() === 'NonexistentModule')
        );
    }

    #[Test]
    public function flat_map_configs_across_modules(): void
    {
        $info = Info::capture();

        // This is exactly how PhpInfo::configs() works internally
        $allConfigs = $info->modules()->flatMap(fn(Module $m) => $m->configs());

        $this->assertInstanceOf(Items::class, $allConfigs);
        $this->assertGreaterThan(50, $allConfigs->count());
        $this->assertInstanceOf(Config::class, $allConfigs->first());
    }

    #[Test]
    public function chained_filter_map_first_on_configs(): void
    {
        $info = Info::capture();

        $result = $info->configs()
            ->filter(fn(Config $c) => $c->hasMasterValue())
            ->map(fn(Config $c) => $c->name())
            ->first();

        // There should be at least one config with a master value
        $this->assertNotNull($result);
        $this->assertIsString($result);
    }

    #[Test]
    public function skip_and_count_on_modules(): void
    {
        $info = Info::capture();
        $total = $info->modules()->count();
        $skipped = $info->modules()->skip(2);

        $this->assertEquals($total - 2, $skipped->count());
        $this->assertNotEquals('General', $skipped->first()->name());
    }

    #[Test]
    public function map_implode_for_module_keys(): void
    {
        $info = Info::capture();
        $keys = $info->modules()
            ->map(fn(Module $m) => $m->key())
            ->implode(',');

        $this->assertIsString($keys);
        $this->assertStringContainsString('module_general', $keys);
        $this->assertStringContainsString(',', $keys);
    }

    #[Test]
    public function reject_on_configs(): void
    {
        $info = Info::capture();

        $withoutMaster = $info->configs()
            ->reject(fn(Config $c) => $c->hasMasterValue());

        $this->assertGreaterThan(0, $withoutMaster->count());
        foreach ($withoutMaster as $config) {
            $this->assertFalse($config->hasMasterValue());
        }
    }

    #[Test]
    public function unique_on_mapped_values(): void
    {
        $info = Info::capture();
        $allKeys = $info->modules()->map(fn(Module $m) => $m->key());
        $uniqueKeys = $allKeys->unique();

        $this->assertEquals($allKeys->count(), $uniqueKeys->count());
    }

    #[Test]
    public function to_array_returns_model_objects(): void
    {
        $info = Info::capture();
        $arr = $info->modules()->toArray();

        $this->assertIsArray($arr);
        $this->assertInstanceOf(Module::class, $arr[0]);
    }
}
