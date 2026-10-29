<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

use App\OperServ\Application\Port\Out\NetworkUserLookup;

use function count;
use function strnatcasecmp;
use function usort;

/** Answers LISTCLONES: the host of a nickname and the other nicknames connected from it. */
final readonly class ListClonesHandler
{
    public function __construct(
        private NetworkUserLookup $users,
        private ConnectionHosts $hosts,
    ) {}

    public function handle(ListClones $query): ListClonesResult
    {
        $target = $this->users->findByNickname($query->targetNickname);
        if (null === $target) {
            return ListClonesResult::notOnline($query->targetNickname);
        }

        $host = $this->hosts->hostOf($target);
        $sessions = $this->hosts->sessionsOn($host);
        $clones = [];
        foreach ($sessions as $session) {
            if ($session->uid !== $target->uid) {
                $clones[] = new CloneSession($session->nickname, $session->ident);
            }
        }
        usort($clones, static fn (CloneSession $a, CloneSession $b): int => strnatcasecmp($a->nickname, $b->nickname));

        return ListClonesResult::listed($target->nickname, $host, count($sessions), $clones);
    }
}
