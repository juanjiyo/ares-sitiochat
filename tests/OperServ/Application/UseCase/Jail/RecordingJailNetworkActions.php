<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\Jail;

use App\OperServ\Application\Port\Out\JailNetworkActions;

/** Records every jail move; channels per UID are set by the test. */
final class RecordingJailNetworkActions implements JailNetworkActions
{
    /** @var list<array{string, string, string}> */
    public array $calls = [];

    /** @param array<string, list<string>> $channels */
    public function __construct(
        public array $channels = [],
        public bool $up = true,
    ) {}

    public function channelsOf(string $uid): array
    {
        return $this->channels[$uid] ?? [];
    }

    public function part(string $uid, string $channel): bool
    {
        $this->calls[] = ['part', $uid, $channel];

        return $this->up;
    }

    public function join(string $uid, string $channel): bool
    {
        if ($this->up) {
            $this->calls[] = ['join', $uid, $channel];
        }

        return $this->up;
    }
}
