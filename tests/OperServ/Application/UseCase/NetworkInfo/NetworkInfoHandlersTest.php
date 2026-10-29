<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\NetworkInfo;

use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\UseCase\NetworkInfo\CountOnlineUsersHandler;
use App\OperServ\Application\UseCase\NetworkInfo\ListOnlineOperatorsHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CountOnlineUsersHandler::class)]
#[CoversClass(ListOnlineOperatorsHandler::class)]
final class NetworkInfoHandlersTest extends TestCase
{
    #[Test]
    public function countsEveryConnectedUser(): void
    {
        $handler = new CountOnlineUsersHandler($this->directory([
            $this->user('U1', 'Alice', false),
            $this->user('U2', 'Bob', true),
            $this->user('U3', 'Carol', false),
        ]));

        self::assertSame(3, $handler->handle());
    }

    #[Test]
    public function countsZeroOnAnEmptyNetwork(): void
    {
        self::assertSame(0, new CountOnlineUsersHandler($this->directory([]))->handle());
    }

    #[Test]
    public function listsOnlyIrcOperatorsSortedByNickname(): void
    {
        $handler = new ListOnlineOperatorsHandler($this->directory([
            $this->user('U1', 'zeta', true),
            $this->user('U2', 'Alice', false),
            $this->user('U3', 'beta', true),
            $this->user('U4', 'Admin10', true),
            $this->user('U5', 'Admin2', true),
        ]));

        self::assertSame(['Admin2', 'Admin10', 'beta', 'zeta'], $handler->handle());
    }

    #[Test]
    public function listsNoOperatorsWhenNoneAreOnline(): void
    {
        $handler = new ListOnlineOperatorsHandler($this->directory([$this->user('U1', 'Alice', false)]));

        self::assertSame([], $handler->handle());
    }

    /** @param list<NetworkUser> $users */
    private function directory(array $users): ConnectedUsersDirectory
    {
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn($users);

        return $directory;
    }

    private function user(string $uid, string $nickname, bool $ircOperator): NetworkUser
    {
        return new NetworkUser($uid, $nickname, 'ident', 'host.test', '*', true, $ircOperator);
    }
}
