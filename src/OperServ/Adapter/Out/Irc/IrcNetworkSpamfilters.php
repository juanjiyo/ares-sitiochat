<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\Spamfilter;
use App\Irc\Application\Port\In\SpamfilterPort;
use App\OperServ\Application\Port\Out\NetworkSpamfilters;
use App\OperServ\Application\Port\Out\SpamfilterEntry;

final readonly class IrcNetworkSpamfilters implements NetworkSpamfilters
{
    public function __construct(
        private SpamfilterPort $spamfilters,
    ) {}

    public function all(): array
    {
        $entries = [];
        foreach ($this->spamfilters->list() as $spamfilter) {
            $entries[] = new SpamfilterEntry(
                $spamfilter->targets,
                $spamfilter->action,
                $spamfilter->matchType,
                $spamfilter->match,
                $spamfilter->banDuration,
                $spamfilter->reason,
                $spamfilter->setBy,
            );
        }

        return $entries;
    }

    public function add(SpamfilterEntry $entry): bool
    {
        return $this->spamfilters->add($this->toSpamfilter($entry));
    }

    public function remove(SpamfilterEntry $entry, string $removedBy): bool
    {
        return $this->spamfilters->remove($this->toSpamfilter($entry), $removedBy);
    }

    private function toSpamfilter(SpamfilterEntry $entry): Spamfilter
    {
        return new Spamfilter($entry->targets, $entry->action, $entry->matchType, $entry->match, $entry->banDuration, $entry->reason, $entry->setBy);
    }
}
