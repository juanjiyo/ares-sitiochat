<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\Security;

use App\OperServ\Application\Port\Out\NetworkUser;
use App\OperServ\Application\Port\Out\OperatorAccountLookup;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Application\Port\Out\RootIdentityRegistry;
use App\OperServ\Application\Security\ProtectedNetworkTargets;
use App\OperServ\Application\Security\TargetProtection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProtectedNetworkTargets::class)]
final class ProtectedNetworkTargetsTest extends TestCase
{
    /** @return iterable<string, array{bool, bool, bool, ?int, bool, ?TargetProtection}> */
    public static function targets(): iterable
    {
        yield 'root identity' => [true, false, false, null, false, TargetProtection::Root];
        yield 'ordinary user' => [false, false, false, 7, true, null];
        yield 'oper not identified' => [false, true, false, 7, true, null];
        yield 'identified non oper' => [false, false, true, 7, true, null];
        yield 'identified oper without account' => [false, true, true, null, true, null];
        yield 'identified oper without role' => [false, true, true, 7, false, null];
        yield 'identified oper with role' => [false, true, true, 7, true, TargetProtection::IrcOperator];
    }

    #[Test]
    #[DataProvider('targets')]
    public function classifiesProtectedTargets(bool $root, bool $oper, bool $identified, ?int $accountId, bool $hasRole, ?TargetProtection $expected): void
    {
        $roots = $this->createStub(RootIdentityRegistry::class);
        $roots->method('contains')->willReturn($root);
        $accounts = $this->createMock(OperatorAccountLookup::class);
        $accounts->expects($oper && $identified && !$root ? self::once() : self::never())
            ->method('findIdByNickname')->with('target')->willReturn($accountId);
        $roles = $this->createStub(OperatorRoleAccess::class);
        $roles->method('hasAssignedRole')->willReturn($hasRole);

        $protection = new ProtectedNetworkTargets($roots, $accounts, $roles)
            ->protectionFor(new NetworkUser('UID1', 'Target', 'ident', 'host', 'ip', $identified, $oper));

        self::assertSame($expected, $protection);
    }
}
