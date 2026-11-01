<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Domain\ValueObject;

use App\Bootstrap\Security\ChanServPermissionProvider;
use App\Bootstrap\Security\CRegServPermissionProvider;
use App\Bootstrap\Security\MemoServPermissionProvider;
use App\Bootstrap\Security\NickServPermissionProvider;
use App\GlobalServ\Application\Security\GlobalServIrcopPermission;
use App\OperServ\Application\Security\OperServIrcopPermission;
use App\OperServ\Application\Security\PermissionRegistry;
use App\OperServ\Domain\ValueObject\StaffRank;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_intersect;
use function array_merge;

#[CoversClass(StaffRank::class)]
final class StaffRankTest extends TestCase
{
    #[Test]
    public function mapsEveryRankToItsOperclassAndVhostLabel(): void
    {
        self::assertSame(['locop', 'globop', 'admin', 'netadmin'], array_map(static fn (StaffRank $r): string => $r->operclass(), StaffRank::cases()));
        self::assertSame(['preoper', 'oper', 'admin', 'root'], array_map(static fn (StaffRank $r): string => $r->vhostLabel(), StaffRank::cases()));
    }

    #[Test]
    public function includesEveryLowerRankInLadderOrder(): void
    {
        self::assertSame([StaffRank::PREOPER], StaffRank::PREOPER->withLowerRanks());
        self::assertSame([StaffRank::PREOPER, StaffRank::OPER, StaffRank::ADMIN], StaffRank::ADMIN->withLowerRanks());
        self::assertSame(StaffRank::cases(), StaffRank::ROOT->withLowerRanks());
    }

    #[Test]
    public function resolvesNamesCaseInsensitively(): void
    {
        self::assertSame(StaffRank::OPER, StaffRank::fromName('oper'));
        self::assertSame(StaffRank::ROOT, StaffRank::fromName('Root'));
        self::assertNull(StaffRank::fromName('COADMIN'));
    }

    #[Test]
    public function everyLadderPermissionExistsInTheRealCatalogAndAppearsOnce(): void
    {
        $catalog = new PermissionRegistry([
            new OperServIrcopPermission(),
            new NickServPermissionProvider(),
            new ChanServPermissionProvider(),
            new CRegServPermissionProvider(),
            new MemoServPermissionProvider(),
            new GlobalServIrcopPermission(),
        ])->getAllPermissions();

        $all = [];
        foreach (StaffRank::cases() as $rank) {
            self::assertSame([], array_diff($rank->ownPermissions(), $catalog), $rank->value . ' lists unknown permissions');
            self::assertSame([], array_intersect($all, $rank->ownPermissions()), $rank->value . ' repeats a lower-rank permission');
            $all = array_merge($all, $rank->ownPermissions());
        }
        self::assertSame([], StaffRank::ROOT->ownPermissions());
        self::assertContains('operserv.staff', StaffRank::ADMIN->ownPermissions());
        self::assertNotContains('operserv.staff.admin', $all);
        self::assertNotContains('operserv.set', $all);
        self::assertNotContains('operserv.raw', $all);
    }
}
