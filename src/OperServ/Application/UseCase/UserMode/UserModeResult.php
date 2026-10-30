<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\UserMode;

/**
 * Semantic outcome of UMODE; IRC wording is left to the inbound adapter.
 * The detail carries the applied mode string, or the mode letters above the actor's rank.
 */
final readonly class UserModeResult
{
    public function __construct(
        public UserModeOutcome $outcome,
        public string $targetNickname,
        public string $detail = '',
    ) {}
}
