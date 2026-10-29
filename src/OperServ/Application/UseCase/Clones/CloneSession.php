<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

/** Another session connected from the same host as the LISTCLONES target. */
final readonly class CloneSession
{
    public function __construct(
        public string $nickname,
        public string $ident,
    ) {}
}
