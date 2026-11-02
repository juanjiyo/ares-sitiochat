<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Projection;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\NickServ\Application\Port\In\IdentifiedSessionQuery;
use App\OperServ\Adapter\In\Event\IrcopModeApplier;
use App\OperServ\Adapter\In\Event\IrcopOperclassApplier;
use App\OperServ\Application\Port\Out\OperatorAssignmentNetworkProjection;
use App\OperServ\Application\Port\Out\OperatorRoleRecord;
use App\OperServ\Application\PublishedEvent\OperIrcopChangedEvent;
use App\OperServ\Domain\Entity\OperRole;
use App\OperServ\Domain\Repository\OperRoleRepositoryInterface;
use App\OperServ\Domain\ValueObject\StaffRank;
use App\Shared\Application\Port\EventBusInterface;
use LogicException;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function in_array;

final readonly class DoctrineOperatorAssignmentNetworkProjection implements OperatorAssignmentNetworkProjection
{
    /**
     * @param string $staffChannels comma-separated channels every staff member leaves when removed
     * @param string $adminChannels comma-separated channels ADMIN and ROOT join when added and leave when removed
     */
    public function __construct(
        private IrcopModeApplier $modes,
        private IrcopOperclassApplier $operclass,
        private OperRoleRepositoryInterface $roles,
        private EventBusInterface $events,
        private IdentifiedSessionQuery $sessions,
        private ActiveProtocolModuleHolderInterface $connection,
        private string $staffChannels,
        private string $adminChannels,
    ) {}

    public function apply(int $nickId, string $nick, OperatorRoleRecord $role): void
    {
        $r = $this->role($role->name);
        $this->modes->applyModesForNick($nick, $r);
        $this->operclass->applyForNick($nick, $r);
        $this->joinAdminChannels($nick, $role->name);
        $this->events->dispatch(new OperIrcopChangedEvent($nickId, $nick));
    }

    public function remove(int $nickId, string $nick, OperatorRoleRecord $role): void
    {
        $r = $this->role($role->name);
        $this->modes->removeModesForNick($nick, $r);
        $this->operclass->removeForNick($nick);
        $this->partStaffChannels($nick, $role->name);
        $this->events->dispatch(new OperIrcopChangedEvent($nickId, $nick));
    }

    public function replace(int $nickId, string $nick, OperatorRoleRecord $oldRole, OperatorRoleRecord $newRole): void
    {
        $old = $this->role($oldRole->name);
        $new = $this->role($newRole->name);
        $this->modes->removeModesForNick($nick, $old);
        $this->operclass->removeForNick($nick);
        $this->modes->applyModesForNick($nick, $new);
        $this->operclass->applyForNick($nick, $new);
        $this->events->dispatch(new OperIrcopChangedEvent($nickId, $nick));
    }

    private function joinAdminChannels(string $nick, string $roleName): void
    {
        $target = $this->connectedTarget($nick);
        if (null === $target || !$this->isAdminRank($roleName)) {
            return;
        }

        [$actions, $serverSid, $uid] = $target;
        foreach ($this->channelList($this->adminChannels) as $channel) {
            $actions->forceJoin($serverSid, $uid, $channel);
        }
    }

    private function partStaffChannels(string $nick, string $roleName): void
    {
        $target = $this->connectedTarget($nick);
        if (null === $target) {
            return;
        }

        [$actions, $serverSid, $uid] = $target;
        $channels = $this->channelList($this->staffChannels);
        if ($this->isAdminRank($roleName)) {
            $channels = [...$channels, ...$this->channelList($this->adminChannels)];
        }
        foreach ($channels as $channel) {
            $actions->forcePart($serverSid, $uid, $channel);
        }
    }

    /** @return array{ProtocolServiceActionsInterface, string, string}|null */
    private function connectedTarget(string $nick): ?array
    {
        $uid = $this->sessions->findUidByNick($nick);
        $actions = $this->connection->getProtocolModule()?->getServiceActions();
        $serverSid = $this->connection->getServerSid();

        return null === $uid || null === $actions || null === $serverSid ? null : [$actions, $serverSid, $uid];
    }

    private function isAdminRank(string $roleName): bool
    {
        return in_array(StaffRank::fromName($roleName), [StaffRank::ADMIN, StaffRank::ROOT], true);
    }

    /** @return list<string> */
    private function channelList(string $channels): array
    {
        return array_values(array_filter(array_map(trim(...), explode(',', $channels)), static fn (string $c): bool => '' !== $c));
    }

    private function role(string $name): OperRole
    {
        return $this->roles->findByName($name) ?? throw new LogicException('Role disappeared during projection.');
    }
}
