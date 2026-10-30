<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Jail;

use function array_keys;

/** The users currently jailed, by UID. Runtime state: a jail ends with the session. */
final class JailRoster
{
    /** @var array<string, true> */
    private array $jailed = [];

    public function jail(string $uid): void
    {
        $this->jailed[$uid] = true;
    }

    /** Returns false when the user was not jailed. */
    public function release(string $uid): bool
    {
        if (!isset($this->jailed[$uid])) {
            return false;
        }

        unset($this->jailed[$uid]);

        return true;
    }

    public function contains(string $uid): bool
    {
        return isset($this->jailed[$uid]);
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_keys($this->jailed);
    }
}
