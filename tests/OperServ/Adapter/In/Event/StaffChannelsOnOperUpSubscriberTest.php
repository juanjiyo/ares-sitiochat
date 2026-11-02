<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Event;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\PublishedEvent\UserModesChangedEvent;
use App\NickServ\Application\Port\In\NickAccountQuery;
use App\OperServ\Adapter\In\Event\StaffChannels;
use App\OperServ\Adapter\In\Event\StaffChannelsOnOperUpSubscriber;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(StaffChannelsOnOperUpSubscriber::class)]
#[CoversClass(StaffChannels::class)]
final class StaffChannelsOnOperUpSubscriberTest extends TestCase
{
    #[Test]
    public function subscribesToUserModeChanges(): void
    {
        self::assertArrayHasKey(UserModesChangedEvent::class, StaffChannelsOnOperUpSubscriber::getSubscribedEvents());
    }

    /** @return iterable<string, array{string, string, bool, list<string>}> */
    public static function operUps(): iterable
    {
        yield 'ROOT gets +o with other modes' => ['+Wo', 'ROOT', true, ['#OPers', '#DeBuG', '#ADMINs']];
        yield 'ADMIN gets +o' => ['+o', 'ADMIN', true, ['#OPers', '#DeBuG', '#ADMINs']];
        yield 'OPER gets +o' => ['+o', 'OPER', true, ['#OPers', '#DeBuG']];
        yield 'PREOPER gets +o' => ['+o', 'PREOPER', true, []];
        yield 'nick without rank' => ['+o', '', true, []];
        yield 'ADMIN not identified' => ['+o', 'ADMIN', false, []];
        yield 'ADMIN loses +o' => ['-o', 'ADMIN', true, []];
        yield 'ADMIN other modes' => ['+W-o', 'ADMIN', true, []];
    }

    /** @param list<string> $expected */
    #[Test]
    #[DataProvider('operUps')]
    public function joinsStaffToTheirChannelsWhenTheyBecomeIrcops(string $delta, string $rank, bool $identified, array $expected): void
    {
        $joined = [];
        $actions = $this->createStub(ProtocolServiceActionsInterface::class);
        $actions->method('forceJoin')->willReturnCallback(static function (string $sid, string $uid, string $channel) use (&$joined): void {
            $joined[] = [$sid, $uid, $channel];
        });
        $accounts = $this->createStub(NickAccountQuery::class);
        $accounts->method('findIdByNick')->willReturn(7);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('roleName')->willReturn('' === $rank ? null : $rank);

        new StaffChannelsOnOperUpSubscriber($this->users($identified), $accounts, $roles, $this->connection($actions), '#OPers, #DeBuG,', '#ADMINs')
            ->onUserModesChanged(new UserModesChangedEvent('U42', $delta));

        self::assertSame(array_map(static fn (string $c): array => ['001', 'U42', $c], $expected), $joined);
    }

    #[Test]
    public function ignoresUnknownUsersAndAMissingConnection(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByUid')->willReturn(null);
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::never())->method('forceJoin');
        $accounts = $this->createStub(NickAccountQuery::class);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('roleName')->willReturn('ROOT');

        new StaffChannelsOnOperUpSubscriber($users, $accounts, $roles, $this->connection($actions), '#OPers', '#ADMINs')
            ->onUserModesChanged(new UserModesChangedEvent('U42', '+o'));
        new StaffChannelsOnOperUpSubscriber($this->users(true), $accounts, $roles, $this->createStub(ActiveProtocolModuleHolderInterface::class), '#OPers', '#ADMINs')
            ->onUserModesChanged(new UserModesChangedEvent('U42', '+o'));
    }

    private function users(bool $identified): NetworkUserLookupPort
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByUid')->willReturn(new SenderView('U42', 'Alice', 'a', 'h', 'c', 'ip', $identified, true));

        return $users;
    }

    private function connection(ProtocolServiceActionsInterface $actions): ActiveProtocolModuleHolderInterface
    {
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn($module);
        $connection->method('getServerSid')->willReturn('001');

        return $connection;
    }
}
