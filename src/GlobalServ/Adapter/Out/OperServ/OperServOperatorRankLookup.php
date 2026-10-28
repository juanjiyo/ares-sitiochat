<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\Out\OperServ;

use App\GlobalServ\Application\Port\Out\GlobalServNickAccountPort;
use App\GlobalServ\Application\Port\Out\OperatorRankLookupPort;
use App\GlobalServ\Domain\ValueObject\GlobalServRank;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\OperServ\Application\Port\In\OperatorNetworkProjectionQuery;

use function in_array;
use function str_ends_with;
use function strlen;
use function substr;

/**
 * Resolves a connected user's staff rank from the OperServ network projection.
 *
 * ares models staff as IRCop operclasses, so the dbots ladder maps onto the
 * default operclass tiers:
 *
 *   Pre-Operador -> any operclass
 *   Operador     -> globop and above
 *   Administrador -> exact admin tier (ADMIN)
 *   Administrador (exact) -> admin (ADMIN)
 *
 * Returns null for an unknown operclass so the use case reports the rank as
 * unsupported instead of silently broadcasting to nobody.
 */
final readonly class OperServOperatorRankLookup implements OperatorRankLookupPort
{
    private const array TIER_PREOPER = ['locop', 'globop', 'admin', 'services-admin', 'netadmin'];

    private const array TIER_OPER = ['globop', 'admin', 'services-admin', 'netadmin'];

    private const array TIER_ADMIN_EXACT = ['admin'];

    public function __construct(
        private NetworkUserLookupPort $users,
        private GlobalServNickAccountPort $accounts,
        private OperatorNetworkProjectionQuery $projections,
    ) {}

    public function hasAtLeastRank(string $uid, GlobalServRank $rank): ?bool
    {
        $user = $this->users->findByUid($uid);
        if (null === $user) {
            return null;
        }

        if (!$user->isOper) {
            return false;
        }

        $accountId = $this->accounts->findAccountIdByNick($user->nick);
        if (null === $accountId) {
            return null;
        }

        $projection = $this->projections->findForNick($accountId, $user->nick);
        if (null === $projection || null === $projection->operclass) {
            return null;
        }

        $operclass = $this->normalizeOperclass($projection->operclass);

        return match ($rank) {
            GlobalServRank::PREOPER => self::inTier($operclass, self::TIER_PREOPER),
            GlobalServRank::OPER => self::inTier($operclass, self::TIER_OPER),
            GlobalServRank::ADMIN => self::inTier($operclass, self::TIER_ADMIN_EXACT),
        };
    }

    private function normalizeOperclass(string $operclass): string
    {
        $normalized = strtolower($operclass);

        return str_ends_with($normalized, '-with-override')
            ? substr($normalized, 0, -strlen('-with-override'))
            : $normalized;
    }

    /**
     * @param list<string> $tier
     */
    private static function inTier(string $operclass, array $tier): bool
    {
        return in_array($operclass, $tier, true);
    }
}
