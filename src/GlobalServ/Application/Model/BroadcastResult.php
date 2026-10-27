<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\Model;

final readonly class BroadcastResult
{
    private function __construct(
        public BroadcastOutcome $outcome,
        public ?int $recipientCount = null,
        public ?string $channelName = null,
    ) {}

    public static function sent(int $recipientCount): self
    {
        return new self(BroadcastOutcome::Sent, $recipientCount);
    }

    public static function networkUnavailable(): self
    {
        return new self(BroadcastOutcome::NetworkUnavailable);
    }

    public static function channelNotFound(string $channelName): self
    {
        return new self(BroadcastOutcome::ChannelNotFound, channelName: $channelName);
    }

    public static function joinUnsupported(): self
    {
        return new self(BroadcastOutcome::JoinUnsupported);
    }

    public static function rankUnsupported(): self
    {
        return new self(BroadcastOutcome::RankUnsupported);
    }
}
