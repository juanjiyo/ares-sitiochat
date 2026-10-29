<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelView;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\ServiceUidProviderInterface;
use App\Irc\Application\Port\In\ServiceUidRegistry;
use App\OperServ\Adapter\Out\Irc\ActiveConnectionChannelOverrideActions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveConnectionChannelOverrideActions::class)]
final class ActiveConnectionChannelOverrideActionsTest extends TestCase
{
    #[Test]
    public function kicksWithOperServAsSource(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('kickFromChannel')->with('001', '#help', 'UID1', 'reason', '001OS');

        self::assertTrue($this->adapter($actions)->kick('#help', 'UID1', 'reason'));
    }

    #[Test]
    public function grantsAndRemovesOperatorStatusWithChannelTimestamp(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $calls = [];
        $actions->expects(self::exactly(2))->method('setChannelMemberMode')
            ->willReturnCallback(static function (string $sid, string $channel, string $uid, string $letter, bool $add, string $source, ?int $ts) use (&$calls): void {
                $calls[] = [$sid, $channel, $uid, $letter, $add, $source, $ts];
            });
        $adapter = $this->adapter($actions, timestamp: 1700000000);

        self::assertTrue($adapter->setOperatorStatus('#help', 'UID1', true));
        self::assertTrue($adapter->setOperatorStatus('#help', 'UID1', false));
        self::assertSame([
            ['001', '#help', 'UID1', 'o', true, '001OS', 1700000000],
            ['001', '#help', 'UID1', 'o', false, '001OS', 1700000000],
        ], $calls);
    }

    #[Test]
    public function setsModesWithParametersAndChannelTimestamp(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('setChannelModes')->with('001', '#help', '+kl', ['secret', '10'], '001OS', 1700000000);

        self::assertTrue($this->adapter($actions, timestamp: 1700000000)->setModes('#help', '+kl', ['secret', '10']));
    }

    #[Test]
    public function invitesWithoutTimestampWhenChannelIsUnknownOrUntimed(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::exactly(2))->method('inviteUserToChannel')->with('001', '#help', 'UID1', '001OS', null);

        self::assertTrue($this->adapter($actions)->invite('#help', 'UID1'));
        self::assertTrue($this->adapter($actions, timestamp: 0)->invite('#help', 'UID1'));
    }

    #[Test]
    public function usesServerAsSourceWhenOperServUidIsUnknown(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::once())->method('kickFromChannel')->with('001', '#help', 'UID1', 'reason', '');

        self::assertTrue($this->adapter($actions, withOperServ: false)->kick('#help', 'UID1', 'reason'));
    }

    #[Test]
    public function refusesEveryActionWithoutProtocolOrServerSid(): void
    {
        $withoutModule = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $withoutModule->method('getProtocolModule')->willReturn(null);
        $withoutModule->method('getServerSid')->willReturn('001');
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($this->createStub(ProtocolServiceActionsInterface::class));
        $withoutSid = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $withoutSid->method('getProtocolModule')->willReturn($module);
        $withoutSid->method('getServerSid')->willReturn(null);

        foreach ([$withoutModule, $withoutSid] as $connection) {
            $adapter = new ActiveConnectionChannelOverrideActions($connection, new ServiceUidRegistry(), $this->createStub(ChannelLookupPort::class));

            self::assertFalse($adapter->kick('#help', 'UID1', 'reason'));
            self::assertFalse($adapter->setOperatorStatus('#help', 'UID1', true));
            self::assertFalse($adapter->setModes('#help', '-imnpst', []));
            self::assertFalse($adapter->invite('#help', 'UID1'));
        }
    }

    private function adapter(ProtocolServiceActionsInterface $actions, ?int $timestamp = null, bool $withOperServ = true): ActiveConnectionChannelOverrideActions
    {
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn($module);
        $connection->method('getServerSid')->willReturn('001');
        $provider = $this->createStub(ServiceUidProviderInterface::class);
        $provider->method('getUid')->willReturn('001OS');
        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(null === $timestamp ? null : new ChannelView('#help', '+nt', null, 1, [], $timestamp));

        return new ActiveConnectionChannelOverrideActions(
            $connection,
            new ServiceUidRegistry($withOperServ ? ['operserv' => $provider] : []),
            $channels,
        );
    }
}
