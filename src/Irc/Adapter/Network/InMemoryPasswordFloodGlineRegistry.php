<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Network;

use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\PasswordFloodGline;
use App\Irc\Application\Port\In\PasswordFloodGlinePort;
use DateTimeImmutable;
use Symfony\Component\Clock\ClockInterface;

use function array_values;
use function sprintf;
use function strtolower;

/**
 * In-memory mirror of the IRCd G-Lines signed by the UDB module's password
 * flood protection. Live for the duration of the link: the IRCd re-sends every
 * global TKL on link, and it does not announce expiry, so expired entries are
 * dropped when read.
 */
final class InMemoryPasswordFloodGlineRegistry implements PasswordFloodGlinePort
{
    public const string SET_BY = 'UDB:password-flood';

    /** @var array<string, PasswordFloodGline> */
    private array $glines = [];

    public function __construct(
        private readonly ActiveConnectionHolderInterface $connection,
        private readonly ClockInterface $clock,
    ) {}

    public function record(PasswordFloodGline $gline): void
    {
        $this->glines[strtolower($gline->mask())] = $gline;
    }

    public function forget(string $user, string $host): void
    {
        unset($this->glines[strtolower($user . '@' . $host)]);
    }

    public function clear(): void
    {
        $this->glines = [];
    }

    public function list(): array
    {
        $now = $this->clock->now();
        foreach ($this->glines as $key => $gline) {
            if (null !== $gline->expiresAt && $gline->expiresAt <= $now) {
                unset($this->glines[$key]);
            }
        }

        return array_values($this->glines);
    }

    public function remove(PasswordFloodGline $gline): void
    {
        $serverSid = $this->connection->getServerSid();
        if (null !== $serverSid && $this->connection->isConnected()) {
            $this->connection->writeLine(sprintf(':%s TKL - G %s %s %s', $serverSid, $gline->user, $gline->host, self::SET_BY));
        }

        // The IRCd does not echo our own TKL back, so the mirror forgets it here.
        $this->forget($gline->user, $gline->host);
    }

    public static function expiry(int $expiresAt): ?DateTimeImmutable
    {
        return 0 >= $expiresAt ? null : new DateTimeImmutable('@' . $expiresAt);
    }
}
