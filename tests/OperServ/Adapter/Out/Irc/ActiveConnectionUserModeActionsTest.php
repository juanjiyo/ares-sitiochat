<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\OperServ\Adapter\Out\Irc\ActiveConnectionUserModeActions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveConnectionUserModeActions::class)]
final class ActiveConnectionUserModeActionsTest extends TestCase
{
    #[Test]
    public function sendsTheModesAndMirrorsThemLocally(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('forceUserMode')->with('001', 'U1', '+R-w');
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn($module);
        $connection->method('getServerSid')->willReturn('001');
        $users = $this->createMock(NetworkUserLookupPort::class);
        $users->expects(self::once())->method('applyModeChange')->with('U1', '+R-w');

        self::assertTrue(new ActiveConnectionUserModeActions($connection, $users)->apply('U1', '+R-w'));
    }

    #[Test]
    public function refusesWhenConnectionHasNoProtocolOrServerSid(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn(null);
        $connection->method('getServerSid')->willReturn(null);
        $users = $this->createMock(NetworkUserLookupPort::class);
        $users->expects(self::never())->method('applyModeChange');

        self::assertFalse(new ActiveConnectionUserModeActions($connection, $users)->apply('U1', '+R'));
    }
}
