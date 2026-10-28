<?php

declare(strict_types=1);

namespace App\GlobalServ\Domain\ValueObject;

/**
 * Target staff rank for GlobalServ rank-filtered broadcasts.
 *
 * The values map to the staff ladder managed by CeNTeR/OperServ:
 * Pre-Operador, Operador, Administrador. ADMIN targets the exact
 * Administrador tier. There is no Co-Administrador role in Ares.
 */
enum GlobalServRank: string
{
    case PREOPER = 'PREOPER';

    case OPER = 'OPER';

    case ADMIN = 'ADMIN';
}
