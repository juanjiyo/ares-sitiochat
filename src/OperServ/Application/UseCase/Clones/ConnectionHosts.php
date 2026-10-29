<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;
use App\OperServ\Application\Port\Out\NetworkUser;

use function filter_var;
use function in_array;
use function str_starts_with;
use function strtolower;

use const FILTER_FLAG_IPV4;
use const FILTER_VALIDATE_IP;

/**
 * Groups connected sessions by the host they connect from, the rule shared by LISTCLONES,
 * KILLCLONES, BLOCK and CONEXIONES: the real IP when the IRCd sent a decodable one, otherwise
 * the real hostname, always lowercased so the same host is never counted twice.
 */
final readonly class ConnectionHosts
{
    public function __construct(private ConnectedUsersDirectory $users) {}

    public function hostOf(NetworkUser $user): string
    {
        $host = false !== filter_var($user->ipAddress, FILTER_VALIDATE_IP) ? $user->ipAddress : $user->hostname;

        return strtolower($host);
    }

    /** Loopback hosts (localhost, 127.0.0.0/8, ::1) belong to local services and are never G-Lined. */
    public function isLoopback(string $host): bool
    {
        $host = strtolower($host);
        if (in_array($host, ['localhost', '::1'], true)) {
            return true;
        }

        return str_starts_with($host, '127.') && false !== filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /** @return list<NetworkUser> */
    public function sessionsOn(string $host): array
    {
        $host = strtolower($host);
        $sessions = [];
        foreach ($this->users->listConnected() as $user) {
            if ($this->hostOf($user) === $host) {
                $sessions[] = $user;
            }
        }

        return $sessions;
    }

    /** @return array<string, int> session count keyed by host, in first-seen order */
    public function countByHost(): array
    {
        $counts = [];
        foreach ($this->users->listConnected() as $user) {
            $host = $this->hostOf($user);
            $counts[$host] = ($counts[$host] ?? 0) + 1;
        }

        return $counts;
    }
}
