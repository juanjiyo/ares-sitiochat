<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Jail;

use App\OperServ\Application\Port\Out\JailNetworkActions;

use function array_map;
use function in_array;
use function strcasecmp;
use function strtolower;

/** Keeps jailed users in the jail channel: out of any other channel, back in if they leave it. */
final readonly class EnforceJail
{
    public function __construct(
        private JailNetworkActions $network,
        private JailRoster $roster,
        private string $jailChannel,
    ) {}

    public function userJoined(string $uid, string $channel): void
    {
        if ($this->roster->contains($uid) && !$this->isJail($channel)) {
            $this->network->part($uid, $channel);
        }
    }

    public function userDeparted(string $uid, string $channel): void
    {
        if ($this->roster->contains($uid) && $this->isJail($channel)) {
            $this->network->join($uid, $this->jailChannel);
        }
    }

    public function userLeftNetwork(string $uid): void
    {
        $this->roster->release($uid);
    }

    /** A channel created by its first member is announced as synchronized, not as a join. */
    public function channelSynchronized(string $channel): void
    {
        if ($this->isJail($channel)) {
            return;
        }

        foreach ($this->roster->all() as $uid) {
            if (in_array(strtolower($channel), array_map(strtolower(...), $this->network->channelsOf($uid)), true)) {
                $this->network->part($uid, $channel);
            }
        }
    }

    private function isJail(string $channel): bool
    {
        return 0 === strcasecmp($channel, $this->jailChannel);
    }
}
