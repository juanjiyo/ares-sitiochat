<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\Service;

use App\Irc\Application\PublishedEvent\NetworkSynchronizationCompletedEvent;
use App\OperServ\Adapter\In\Event\OperServStaffRankSyncSubscriber;
use App\OperServ\Application\Port\In\OperatorPermissionCatalog;
use App\OperServ\Application\Port\Out\OperatorRoleNetworkProjection;
use App\OperServ\Application\Port\Out\OperatorRoleRecord;
use App\OperServ\Application\Port\Out\OperatorRoleStore;
use App\OperServ\Application\Service\StaffRankRoleSync;
use App\OperServ\Domain\ValueObject\StaffRank;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StaffRankRoleSync::class)]
#[CoversClass(OperServStaffRankSyncSubscriber::class)]
final class StaffRankRoleSyncTest extends TestCase
{
    private const array CATALOG = [
        'nickserv.list', 'operserv.kick', 'operserv.kill', 'operserv.gline', 'operserv.eline',
        'operserv.staff', 'operserv.staff.admin', 'operserv.set', 'operserv.raw',
    ];

    #[Test]
    public function createsMissingRolesAndKeepsExistingOnesAlignedWithTheLadder(): void
    {
        $store = new SyncRecordingRoleStore(['ROOT' => ['netadmin', null]]);
        $projection = new SyncRecordingProjection();

        $this->sync($store, $projection)->sync();

        self::assertSame(['PREOPER', 'OPER', 'ADMIN'], array_column($store->created, 0));
        self::assertSame(['Pre-Operador', 'Operador', 'Administrador'], array_column($store->created, 1));
        self::assertSame([
            'PREOPER' => ['nickserv.list', 'operserv.kick'],
            'OPER' => ['nickserv.list', 'operserv.kick', 'operserv.kill', 'operserv.gline'],
            'ADMIN' => ['nickserv.list', 'operserv.kick', 'operserv.kill', 'operserv.gline', 'operserv.eline', 'operserv.staff'],
            'ROOT' => self::CATALOG,
        ], $store->permissions);
        self::assertSame(['PREOPER' => 'locop', 'OPER' => 'globop', 'ADMIN' => 'admin', 'ROOT' => 'netadmin'], $store->operclasses);
        self::assertSame([
            'PREOPER' => 'preoper.sitiochat.es',
            'OPER' => 'oper.sitiochat.es',
            'ADMIN' => 'admin.sitiochat.es',
            'ROOT' => 'root.sitiochat.es',
        ], $store->vhosts);
        // Only the existing ROOT role changed on the network (its vhost); new roles have no holders yet.
        self::assertSame([[4, 'root.sitiochat.es']], $projection->vhosts);
        self::assertSame([], $projection->operclasses);
    }

    #[Test]
    public function refreshesTheHoldersOfAnExistingRoleWhoseOperclassChanged(): void
    {
        $projection = new SyncRecordingProjection();

        $this->sync(new SyncRecordingRoleStore(['OPER' => ['locop', 'oper.sitiochat.es']]), $projection)->sync();

        self::assertSame([[2, 'globop']], $projection->operclasses);
        self::assertSame([], $projection->vhosts);
    }

    #[Test]
    public function permissionsAreCumulativeAndOnlyFromTheCatalog(): void
    {
        $sync = $this->sync(new SyncRecordingRoleStore());

        self::assertNotContains('operserv.staff', $sync->permissionsFor(StaffRank::OPER));
        self::assertContains('operserv.staff', $sync->permissionsFor(StaffRank::ADMIN));
        self::assertNotContains('operserv.set', $sync->permissionsFor(StaffRank::ADMIN));
        self::assertSame(self::CATALOG, $sync->permissionsFor(StaffRank::ROOT));
    }

    #[Test]
    public function runsOnceTheLinkIsSynced(): void
    {
        $store = new SyncRecordingRoleStore([
            'PREOPER' => ['locop', 'preoper.sitiochat.es'],
            'OPER' => ['globop', 'oper.sitiochat.es'],
            'ADMIN' => ['admin', 'admin.sitiochat.es'],
            'ROOT' => ['netadmin', 'root.sitiochat.es'],
        ]);
        $projection = new SyncRecordingProjection();
        $subscriber = new OperServStaffRankSyncSubscriber($this->sync($store, $projection));

        self::assertSame([NetworkSynchronizationCompletedEvent::class => ['onSyncComplete', -30]], OperServStaffRankSyncSubscriber::getSubscribedEvents());
        $subscriber->onSyncComplete(new NetworkSynchronizationCompletedEvent('001'));

        self::assertSame([], $store->created);
        self::assertCount(4, $store->operclasses);
        self::assertSame([], $projection->vhosts);
        self::assertSame([], $projection->operclasses);
    }

    private function sync(SyncRecordingRoleStore $store, ?SyncRecordingProjection $projection = null): StaffRankRoleSync
    {
        $catalog = $this->createStub(OperatorPermissionCatalog::class);
        $catalog->method('getAllPermissions')->willReturn(self::CATALOG);

        return new StaffRankRoleSync($store, $catalog, $projection ?? new SyncRecordingProjection(), 'sitiochat.es');
    }
}

final class SyncRecordingRoleStore implements OperatorRoleStore
{
    /** @var list<array{string, string}> */
    public array $created = [];

    /** @var array<string, list<string>> */
    public array $permissions = [];

    /** @var array<string, ?string> */
    public array $operclasses = [];

    /** @var array<string, ?string> */
    public array $vhosts = [];

    private const array IDS = ['PREOPER' => 1, 'OPER' => 2, 'ADMIN' => 3, 'ROOT' => 4];

    /** @param array<string, array{?string, ?string}> $existing role => [operclass, vhost pattern] */
    public function __construct(private array $existing = []) {}

    public function findByName(string $name): ?OperatorRoleRecord
    {
        return isset($this->existing[$name]) ? $this->record($name) : null;
    }

    public function all(): array
    {
        return [];
    }

    public function create(string $name, string $description): OperatorRoleRecord
    {
        $this->created[] = [$name, $description];
        $this->existing[$name] = [null, null];

        return $this->record($name);
    }

    public function remove(string $name): void {}

    public function setPermissions(string $roleName, array $permissions): void
    {
        $this->permissions[$roleName] = $permissions;
    }

    public function setUserModes(string $roleName, array $modes): void {}

    public function setForcedVhostPattern(string $roleName, ?string $pattern): void
    {
        $this->vhosts[$roleName] = $pattern;
    }

    public function setOperclass(string $roleName, ?string $operclass): void
    {
        $this->operclasses[$roleName] = $operclass;
    }

    private function record(string $name): OperatorRoleRecord
    {
        [$operclass, $vhost] = $this->existing[$name];

        return new OperatorRoleRecord(self::IDS[$name], $name, '', false, forcedVhostPattern: $vhost, operclass: $operclass);
    }
}

final class SyncRecordingProjection implements OperatorRoleNetworkProjection
{
    /** @var list<array{int, ?string}> */
    public array $vhosts = [];

    /** @var list<array{int, ?string}> */
    public array $operclasses = [];

    public function refreshVhost(int $roleId, ?string $pattern): void
    {
        $this->vhosts[] = [$roleId, $pattern];
    }

    public function refreshOperclass(int $roleId, ?string $operclass): void
    {
        $this->operclasses[] = [$roleId, $operclass];
    }
}
