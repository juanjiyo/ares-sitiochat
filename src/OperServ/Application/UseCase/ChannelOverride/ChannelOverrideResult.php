<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

/**
 * Semantic outcome of a channel override; IRC wording is left to the inbound adapter.
 * The detail carries the applied kick reason or mode line, or the rejected mode string.
 */
final readonly class ChannelOverrideResult
{
    private function __construct(
        public ChannelOverrideOutcome $outcome,
        public string $channelName,
        public string $targetNickname,
        public string $detail,
    ) {}

    public static function applied(string $channelName, string $targetNickname = '', string $detail = ''): self
    {
        return new self(ChannelOverrideOutcome::Applied, $channelName, $targetNickname, $detail);
    }

    public static function refused(ChannelOverrideOutcome $outcome, string $channelName, string $targetNickname = '', string $detail = ''): self
    {
        return new self($outcome, $channelName, $targetNickname, $detail);
    }
}
