<?php

namespace STS\Phpinfo\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use STS\Phpinfo\Info;
use STS\Phpinfo\Parsers\HtmlParser;
use STS\Phpinfo\PhpInfo;

class HtmlParserTest extends TestCase
{
    private static ?PhpInfo $info = null;

    private static function info(): PhpInfo
    {
        if (self::$info === null) {
            self::$info = Info::fromHtml(
                file_get_contents(__DIR__ . '/fixtures/html-php83.html')
            );
        }

        return self::$info;
    }

    #[Test]
    public function it_can_detect_html_content(): void
    {
        $html = file_get_contents(__DIR__ . '/fixtures/html-php83.html');

        $this->assertTrue(HtmlParser::canParse($html));
        $this->assertFalse(HtmlParser::canParse('not phpinfo'));
        $this->assertFalse(HtmlParser::canParse(''));
    }

    #[Test]
    public function it_rejects_invalid_content(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Info::fromHtml('not phpinfo content');
    }

    #[Test]
    public function it_returns_phpinfo_instance(): void
    {
        $this->assertInstanceOf(PhpInfo::class, self::info());
    }

    #[Test]
    public function it_parses_php_version(): void
    {
        $this->assertNotEmpty(self::info()->version());
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', self::info()->version());
    }

    #[Test]
    public function it_parses_modules(): void
    {
        $modules = self::info()->modules();

        $this->assertGreaterThan(5, $modules->count());
        $this->assertEquals('General', $modules->first()->name());
    }

    #[Test]
    public function it_has_general_module_with_configs(): void
    {
        $general = self::info()->module('General');

        $this->assertNotNull($general);
        $this->assertGreaterThan(0, $general->configs()->count());
    }

    #[Test]
    public function has_module_is_case_insensitive(): void
    {
        $firstModuleName = self::info()->modules()->skip(1)->first()->name();

        $this->assertTrue(self::info()->hasModule($firstModuleName));
        $this->assertTrue(self::info()->hasModule(strtolower($firstModuleName)));
        $this->assertTrue(self::info()->hasModule(strtoupper($firstModuleName)));
    }

    #[Test]
    public function it_returns_null_for_missing_module(): void
    {
        $this->assertNull(self::info()->module('nonexistent_module_xyz'));
        $this->assertFalse(self::info()->hasModule('nonexistent_module_xyz'));
    }

    #[Test]
    public function it_parses_configs(): void
    {
        $this->assertGreaterThan(50, self::info()->configs()->count());
    }

    #[Test]
    public function it_can_query_individual_configs(): void
    {
        $this->assertTrue(self::info()->hasConfig('System'));
        $this->assertNotNull(self::info()->config('System'));
    }

    #[Test]
    public function it_returns_null_for_missing_config(): void
    {
        $this->assertNull(self::info()->config('nonexistent_config_xyz'));
        $this->assertFalse(self::info()->hasConfig('nonexistent_config_xyz'));
    }

    #[Test]
    public function convenience_methods_work(): void
    {
        $os = self::info()->os();
        $hostname = self::info()->hostname();

        $this->assertNotNull($os);
        $this->assertNotNull($hostname);

        $system = self::info()->config('System');
        $this->assertStringStartsWith($os, $system);
    }

    #[Test]
    public function it_handles_local_and_master_values(): void
    {
        $configWithMaster = self::info()->configs()
            ->first(fn($config) => $config->hasMasterValue());

        if ($configWithMaster) {
            $name = $configWithMaster->name();
            $this->assertNotNull(self::info()->config($name, 'local'));
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function it_parses_groups_with_headings(): void
    {
        $groupWithHeadings = null;

        foreach (self::info()->modules() as $module) {
            foreach ($module->groups() as $group) {
                if ($group->hasHeadings()) {
                    $groupWithHeadings = $group;
                    break 2;
                }
            }
        }

        if ($groupWithHeadings) {
            $this->assertGreaterThan(0, $groupWithHeadings->headings()->count());
            $this->assertNotNull($groupWithHeadings->heading(0));
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_svg_elements_in_html(): void
    {
        $infoWithSvg = Info::fromHtml(file_get_contents(__DIR__ . '/fixtures/html-php83-with-svg.html'));
        $infoWithout = Info::fromHtml(file_get_contents(__DIR__ . '/fixtures/html-php83.html'));

        $this->assertEquals(
            $infoWithout->modules()->count(),
            $infoWithSvg->modules()->count()
        );
        $this->assertEquals(
            $infoWithout->configs()->count(),
            $infoWithSvg->configs()->count()
        );
    }

    #[Test]
    public function it_parses_credits(): void
    {
        $credits = self::info()->module('PHP Credits');

        $this->assertNotNull($credits);
        $this->assertNotEmpty($credits->name());
        $this->assertGreaterThan(0, $credits->groups()->count());
    }

    #[Test]
    public function it_parses_license(): void
    {
        $license = self::info()->module('PHP License');

        $this->assertNotNull($license);
        $this->assertNotEmpty($license->name());
        $this->assertGreaterThan(0, $license->groups()->count());
    }

    #[Test]
    public function it_is_json_serializable(): void
    {
        $json = json_encode(self::info());

        $this->assertNotFalse($json);

        $data = json_decode($json, true);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('modules', $data);
        $this->assertIsArray($data['modules']);
        $this->assertGreaterThan(0, count($data['modules']));

        $firstModule = $data['modules'][0];
        $this->assertArrayHasKey('key', $firstModule);
        $this->assertArrayHasKey('name', $firstModule);
        $this->assertArrayHasKey('groups', $firstModule);
    }

    #[Test]
    public function modules_have_unique_keys(): void
    {
        $keys = self::info()->modules()->map(fn($m) => $m->key());
        $uniqueKeys = $keys->unique();

        $this->assertEquals($keys->count(), $uniqueKeys->count());
    }
}
