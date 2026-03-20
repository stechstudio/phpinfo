<?php

namespace STS\Phpinfo\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use STS\Phpinfo\Models\Config;
use STS\Phpinfo\Models\Group;
use STS\Phpinfo\Models\Module;

class ModelTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────────────

    #[Test]
    public function config_stores_name_and_values(): void
    {
        $config = new Config('max_file_uploads', '20', '100', hasMasterValue: true);

        $this->assertEquals('max_file_uploads', $config->name());
        $this->assertEquals('20', $config->localValue());
        $this->assertEquals('100', $config->masterValue());
        $this->assertTrue($config->hasMasterValue());
        $this->assertEquals('20', $config->value('local'));
        $this->assertEquals('100', $config->value('master'));
    }

    #[Test]
    public function config_handles_no_master_value(): void
    {
        $config = new Config('extension_dir', '/usr/lib/php');

        $this->assertEquals('/usr/lib/php', $config->localValue());
        $this->assertFalse($config->hasMasterValue());
        $this->assertNull($config->masterValue());
    }

    #[Test]
    public function config_handles_null_values(): void
    {
        $config = new Config('some_setting', null, null, hasMasterValue: true);

        $this->assertNull($config->localValue());
        $this->assertTrue($config->hasMasterValue());
        $this->assertNull($config->masterValue());
    }

    #[Test]
    public function config_from_values_two_columns(): void
    {
        $config = Config::fromValues(['BCMath support', 'enabled']);

        $this->assertEquals('BCMath support', $config->name());
        $this->assertEquals('enabled', $config->localValue());
        $this->assertFalse($config->hasMasterValue());
    }

    #[Test]
    public function config_from_values_three_columns(): void
    {
        $config = Config::fromValues(['max_file_uploads', '20', '100']);

        $this->assertEquals('max_file_uploads', $config->name());
        $this->assertEquals('20', $config->localValue());
        $this->assertEquals('100', $config->masterValue());
        $this->assertTrue($config->hasMasterValue());
    }

    #[Test]
    public function config_from_values_handles_no_value(): void
    {
        $config = Config::fromValues(['some_setting', 'no value', 'no value']);

        $this->assertNull($config->localValue());
        $this->assertNull($config->masterValue());
    }

    #[Test]
    public function config_casts_to_string(): void
    {
        $config = new Config('test', 'hello');
        $this->assertEquals('hello', (string) $config);

        $nullConfig = new Config('test', null);
        $this->assertEquals('', (string) $nullConfig);
    }

    #[Test]
    public function config_key_is_slugified(): void
    {
        $config = new Config('Max File Uploads', '20');

        $this->assertEquals('config_max_file_uploads', $config->key());
    }

    #[Test]
    public function config_names_key_uses_hash(): void
    {
        $config = new Config('Names', 'John Doe');

        $this->assertStringStartsWith('config_names_', $config->key());
    }

    #[Test]
    public function config_is_json_serializable(): void
    {
        $config = new Config('test_setting', 'local_val', 'master_val', hasMasterValue: true);
        $data = json_decode(json_encode($config), true);

        $this->assertEquals('test_setting', $data['name']);
        $this->assertEquals('local_val', $data['localValue']);
        $this->assertEquals('master_val', $data['masterValue']);
        $this->assertTrue($data['hasMasterValue']);
        $this->assertArrayHasKey('key', $data);
    }

    #[Test]
    public function config_json_shows_no_value_text(): void
    {
        $config = new Config('empty', null);
        $data = json_decode(json_encode($config), true);

        $this->assertEquals('no value', $data['localValue']);
    }

    // ── Group ─────────────────────────────────────────────────────────

    #[Test]
    public function group_stores_configs_and_metadata(): void
    {
        $configs = items([
            new Config('a', '1'),
            new Config('b', '2'),
        ]);
        $headings = items(['Directive', 'Local Value', 'Master Value']);

        $group = new Group($configs, $headings, 'My Group', 'A note');

        $this->assertEquals('My Group', $group->name());
        $this->assertEquals('A note', $group->note());
        $this->assertEquals(2, $group->configs()->count());
        $this->assertTrue($group->hasHeadings());
        $this->assertEquals('Directive', $group->heading(0));
    }

    #[Test]
    public function group_with_no_headings(): void
    {
        $group = new Group(items());

        $this->assertFalse($group->hasHeadings());
        $this->assertEquals(0, $group->headings()->count());
    }

    #[Test]
    public function group_short_heading_strips_value(): void
    {
        $group = new Group(
            items(),
            items(['Directive', 'Local Value', 'Master Value'])
        );

        $this->assertEquals('Local', $group->shortHeading(1));
        $this->assertEquals('Master', $group->shortHeading(2));
        $this->assertEquals('Directive', $group->shortHeading(0));
    }

    #[Test]
    public function group_simple_factory(): void
    {
        $group = Group::simple('PHP Group', 'Names', 'Rasmus Lerdorf');

        $this->assertEquals('PHP Group', $group->name());
        $this->assertEquals(1, $group->configs()->count());
        $this->assertEquals('Names', $group->configs()->first()->name());
        $this->assertEquals('Rasmus Lerdorf', $group->configs()->first()->localValue());
    }

    #[Test]
    public function group_note_only_factory(): void
    {
        $group = Group::noteOnly('This is just a note');

        $this->assertEquals('This is just a note', $group->note());
        $this->assertEquals(0, $group->configs()->count());
    }

    #[Test]
    public function group_add_note(): void
    {
        $group = new Group(items());
        $result = $group->addNote('Added note');

        $this->assertSame($group, $result);
        $this->assertEquals('Added note', $group->note());
    }

    #[Test]
    public function group_key_uses_name_when_available(): void
    {
        $group = new Group(items(), null, 'Session');

        $this->assertStringStartsWith('group_', $group->key());
        $this->assertStringContainsString('session', $group->key());
    }

    #[Test]
    public function group_key_uses_hash_when_no_name(): void
    {
        $group = new Group(items([new Config('a', '1')]));

        $this->assertStringStartsWith('group_', $group->key());
    }

    #[Test]
    public function group_is_json_serializable(): void
    {
        $group = new Group(
            items([new Config('test', 'value')]),
            items(['Directive', 'Value']),
            'Test Group',
            'A note'
        );

        $data = json_decode(json_encode($group), true);

        $this->assertArrayHasKey('key', $data);
        $this->assertEquals('Test Group', $data['name']);
        $this->assertEquals('A note', $data['note']);
        $this->assertCount(1, $data['configs']);
        $this->assertCount(2, $data['headings']);
        $this->assertCount(2, $data['shortHeadings']);
    }

    // ── Module ────────────────────────────────────────────────────────

    #[Test]
    public function module_stores_name_and_groups(): void
    {
        $group = new Group(items([new Config('a', '1')]));
        $module = new Module('curl', items([$group]));

        $this->assertEquals('curl', $module->name());
        $this->assertEquals(1, $module->groups()->count());
    }

    #[Test]
    public function module_key_is_prefixed_and_slugified(): void
    {
        $module = new Module('Zend OPcache', items());

        $this->assertStringStartsWith('module_', $module->key());
        $this->assertEquals('module_zend_opcache', $module->key());
    }

    #[Test]
    public function module_flattens_configs_from_groups(): void
    {
        $group1 = new Group(items([
            new Config('a', '1'),
            new Config('b', '2'),
        ]));
        $group2 = new Group(items([
            new Config('c', '3'),
        ]));
        $module = new Module('test', items([$group1, $group2]));

        $this->assertEquals(3, $module->configs()->count());
    }

    #[Test]
    public function module_can_query_configs(): void
    {
        $group = new Group(items([
            new Config('max_size', '100', '200', hasMasterValue: true),
        ]));
        $module = new Module('test', items([$group]));

        $this->assertTrue($module->hasConfig('max_size'));
        $this->assertFalse($module->hasConfig('nonexistent'));
        $this->assertEquals('100', $module->config('max_size'));
        $this->assertEquals('200', $module->config('max_size', 'master'));
    }

    #[Test]
    public function module_combined_key_for_config(): void
    {
        $config = new Config('max_size', '100');
        $module = new Module('test', items());

        $combined = $module->combinedKeyFor($config);

        $this->assertStringStartsWith('module_test_', $combined);
        $this->assertStringContainsString('config_max_size', $combined);
    }

    #[Test]
    public function module_is_json_serializable(): void
    {
        $group = new Group(items([new Config('a', '1')]));
        $module = new Module('curl', items([$group]));

        $data = json_decode(json_encode($module), true);

        $this->assertEquals('module_curl', $data['key']);
        $this->assertEquals('curl', $data['name']);
        $this->assertCount(1, $data['groups']);
    }
}
