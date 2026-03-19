<?php

namespace STS\Phpinfo\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use STS\Phpinfo\Info;
use STS\Phpinfo\PhpInfo;

class InfoTest extends TestCase
{
    #[Test]
    public function from_html_returns_phpinfo(): void
    {
        $html = file_get_contents(__DIR__ . '/fixtures/html-php83.html');
        $info = Info::fromHtml($html);

        $this->assertInstanceOf(PhpInfo::class, $info);
    }

    #[Test]
    public function from_text_returns_phpinfo(): void
    {
        $text = file_get_contents(__DIR__ . '/fixtures/cli-php83.txt');
        $info = Info::fromText($text);

        $this->assertInstanceOf(PhpInfo::class, $info);
    }

    #[Test]
    public function detect_identifies_html(): void
    {
        $html = file_get_contents(__DIR__ . '/fixtures/html-php83.html');
        $info = Info::detect($html);

        $this->assertInstanceOf(PhpInfo::class, $info);
    }

    #[Test]
    public function detect_identifies_text(): void
    {
        $text = file_get_contents(__DIR__ . '/fixtures/cli-php83.txt');
        $info = Info::detect($text);

        $this->assertInstanceOf(PhpInfo::class, $info);
    }

    #[Test]
    public function detect_throws_for_invalid_content(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Info::detect('this is not phpinfo output');
    }

    #[Test]
    public function capture_returns_phpinfo(): void
    {
        $info = Info::capture();

        $this->assertInstanceOf(PhpInfo::class, $info);
        $this->assertNotEmpty($info->version());
        $this->assertGreaterThan(5, $info->modules()->count());
    }

    #[Test]
    public function call_static_delegates_to_capture(): void
    {
        $version = Info::version();

        $this->assertNotEmpty($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version);
    }

    #[Test]
    public function call_static_modules(): void
    {
        $modules = Info::modules();

        $this->assertGreaterThan(5, $modules->count());
    }
}
