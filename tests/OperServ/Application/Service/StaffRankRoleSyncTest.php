<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\Service;

use App\Irc\Application\PublishedEvent\NetworkSynchronizationCompletedEvent;
use App\OperServ\Adapter\In\Event\OperServStaffRankSyncSubscriber;
use App\OperServ\Application\Port\In\OperatorPermissionCatalog;
use App\OperServ\Application\Port\Out\OperatorRoleRecord;
use App\OperServ\Application\Port\Out\OperatorRoleStore;
use App\OperServ\Application\Service\StaffRankRoleSync;
use App\OperServ\Domain\ValueObject\StaffRank;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function in_array;

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
        $store = new SyncRecordingRoleStore(['ROOT']);

        $this->sync($store)->sync();

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
        $store = new SyncRecordingRoleStore(['PREOPER', 'OPER', 'ADMIN', 'ROOT']);
        $subscriber = new OperServStaffRankSyncSubscriber($this->sync($store));

        self::assertSame([NetworkSynchronizationCompletedEvent::class => ['onSyncComplete', -30]], OperServStaffRankSyncSubscriber::getSubscribedEvents());
        $subscriber->onSyncComplete(new NetworkSynchronizationCompletedEvent('001'));

        self::assertSame([], $store->created);
        self::assertCount(4, $store->operclasses);
    }

    private function sync(SyncRecordingRoleStore $store): StaffRankRoleSync
    {
        $catalog = $this->createStub(OperatorPermissionCatalog::class);
        $catalog->method('getAllPermissions')->willReturn(self::CATALOG);

        return new StaffRankRoleSync($store, $catalog, 'sitiochat.es');
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

    /** @param list<string> $existing */
    public function __construct(private array $existing = []) {}

    public function findByName(string $name): ?OperatorRoleRecord
    {
        return in_array($name, $this->existing, true) ? $this->record($name) : null;
    }

    public function all(): array
    {
        return [];
    }

    public function create(string $name, string $description): OperatorRoleRecord
    {
        $this->created[] = [$name, $description];
        $this->existing[] = $name;

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
        return new OperatorRoleRecord(1, $name, '', false);
    }
}
