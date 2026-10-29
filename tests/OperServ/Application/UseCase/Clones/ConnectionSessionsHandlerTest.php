<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\Clones;

use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use App\OperServ\Application\UseCase\Clones\ConnectionSessionsHandler;
use App\OperServ\Application\UseCase\Clones\HostSessionCount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(ConnectionSessionsHandler::class)]
#[CoversClass(HostSessionCount::class)]
#[CoversClass(ConnectionHosts::class)]
final class ConnectionSessionsHandlerTest extends TestCase
{
    #[Test]
    public function listsHostsWithAtLeastTheMinimumSortedByCountThenHost(): void
    {
        $hosts = $this->handler()->hostsWithAtLeast(2);

        self::assertSame(
            [['198.51.100.7', 3], ['192.0.2.1', 2], ['shared.example', 2]],
            array_map(static fn (HostSessionCount $host): array => [$host->host, $host->sessions], $hosts),
        );
    }

    #[Test]
    public function listsNothingWhenNoHostReachesTheMinimum(): void
    {
        self::assertSame([], $this->handler()->hostsWithAtLeast(4));
    }

    #[Test]
    public function countsTheSessionsOfOneHostCaseInsensitively(): void
    {
        $handler = $this->handler();

        self::assertSame(3, $handler->sessionsOn('198.51.100.7'));
        self::assertSame(2, $handler->sessionsOn('SHARED.Example'));
        self::assertSame(0, $handler->sessionsOn('203.0.113.50'));
    }

    private function handler(): ConnectionSessionsHandler
    {
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn([
            $this->user('U1', 'shared.example', '*'),
            $this->user('U2', 'a.example', '198.51.100.7'),
            $this->user('U3', 'b.example', '192.0.2.1'),
            $this->user('U4', 'c.example', '198.51.100.7'),
            $this->user('U5', 'Shared.Example', ''),
            $this->user('U6', 'd.example', '192.0.2.1'),
            $this->user('U7', 'e.example', '198.51.100.7'),
            $this->user('U8', 'f.example', '203.0.113.1'),
        ]);

        return new ConnectionSessionsHandler(new ConnectionHosts($directory));
    }

    private function user(string $uid, string $hostname, string $ipAddress): NetworkUser
    {
        return new NetworkUser($uid, 'Nick' . $uid, 'ident', $hostname, 'enc', false, false, $ipAddress);
    }
}
