<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Network;

use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\Spamfilter;
use App\Irc\Application\Port\In\SpamfilterPort;
use Symfony\Component\Clock\ClockInterface;

use function array_values;
use function sprintf;
use function str_replace;

/**
 * In-memory mirror of the IRCd global spamfilters, live for the duration of
 * the link: the IRCd re-sends every global TKL on link. The IRCd does not echo
 * our own TKL back, so add/remove update the mirror themselves.
 */
final class InMemorySpamfilterRegistry implements SpamfilterPort
{
    /** @var array<string, Spamfilter> */
    private array $spamfilters = [];

    public function __construct(
        private readonly ActiveConnectionHolderInterface $connection,
        private readonly ClockInterface $clock,
    ) {}

    public function record(Spamfilter $spamfilter): void
    {
        $this->spamfilters[$spamfilter->key()] = $spamfilter;
    }

    public function forget(string $targets, string $action, string $match): void
    {
        unset($this->spamfilters[Spamfilter::keyOf($targets, $action, $match)]);
    }

    public function clear(): void
    {
        $this->spamfilters = [];
    }

    public function list(): array
    {
        return array_values($this->spamfilters);
    }

    public function add(Spamfilter $spamfilter): bool
    {
        if (!$this->send('+', $spamfilter, $spamfilter->setBy)) {
            return false;
        }
        $this->record($spamfilter);

        return true;
    }

    public function remove(Spamfilter $spamfilter, string $removedBy): bool
    {
        if (!$this->send('-', $spamfilter, $removedBy)) {
            return false;
        }
        $this->forget($spamfilter->targets, $spamfilter->action, $spamfilter->match);

        return true;
    }

    private function send(string $sign, Spamfilter $spamfilter, string $by): bool
    {
        $serverSid = $this->connection->getServerSid();
        if (null === $serverSid || !$this->connection->isConnected()) {
            return false;
        }

        $this->connection->writeLine(sprintf(
            ':%s TKL %s F %s %s %s 0 %d %d %s %s :%s',
            $serverSid,
            $sign,
            $spamfilter->targets,
            $spamfilter->action,
            $by,
            $this->clock->now()->getTimestamp(),
            $spamfilter->banDuration,
            str_replace(' ', '_', $spamfilter->reason),
            $spamfilter->matchType,
            $spamfilter->match,
        ));

        return true;
    }
}
