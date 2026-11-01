<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Projection;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\NickServ\Adapter\In\Irc\NickServNotifierInterface;
use App\NickServ\Adapter\Out\InMemory\IdentifiedSessionRegistry;
use App\NickServ\Application\Port\In\NickProjectionQuery;
use App\NickServ\Application\Service\VhostDisplayResolver;
use App\OperServ\Adapter\In\Event\ForcedVhostApplier;
use App\OperServ\Adapter\In\Event\IrcopOperclassApplier;
use App\OperServ\Adapter\Out\Projection\DoctrineOperatorRoleNetworkProjection;
use App\OperServ\Application\PublishedEvent\OperRoleForcedVhostChangedEvent;
use App\OperServ\Domain\Repository\OperIrcopRepositoryInterface;
use App\Shared\Application\Port\EventBusInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DoctrineOperatorRoleNetworkProjection::class)]
#[CoversClass(OperRoleForcedVhostChangedEvent::class)]
final class DoctrineOperatorRoleNetworkProjectionTest extends TestCase
{
    #[Test]
    public function refreshesTheVhostAndOperclassOfTheRoleHolders(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $operators = $this->createStub(OperIrcopRepositoryInterface::class);
        $nicks = $this->createStub(NickProjectionQuery::class);
        $sessions = new IdentifiedSessionRegistry();
        $events = $this->createMock(EventBusInterface::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (object $event): bool => $event instanceof OperRoleForcedVhostChangedEvent
                && 7 === $event->roleId
                && 'staff.example.net' === $event->pattern,
        ));
        $projection = new DoctrineOperatorRoleNetworkProjection(
            new ForcedVhostApplier(
                $operators,
                $nicks,
                $sessions,
                $this->createStub(NickServNotifierInterface::class),
                $this->createStub(NetworkUserLookupPort::class),
                $connection,
                new VhostDisplayResolver(),
                new NullLogger(),
            ),
            new IrcopOperclassApplier($sessions, $connection, $operators, $nicks),
            $events,
        );

        $projection->refreshVhost(7, 'staff.example.net');
        $projection->refreshOperclass(7, 'netadmin');
    }
}
