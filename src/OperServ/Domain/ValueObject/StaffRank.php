<?php

declare(strict_types=1);

namespace App\OperServ\Domain\ValueObject;

use function strtolower;
use function strtoupper;

/**
 * Network staff ladder, as managed by the dbots CeNTeR module:
 * Pre-Operador < Operador < Administrador < Root (UnrealIRCd netadmin).
 * Each rank is a fixed role; its permissions are its own plus every lower rank's.
 */
enum StaffRank: string
{
    case PREOPER = 'PREOPER';
    case OPER = 'OPER';
    case ADMIN = 'ADMIN';
    case ROOT = 'ROOT';

    /** UnrealIRCd operclass granted with the rank. */
    public function operclass(): string
    {
        return match ($this) {
            self::PREOPER => 'locop',
            self::OPER => 'globop',
            self::ADMIN => 'admin',
            self::ROOT => 'netadmin',
        };
    }

    /** Middle label of the staff vhost: <nick>.<label>.<domain>. */
    public function vhostLabel(): string
    {
        return strtolower($this->value);
    }

    /** @return list<self> this rank and every rank below it */
    public function withLowerRanks(): array
    {
        $ranks = [];
        foreach (self::cases() as $rank) {
            $ranks[] = $rank;
            if ($rank === $this) {
                break;
            }
        }

        return $ranks;
    }

    /**
     * Permissions this rank adds on top of the lower ranks. ROOT adds every
     * permission in the catalog, which the role sync resolves.
     *
     * @return list<string>
     */
    public function ownPermissions(): array
    {
        return match ($this) {
            self::PREOPER => [
                'nickserv.list', 'nickserv.history', 'nickserv.whoip', 'nickserv.userip',
                'chanserv.list', 'chanserv.history',
                'operserv.kick', 'operserv.op', 'operserv.mode', 'operserv.invite', 'operserv.conexiones',
                'cregserv.pendientes', 'cregserv.denegados',
                'globalserv.preoper',
            ],
            self::OPER => [
                'operserv.kill', 'operserv.gline', 'operserv.clones', 'operserv.jail',
                'nickserv.suspend', 'nickserv.saset', 'nickserv.rename',
                'chanserv.suspend', 'chanserv.listsuspend', 'chanserv.listforbid', 'chanserv.clearusers',
                'cregserv.aprobar', 'cregserv.denegar',
                'globalserv.oper', 'globalserv.chan',
            ],
            self::ADMIN => [
                'operserv.eline', 'operserv.geoip', 'operserv.motd', 'operserv.umode', 'operserv.staff',
                'nickserv.drop', 'nickserv.forbid', 'nickserv.forbidvhost', 'nickserv.noexpire', 'nickserv.restore',
                'nickserv.saregister', 'nickserv.vreg', 'nickserv.blackmail',
                'chanserv.drop', 'chanserv.forbid', 'chanserv.noexpire', 'chanserv.restore', 'chanserv.clearaccess',
                'chanserv.level_founder', 'chanserv.ircoponly',
                'cregserv.drop', 'cregserv.saregister', 'cregserv.oficial', 'cregserv.apodera', 'cregserv.ig',
                'globalserv.global', 'globalserv.nick', 'globalserv.join', 'globalserv.admin',
            ],
            self::ROOT => [],
        };
    }

    public static function fromName(string $name): ?self
    {
        return self::tryFrom(strtoupper($name));
    }
}
