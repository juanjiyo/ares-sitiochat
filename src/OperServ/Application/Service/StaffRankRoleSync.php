<?php

declare(strict_types=1);

namespace App\OperServ\Application\Service;

use App\OperServ\Application\Port\In\OperatorPermissionCatalog;
use App\OperServ\Application\Port\Out\OperatorRoleNetworkProjection;
use App\OperServ\Application\Port\Out\OperatorRoleStore;
use App\OperServ\Domain\ValueObject\StaffRank;

use function array_intersect;
use function array_merge;
use function array_unique;
use function array_values;

/**
 * Keeps the four staff rank roles (PREOPER, OPER, ADMIN, ROOT) in the role store
 * exactly as the code defines them: cumulative permissions, operclass and the
 * forced vhost <nick>.<rank>.<domain>. Runs on every link, so a deploy that
 * changes the ladder is applied without a data migration.
 */
final readonly class StaffRankRoleSync
{
    private const array DESCRIPTIONS = [
        'PREOPER' => 'Pre-Operador',
        'OPER' => 'Operador',
        'ADMIN' => 'Administrador',
        'ROOT' => 'Root (netadmin)',
    ];

    public function __construct(
        private OperatorRoleStore $roles,
        private OperatorPermissionCatalog $catalog,
        private OperatorRoleNetworkProjection $network,
        private string $vhostDomain,
    ) {}

    public function sync(): void
    {
        foreach (StaffRank::cases() as $rank) {
            $name = $rank->value;
            $vhost = $rank->vhostLabel() . '.' . $this->vhostDomain;
            $existing = $this->roles->findByName($name);
            if (null === $existing) {
                $this->roles->create($name, self::DESCRIPTIONS[$name]);
            }
            $this->roles->setPermissions($name, $this->permissionsFor($rank));
            $this->roles->setOperclass($name, $rank->operclass());
            $this->roles->setForcedVhostPattern($name, $vhost);

            // Operators already holding an existing role get the changed settings on the network.
            if (null !== $existing && $existing->operclass !== $rank->operclass()) {
                $this->network->refreshOperclass($existing->id, $rank->operclass());
            }
            if (null !== $existing && $existing->forcedVhostPattern !== $vhost) {
                $this->network->refreshVhost($existing->id, $vhost);
            }
        }
    }

    /** @return list<string> the rank's own permissions plus every lower rank's; ROOT gets the whole catalog */
    public function permissionsFor(StaffRank $rank): array
    {
        $catalog = $this->catalog->getAllPermissions();
        if (StaffRank::ROOT === $rank) {
            return $catalog;
        }

        $permissions = [];
        foreach ($rank->withLowerRanks() as $included) {
            $permissions = array_merge($permissions, $included->ownPermissions());
        }

        return array_values(array_intersect(array_unique($permissions), $catalog));
    }
}
