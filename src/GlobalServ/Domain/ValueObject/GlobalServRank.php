<?php

declare(strict_types=1);

namespace App\GlobalServ\Domain\ValueObject;

/**
 * Target staff rank for GlobalServ rank-filtered broadcasts.
 *
 * The values map to the staff ladder managed by CeNTeR/OperServ:
 * Pre-Operador, Operador, Administrador. CO-ADMIN and ADMIN both target
 * Administrador; CO-ADMIN includes ranks above it (Root), ADMIN is exact.
 */
enum GlobalServRank: string
{
    case PREOPER = 'PREOPER';

    case OPER = 'OPER';

    case ADMIN = 'ADMIN';

    case COADMIN = 'COADMIN';
}
