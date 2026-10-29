<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\Clones;

use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\UseCase\Clones\CloneSession;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use App\OperServ\Application\UseCase\Clones\ListClones;
use App\OperServ\Application\UseCase\Clones\ListClonesHandler;
use App\OperServ\Application\UseCase\Clones\ListClonesResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(ListClones::class)]
#[CoversClass(ListClonesHandler::class)]
#[CoversClass(ListClonesResult::class)]
#[CoversClass(CloneSession::class)]
#[CoversClass(ConnectionHosts::class)]
final class ListClonesHandlerTest extends TestCase
{
    #[Test]
    public function listsTheOtherSessionsFromTheTargetHostInNaturalOrder(): void
    {
        $target = new NetworkUser('U1', 'Target', 'tid', 'a.example', 'enc', false, false, '198.51.100.7');

        $result = $this->handler($target, [
            $target,
            new NetworkUser('U2', 'clone10', 'c10', 'b.example', 'enc', false, false, '198.51.100.7'),
            new NetworkUser('U3', 'Other', 'oid', 'c.example', 'enc', false, false, '203.0.113.9'),
            new NetworkUser('U4', 'Clone2', 'c2', 'd.example', 'enc', false, false, '198.51.100.7'),
        ])->handle(new ListClones('target'));

        self::assertTrue($result->found);
        self::assertSame('Target', $result->nickname);
        self::assertSame('198.51.100.7', $result->host);
        self::assertSame(3, $result->sessionCount);
        self::assertSame(
            [['Clone2', 'c2'], ['clone10', 'c10']],
            array_map(static fn (CloneSession $clone): array => [$clone->nickname, $clone->ident], $result->clones),
        );
    }

    #[Test]
    public function reportsNoClonesWhenTheTargetIsAlone(): void
    {
        $target = new NetworkUser('U1', 'Target', 'tid', 'Solo.Example', 'enc', false, false, '*');

        $result = $this->handler($target, [$target])->handle(new ListClones('Target'));

        self::assertTrue($result->found);
        self::assertSame('solo.example', $result->host);
        self::assertSame(1, $result->sessionCount);
        self::assertSame([], $result->clones);
    }

    #[Test]
    public function reportsATargetThatIsNotOnline(): void
    {
        $result = $this->handler(null, [])->handle(new ListClones('Ghost'));

        self::assertFalse($result->found);
        self::assertSame('Ghost', $result->nickname);
        self::assertSame('', $result->host);
        self::assertSame(0, $result->sessionCount);
        self::assertSame([], $result->clones);
    }

    /** @param list<NetworkUser> $connected */
    private function handler(?NetworkUser $target, array $connected): ListClonesHandler
    {
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturn($target);
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn($connected);

        return new ListClonesHandler($users, new ConnectionHosts($directory));
    }
}
