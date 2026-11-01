<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Projection;

use App\OperServ\Adapter\In\Event\ForcedVhostApplier;
use App\OperServ\Adapter\In\Event\IrcopOperclassApplier;
use App\OperServ\Application\Port\Out\OperatorRoleNetworkProjection;
use App\OperServ\Application\PublishedEvent\OperRoleForcedVhostChangedEvent;
use App\Shared\Application\Port\EventBusInterface;

final readonly class DoctrineOperatorRoleNetworkProjection implements OperatorRoleNetworkProjection
{
    public function __construct(
        private ForcedVhostApplier $vhost,
        private IrcopOperclassApplier $operclass,
        private EventBusInterface $events,
    ) {}

    public function refreshVhost(int $roleId, ?string $pattern): void
    {
        $this->vhost->updateVhostForRole($roleId, $pattern);
        $this->events->dispatch(new OperRoleForcedVhostChangedEvent($roleId, $pattern));
    }

    public function refreshOperclass(int $roleId, ?string $operclass): void
    {
        $this->operclass->updateForRole($roleId, $operclass);
    }
}
