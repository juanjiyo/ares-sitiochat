<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

use function strcmp;
use function strtolower;
use function usort;

/** Answers CONEXIONES: simultaneous sessions per host. */
final readonly class ConnectionSessionsHandler
{
    public function __construct(private ConnectionHosts $hosts) {}

    /** @return list<HostSessionCount> hosts with at least $minimum sessions, most sessions first, then by host */
    public function hostsWithAtLeast(int $minimum): array
    {
        $hosts = [];
        foreach ($this->hosts->countByHost() as $host => $sessions) {
            if ($sessions >= $minimum) {
                $hosts[] = new HostSessionCount((string) $host, $sessions);
            }
        }
        usort($hosts, static fn (HostSessionCount $a, HostSessionCount $b): int => $b->sessions <=> $a->sessions ?: strcmp($a->host, $b->host));

        return $hosts;
    }

    public function sessionsOn(string $host): int
    {
        return $this->hosts->countByHost()[strtolower($host)] ?? 0;
    }
}
