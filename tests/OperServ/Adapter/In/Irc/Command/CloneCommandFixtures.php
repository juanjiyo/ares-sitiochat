<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\NetworkUserLookup;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\UseCase\Clones\BanCloneHostHandler;
use App\OperServ\Application\UseCase\Clones\ConnectionHosts;
use App\OperServ\Application\UseCase\ManageGline\ManageGlineHandlerInterface;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;

use function strtolower;

/**
 * In-memory network shared by the clone command adapter tests: Target and Clone share 198.51.100.7,
 * Local connects from the loopback and Root is a protected Root identity.
 */
trait CloneCommandFixtures
{
    /** @return list<NetworkUser> */
    private function cloneNetwork(): array
    {
        return [
            new NetworkUser('U1', 'Target', 'tid', 'a.example', 'enc', false, false, '198.51.100.7'),
            new NetworkUser('U2', 'Clone', 'cid', 'b.example', 'enc', false, false, '198.51.100.7'),
            new NetworkUser('U3', 'Other', 'oid', 'c.example', 'enc', false, false, '203.0.113.9'),
            new NetworkUser('U4', 'Local', 'lid', 'localhost', 'enc', false, false, '127.0.0.1'),
            new NetworkUser('U5', 'Root', 'rid', 'r.example', 'enc', true, true, '192.0.2.50'),
        ];
    }

    private function cloneUsers(): NetworkUserLookup
    {
        $network = $this->cloneNetwork();
        $users = $this->createStub(NetworkUserLookup::class);
        $users->method('findByNickname')->willReturnCallback(static function (string $nickname) use ($network): ?NetworkUser {
            foreach ($network as $user) {
                if (strtolower($user->nickname) === strtolower($nickname)) {
                    return $user;
                }
            }

            return null;
        });

        return $users;
    }

    private function cloneHosts(): ConnectionHosts
    {
        $directory = $this->createStub(ConnectedUsersDirectory::class);
        $directory->method('listConnected')->willReturn($this->cloneNetwork());

        return new ConnectionHosts($directory);
    }

    private function banHandler(ManageGlineHandlerInterface $glines, ?CommandAuditRecorder $audit = null): BanCloneHostHandler
    {
        $roots = $this->createStub(RootIdentityRegistry::class);
        $roots->method('contains')->willReturnCallback(static fn (string $nickname): bool => 'Root' === $nickname);

        return new BanCloneHostHandler(
            $this->cloneUsers(),
            $this->cloneHosts(),
            new ProtectedNetworkTargets($roots, $this->createStub(OperatorAccountLookup::class), $this->createStub(OperatorRoleAccess::class)),
            $glines,
            $audit ?? new RecordingCommandAuditRecorder(),
        );
    }
}
