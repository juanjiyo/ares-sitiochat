<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\Clones;

use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(ConnectionHosts::class)]
final class ConnectionHostsTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function hosts(): iterable
    {
        yield 'decoded IPv4 wins over hostname' => ['Host.Example', '198.51.100.7', '198.51.100.7'];
        yield 'decoded IPv6 is lowercased' => ['host.example', '2001:DB8::5', '2001:db8::5'];
        yield 'unknown IP falls back to hostname' => ['Host.Example', '*', 'host.example'];
        yield 'undecodable IP falls back to hostname' => ['host.example', 'not-an-ip', 'host.example'];
        yield 'missing IP falls back to hostname' => ['host.example', '', 'host.example'];
    }

    #[Test]
    #[DataProvider('hosts')]
    public function groupsByRealIpWhenKnownElseByHostname(string $hostname, string $ipAddress, string $expected): void
    {
        $hosts = new ConnectionHosts($this->directory([]));

        self::assertSame($expected, $hosts->hostOf($this->user('U1', 'Nick', $hostname, $ipAddress)));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function loopbacks(): iterable
    {
        yield 'localhost' => ['localhost', true];
        yield 'localhost upper case' => ['LocalHost', true];
        yield 'IPv4 loopback' => ['127.0.0.1', true];
        yield 'IPv4 loopback range' => ['127.1.2.3', true];
        yield 'IPv6 loopback' => ['::1', true];
        yield 'public IPv4' => ['198.51.100.7', false];
        yield 'hostname starting with 127' => ['127.example.net', false];
        yield 'remote hostname' => ['host.example', false];
    }

    #[Test]
    #[DataProvider('loopbacks')]
    public function recognisesLoopbackHosts(string $host, bool $expected): void
    {
        self::assertSame($expected, new ConnectionHosts($this->directory([]))->isLoopback($host));
    }

    #[Test]
    public function listsTheSessionsConnectedFromAHost(): void
    {
        $hosts = new ConnectionHosts($this->directory([
            $this->user('U1', 'Alice', 'a.example', '198.51.100.7'),
            $this->user('U2', 'Bob', 'b.example', '198.51.100.8'),
            $this->user('U3', 'Carol', 'c.example', '198.51.100.7'),
            $this->user('U4', 'Dave', 'Shared.Example', '*'),
        ]));

        self::assertSame(['Alice', 'Carol'], $this->nicknames($hosts->sessionsOn('198.51.100.7')));
        self::assertSame(['Dave'], $this->nicknames($hosts->sessionsOn('SHARED.example')));
        self::assertSame([], $hosts->sessionsOn('203.0.113.1'));
    }

    #[Test]
    public function countsSessionsPerHost(): void
    {
        $hosts = new ConnectionHosts($this->directory([
            $this->user('U1', 'Alice', 'a.example', '198.51.100.7'),
            $this->user('U2', 'Bob', 'b.example', '198.51.100.8'),
            $this->user('U3', 'Carol', 'c.example', '198.51.100.7'),
        ]));

        self::assertSame(['198.51.100.7' => 2, '198.51.100.8' => 1], $hosts->countByHost());
    }

    /** @param list<NetworkUser> $users */
    private function directory(array $users): ConnectedUsersDirectory
    {
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn($users);

        return $directory;
    }

    private function user(string $uid, string $nickname, string $hostname, string $ipAddress): NetworkUser
    {
        return new NetworkUser($uid, $nickname, 'ident', $hostname, 'encoded', false, false, $ipAddress);
    }

    /**
     * @param list<NetworkUser> $users
     *
     * @return list<string>
     */
    private function nicknames(array $users): array
    {
        return array_map(static fn (NetworkUser $user): string => $user->nickname, $users);
    }
}
