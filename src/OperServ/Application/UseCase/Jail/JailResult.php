<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Jail;

/** Semantic outcome of JAIL and UNJAIL; IRC wording is left to the inbound adapter. */
final readonly class JailResult
{
    public function __construct(
        public JailOutcome $outcome,
        public string $targetNickname,
        public string $jailChannel,
    ) {}
}
