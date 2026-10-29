<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\Out\ChannelOverrideActions;

/** Records every channel override request and answers with a configured network availability. */
final class RecordingChannelOverrideActions implements ChannelOverrideActions
{
    /** @var list<array{string, string, list<string>|string|bool}> */
    public array $calls = [];

    public function __construct(private readonly bool $available = true) {}

    public function kick(string $channelName, string $targetUid, string $reason): bool
    {
        $this->calls[] = ['kick', $channelName, [$targetUid, $reason]];

        return $this->available;
    }

    public function setOperatorStatus(string $channelName, string $targetUid, bool $grant): bool
    {
        $this->calls[] = ['operator', $channelName, [$targetUid, $grant ? '+o' : '-o']];

        return $this->available;
    }

    public function setModes(string $channelName, string $modes, array $params): bool
    {
        $this->calls[] = ['modes', $channelName, [$modes, ...$params]];

        return $this->available;
    }

    public function invite(string $channelName, string $targetUid): bool
    {
        $this->calls[] = ['invite', $channelName, [$targetUid]];

        return $this->available;
    }
}
