<?php

declare(strict_types=1);

namespace App\NickServ\Application\UseCase\AutoMode;

final readonly class SetAutoModesResult
{
    public function __construct(
        public SetAutoModesOutcome $outcome,
        public string $nickname,
        public ?string $modes = null,
        public ?string $invalidModes = null,
    ) {}
}
