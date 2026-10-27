<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\Out\OperServ;

use App\GlobalServ\Adapter\Out\OperServ\OperServOperatorRankLookup;
use App\GlobalServ\Application\Port\Out\GlobalServNickAccountPort;
use App\GlobalServ\Domain\ValueObject\GlobalServRank;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\OperServ\Application\Port\In\OperatorNetworkProjection;
use App\OperServ\Application\Port\In\OperatorNetworkProjectionQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperServOperatorRankLookup::class)]
final class OperServOperatorRankLookupTest extends TestCase
{
    private const string UID = '001ABC';

    #[Test]
    public function returnsNullForAnUnknownUid(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByUid')->willReturn(null);

        $lookup = new OperServOperatorRankLookup(
            $users,
            $this->createStub(GlobalServNickAccountPort::class),
            $this->createStub(OperatorNetworkProjectionQuery::class),
        );

        self::assertNull($lookup->hasAtLeastRank(self::UID, GlobalServRank::PREOPER));
    }

    #[Test]
    public function returnsFalseForAConnectedNonOper(): void
    {
        $lookup = new OperServOperatorRankLookup(
            $this->createUsers(isOper: false),
            $this->createStub(GlobalServNickAccountPort::class),
            $this->createStub(OperatorNetworkProjectionQuery::class),
        );

        self::assertFalse($lookup->hasAtLeastRank(self::UID, GlobalServRank::PREOPER));
    }

    #[Test]
    public function returnsNullWhenTheAccountOrProjectionIsMissing(): void
    {
        $accounts = $this->createStub(GlobalServNickAccountPort::class);
        $accounts->method('findAccountIdByNick')->willReturn(null);

        $lookup = new OperServOperatorRankLookup(
            $this->createUsers(),
            $accounts,
            $this->createStub(OperatorNetworkProjectionQuery::class),
        );

        self::assertNull($lookup->hasAtLeastRank(self::UID, GlobalServRank::OPER));
    }

    #[Test]
    public function returnsNullWhenTheProjectionIsMissing(): void
    {
        $projections = $this->createStub(OperatorNetworkProjectionQuery::class);
        $projections->method('findForNick')->willReturn(null);

        $lookup = new OperServOperatorRankLookup($this->createUsers(), $this->createAccounts(), $projections);

        self::assertNull($lookup->hasAtLeastRank(self::UID, GlobalServRank::OPER));
    }

    /**
     * @return iterable<string, array{string, GlobalServRank, bool}>
     */
    public static function operclassLadder(): iterable
    {
        yield 'locop reaches pre-oper only' => ['locop', GlobalServRank::PREOPER, true];
        yield 'locop does not reach oper' => ['locop', GlobalServRank::OPER, false];
        yield 'globop reaches oper' => ['globop', GlobalServRank::OPER, true];
        yield 'globop does not reach co-admin' => ['globop', GlobalServRank::COADMIN, false];
        yield 'admin reaches co-admin' => ['admin', GlobalServRank::COADMIN, true];
        yield 'admin reaches admin' => ['admin', GlobalServRank::ADMIN, true];
        yield 'services-admin does not reach admin' => ['services-admin', GlobalServRank::ADMIN, false];
        yield 'netadmin with override reaches co-admin' => ['netadmin-with-override', GlobalServRank::COADMIN, true];
        yield 'netadmin does not match the exact admin tier' => ['netadmin', GlobalServRank::ADMIN, false];
    }

    #[Test]
    #[DataProvider('operclassLadder')]
    public function mapsTheOperclassOntoTheStaffLadder(string $operclass, GlobalServRank $rank, bool $expected): void
    {
        $projections = $this->createStub(OperatorNetworkProjectionQuery::class);
        $projections->method('findForNick')->willReturn(new OperatorNetworkProjection(7, null, $operclass));

        $lookup = new OperServOperatorRankLookup($this->createUsers(), $this->createAccounts(), $projections);

        self::assertSame($expected, $lookup->hasAtLeastRank(self::UID, $rank));
    }

    private function createUsers(bool $isOper = true): NetworkUserLookupPort
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByUid')->willReturn(new SenderView(
            uid: self::UID,
            nick: 'Alice',
            ident: 'a',
            hostname: 'h',
            cloakedHost: 'c',
            ipBase64: 'ip',
            isIdentified: true,
            isOper: $isOper,
        ));

        return $users;
    }

    private function createAccounts(): GlobalServNickAccountPort
    {
        $accounts = $this->createStub(GlobalServNickAccountPort::class);
        $accounts->method('findAccountIdByNick')->willReturn(7);

        return $accounts;
    }
}
