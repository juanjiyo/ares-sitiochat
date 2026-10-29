<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\NetworkInfo;

use App\OperServ\Application\Port\Out\ConnectedUsersDirectory;

use function strnatcasecmp;
use function usort;

/** Answers IRCOPS: the nicknames of the IRC operators connected right now, in natural order. */
final readonly class ListOnlineOperatorsHandler
{
    public function __construct(private ConnectedUsersDirectory $users) {}

    /** @return list<string> */
    public function handle(): array
    {
        $operators = [];
        foreach ($this->users->listConnected() as $user) {
            if ($user->ircOperator) {
                $operators[] = $user->nickname;
            }
        }

        usort($operators, strnatcasecmp(...));

        return $operators;
    }
}
