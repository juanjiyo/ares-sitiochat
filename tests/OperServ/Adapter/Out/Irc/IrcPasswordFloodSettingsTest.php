<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\PasswordFloodSettingPort;
use App\OperServ\Adapter\Out\Irc\IrcPasswordFloodSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IrcPasswordFloodSettings::class)]
final class IrcPasswordFloodSettingsTest extends TestCase
{
    #[Test]
    public function forwardsTheThresholdAndItsResult(): void
    {
        $port = $this->createMock(PasswordFloodSettingPort::class);
        $port->expects(self::exactly(2))->method('set')->with(5, 60)->willReturnOnConsecutiveCalls(true, false);
        $settings = new IrcPasswordFloodSettings($port);

        self::assertTrue($settings->set(5, 60));
        self::assertFalse($settings->set(5, 60));
    }
}
