<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\OperServ\Adapter\Out\Irc\IrcConnectedUsersDirectory;
use App\OperServ\Adapter\Out\Irc\NetworkUserMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IrcConnectedUsersDirectory::class)]
#[CoversClass(NetworkUserMapper::class)]
final class IrcConnectedUsersDirectoryTest extends TestCase
{
    #[Test]
    public function mapsEveryConnectedUidToANetworkUser(): void
    {
        $views = [
            'U1' => new SenderView('U1', 'Alice', 'alice', 'alice.host', 'cloak1', 'fwAAAQ==', true, false),
            'U2' => new SenderView('U2', 'Oper', 'oper', 'oper.host', 'cloak2', '*', true, true),
        ];
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn(['U1', 'U2']);
        $users->method('findByUid')->willReturnCallback(static fn (string $uid): ?SenderView => $views[$uid] ?? null);

        $connected = new IrcConnectedUsersDirectory($users)->listConnected();

        self::assertCount(2, $connected);
        self::assertSame('U1', $connected[0]->uid);
        self::assertSame('Alice', $connected[0]->nickname);
        self::assertSame('alice', $connected[0]->ident);
        self::assertSame('alice.host', $connected[0]->hostname);
        self::assertSame('fwAAAQ==', $connected[0]->ipBase64);
        self::assertTrue($connected[0]->identified);
        self::assertFalse($connected[0]->ircOperator);
        self::assertSame('Oper', $connected[1]->nickname);
        self::assertTrue($connected[1]->ircOperator);
    }

    #[Test]
    public function skipsUsersThatDisconnectedWhileWalkingTheNetwork(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn(['GONE', 'U1']);
        $users->method('findByUid')->willReturnCallback(
            static fn (string $uid): ?SenderView => 'U1' === $uid ? new SenderView('U1', 'Alice', 'alice', 'host', '', '') : null,
        );

        $connected = new IrcConnectedUsersDirectory($users)->listConnected();

        self::assertCount(1, $connected);
        self::assertSame('Alice', $connected[0]->nickname);
    }

    #[Test]
    public function returnsAnEmptyListOnAnEmptyNetwork(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn([]);

        self::assertSame([], new IrcConnectedUsersDirectory($users)->listConnected());
    }
}
