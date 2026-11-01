<?php

declare(strict_types=1);

namespace App\Tests\Irc\Adapter\Protocol\UnrealUdb;

use App\Irc\Adapter\Protocol\UnrealUdb\Synchronization\UdbRecordWriterInterface;
use App\Irc\Adapter\Protocol\UnrealUdb\UdbPasswordFloodSetting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UdbPasswordFloodSetting::class)]
final class UdbPasswordFloodSettingTest extends TestCase
{
    #[Test]
    public function writesTheSFloodRecordAndReturnsTheWriterVerdict(): void
    {
        $writer = $this->createMock(UdbRecordWriterInterface::class);
        $writer->expects(self::exactly(2))->method('insert')->with('S', 'flood', '3:120')->willReturnOnConsecutiveCalls(true, false);
        $setting = new UdbPasswordFloodSetting($writer);

        self::assertTrue($setting->set(3, 120));
        self::assertFalse($setting->set(3, 120));
    }
}
