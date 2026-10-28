<?php

declare(strict_types=1);

namespace App\ChanServ\Application\UseCase\KickUser;

final readonly class KickUserResult
{
    private function __construct(public KickUserOutcome $outcome, public ?string $channelName = null, public ?string $targetNickname = null) {}

    public static function kicked(string $channelName, string $targetNickname): self
    {
        return new self(KickUserOutcome::Kicked, $channelName, $targetNickname);
    }

    public static function channelNotRegistered(string $channelName): self
    {
        return new self(KickUserOutcome::ChannelNotRegistered, $channelName);
    }

    public static function channelNotOnNetwork(string $channelName): self
    {
        return new self(KickUserOutcome::ChannelNotOnNetwork, $channelName);
    }

    public static function targetNotConnected(string $targetNickname): self
    {
        return new self(KickUserOutcome::TargetNotConnected, targetNickname: $targetNickname);
    }
}
