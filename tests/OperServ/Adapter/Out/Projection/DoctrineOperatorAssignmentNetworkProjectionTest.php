<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Projection;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\NickServ\Adapter\Out\InMemory\IdentifiedSessionRegistry;
use App\NickServ\Application\Port\In\NickProjectionQuery;
use App\OperServ\Adapter\In\Event\IrcopModeApplier;
use App\OperServ\Adapter\In\Event\IrcopOperclassApplier;
use App\OperServ\Adapter\Out\Projection\DoctrineOperatorAssignmentNetworkProjection;
use App\OperServ\Application\Port\Out\OperatorRoleRecord;
use App\OperServ\Application\PublishedEvent\OperIrcopChangedEvent;
use App\OperServ\Domain\Entity\OperRole;
use App\OperServ\Domain\Repository\OperIrcopRepositoryInterface;
use App\OperServ\Domain\Repository\OperRoleRepositoryInterface;
use App\Shared\Application\Port\EventBusInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DoctrineOperatorAssignmentNetworkProjection::class)]
#[CoversClass(OperatorRoleRecord::class)]
#[CoversClass(OperIrcopChangedEvent::class)]
final class DoctrineOperatorAssignmentNetworkProjectionTest extends TestCase
{
    #[Test]
    public function applyingAndRemovingAnAssignmentDispatchesFinalReconciliationEvents(): void
    {
        $role = OperRole::create('OPER');
        $roles = $this->createStub(OperRoleRepositoryInterface::class);
        $roles->method('findByName')->willReturn($role);
        $events = $this->createMock(EventBusInterface::class);
        $events->expects(self::exactly(2))->method('dispatch')->with(self::callback(
            static fn (object $event): bool => $event instanceof OperIrcopChangedEvent
                && 42 === $event->nickId
                && 'Alice' === $event->nickname,
        ));
        $projection = $this->projection($roles, $events);
        $record = new OperatorRoleRecord(1, 'OPER', '', false);

        $projection->apply(42, 'Alice', $record);
        $projection->remove(42, 'Alice', $record);
    }

    #[Test]
    public function replacingARoleDispatchesExactlyOneFinalReconciliationEvent(): void
    {
        $oldRole = OperRole::create('OPER');
        $newRole = OperRole::create('ADMIN');
        $roles = $this->createMock(OperRoleRepositoryInterface::class);
        $roles->expects(self::exactly(2))->method('findByName')->willReturnMap([
            ['OPER', $oldRole],
            ['ADMIN', $newRole],
        ]);
        $events = $this->createMock(EventBusInterface::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (object $event): bool => $event instanceof OperIrcopChangedEvent
                && 42 === $event->nickId
                && 'Alice' === $event->nickname,
        ));
        $projection = $this->projection($roles, $events);

        $projection->replace(
            42,
            'Alice',
            new OperatorRoleRecord(1, 'OPER', '', false),
            new OperatorRoleRecord(2, 'ADMIN', '', false),
        );
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function staffChannelsByRank(): iterable
    {
        yield 'PREOPER' => ['PREOPER', ['#OPers', '#DeBuG']];
        yield 'OPER' => ['OPER', ['#OPers', '#DeBuG']];
        yield 'ADMIN' => ['ADMIN', ['#OPers', '#DeBuG', '#ADMINs']];
        yield 'ROOT' => ['ROOT', ['#OPers', '#DeBuG', '#ADMINs']];
    }

    /** @param list<string> $expected */
    #[Test]
    #[DataProvider('staffChannelsByRank')]
    public function removingAnAssignmentPartsTheConnectedNickFromTheStaffChannels(string $rank, array $expected): void
    {
        $roles = $this->createStub(OperRoleRepositoryInterface::class);
        $roles->method('findByName')->willReturn(OperRole::create($rank));
        $parted = [];
        $actions = $this->createStub(ProtocolServiceActionsInterface::class);
        $actions->method('forcePart')->willReturnCallback(static function (string $sid, string $uid, string $channel) use (&$parted): void {
            $parted[] = [$sid, $uid, $channel];
        });
        $sessions = new IdentifiedSessionRegistry();
        $sessions->register('U42', 'Alice');

        $this->projection($roles, $this->createStub(EventBusInterface::class), $sessions, $actions)
            ->remove(42, 'Alice', new OperatorRoleRecord(1, $rank, '', false));

        self::assertSame(array_map(static fn (string $c): array => ['001', 'U42', $c], $expected), $parted);
    }

    #[Test]
    public function removingAnAssignmentOfAnOfflineNickPartsNothing(): void
    {
        $roles = $this->createStub(OperRoleRepositoryInterface::class);
        $roles->method('findByName')->willReturn(OperRole::create('ADMIN'));
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::never())->method('forcePart');

        $this->projection($roles, $this->createStub(EventBusInterface::class), new IdentifiedSessionRegistry(), $actions)
            ->remove(42, 'Alice', new OperatorRoleRecord(1, 'ADMIN', '', false));
    }

    private function projection(OperRoleRepositoryInterface $roles, EventBusInterface $events, ?IdentifiedSessionRegistry $sessions = null, ?ProtocolServiceActionsInterface $actions = null): DoctrineOperatorAssignmentNetworkProjection
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        if (null !== $actions) {
            $module = $this->createStub(ProtocolModuleInterface::class);
            $module->method('getServiceActions')->willReturn($actions);
            $connection->method('getProtocolModule')->willReturn($module);
            $connection->method('getServerSid')->willReturn('001');
        }
        $operators = $this->createStub(OperIrcopRepositoryInterface::class);
        $nicks = $this->createStub(NickProjectionQuery::class);
        $sessions ??= new IdentifiedSessionRegistry();

        return new DoctrineOperatorAssignmentNetworkProjection(
            new IrcopModeApplier(
                $sessions,
                $connection,
                $operators,
                $nicks,
                $this->createStub(NetworkUserLookupPort::class),
                new NullLogger(),
            ),
            new IrcopOperclassApplier($sessions, $connection, $operators, $nicks),
            $roles,
            $events,
            $sessions,
            $connection,
            '#OPers,#DeBuG',
            '#ADMINs',
        );
    }
}
