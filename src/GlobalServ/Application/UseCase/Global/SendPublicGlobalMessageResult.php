<?php

declare(strict_types=1);

namespace App\GlobalServ\Application\UseCase\Global;

/** Semantic outcome for a public GLOBAL broadcast. */
final readonly class SendPublicGlobalMessageResult
{
    private function __construct(
        public SendPublicGlobalMessageOutcome $outcome,
        public ?int $recipientCount = null,
    ) {}

    public static function sent(int $recipientCount): self
    {
        return new self(SendPublicGlobalMessageOutcome::Sent, $recipientCount);
    }

    public static function networkUnavailable(): self
    {
        return new self(SendPublicGlobalMessageOutcome::NetworkUnavailable);
    }
}
