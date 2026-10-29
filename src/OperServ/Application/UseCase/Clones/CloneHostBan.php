<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

/** The two host G-Lines of the original OPeR: KILLCLONES lasts one hour, BLOCK five hours. */
enum CloneHostBan: string
{
    case KillClones = 'KILLCLONES';
    case Block = 'BLOCK';

    /** Expiry in the GLINE ADD relative format. */
    public function duration(): string
    {
        return match ($this) {
            self::KillClones => '1h',
            self::Block => '5h',
        };
    }
}
