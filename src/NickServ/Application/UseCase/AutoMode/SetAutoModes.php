<?php

declare(strict_types=1);

namespace App\NickServ\Application\UseCase\AutoMode;

/** Input of AUTOMODE: the target nick and its new automatic modes (empty clears them). */
final readonly class SetAutoModes
{
    public function __construct(
        public string $targetNickname,
        public string $modes,
    ) {}
}
