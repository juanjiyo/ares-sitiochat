<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageEline;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Port\Out\ElineNetworkActions;
use App\OperServ\Application\Port\Out\ElineRepository;
use App\OperServ\Application\Security\OperServPermission;
use App\Shared\Application\Time\RelativeExpiryParser;
use DateTimeImmutable;

use function array_values;
use function ctype_digit;
use function explode;
use function filter_var;
use function ltrim;
use function preg_match;
use function str_contains;
use function strtolower;

use const FILTER_VALIDATE_IP;

/**
 * ELINE: ban exceptions by IP, for trusted hosts such as the IRCops' ZNC boxes.
 * Entries are kept here and published to the IRCd, which expires its own copy.
 */
final readonly class ManageElineHandler
{
    public function __construct(
        private ElineRepository $elines,
        private ElineNetworkActions $network,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(ManageEline $command): ManageElineResult
    {
        $this->elines->removeExpiredAt($command->occurredAt);

        return match ($command->action) {
            ElineAction::Add => $this->add($command),
            ElineAction::Delete => $this->delete($command),
            ElineAction::List => new ManageElineResult(ManageElineOutcome::Listed, $this->elines->findActiveAt($command->occurredAt)),
            ElineAction::Count => new ManageElineResult(ManageElineOutcome::Counted, $this->elines->findActiveAt($command->occurredAt)),
            ElineAction::Clear => $this->clear($command),
        };
    }

    /** Publishes every active E-Line again, for an IRCd that may have lost them. */
    public function resync(DateTimeImmutable $now): void
    {
        foreach ($this->elines->findActiveAt($now) as $entry) {
            $this->network->add($entry);
        }
    }

    private function add(ManageEline $command): ManageElineResult
    {
        $mask = self::normalizeMask($command->target);
        if (null === $mask) {
            return new ManageElineResult(ManageElineOutcome::InvalidMask, detail: $command->target);
        }

        $expiresAt = null;
        if (null !== $command->expiry) {
            $expiresAt = RelativeExpiryParser::parse(ltrim($command->expiry, '+'), $command->occurredAt);
            if (null === $expiresAt && !RelativeExpiryParser::isPermanent(ltrim($command->expiry, '+'))) {
                return new ManageElineResult(ManageElineOutcome::InvalidExpiry, detail: $command->expiry);
            }
        }

        if (null !== $this->elines->findByMask($mask)) {
            return new ManageElineResult(ManageElineOutcome::AlreadyExists, detail: $mask);
        }

        $entry = new ElineEntry($mask, $command->actorNickname, $command->reason, $command->occurredAt, $expiresAt);
        if (!$this->network->add($entry)) {
            return new ManageElineResult(ManageElineOutcome::NetworkUnavailable);
        }

        $this->elines->save($entry);
        $this->record($command, 'ELINE ADD', $mask, $command->reason);

        return new ManageElineResult(ManageElineOutcome::Added, [$entry]);
    }

    private function delete(ManageEline $command): ManageElineResult
    {
        $active = $this->elines->findActiveAt($command->occurredAt);
        $selected = self::select($command->target, $active);
        if ([] === $selected) {
            return new ManageElineResult(ManageElineOutcome::NotFound, detail: $command->target);
        }

        foreach ($selected as $entry) {
            $this->elines->remove($entry->mask);
            $this->network->remove($entry->mask, $command->actorNickname);
            $this->record($command, 'ELINE DEL', $entry->mask, null);
        }

        return new ManageElineResult(ManageElineOutcome::Deleted, $selected);
    }

    /** Removes every active E-Line at once. */
    private function clear(ManageEline $command): ManageElineResult
    {
        $active = $this->elines->findActiveAt($command->occurredAt);
        foreach ($active as $entry) {
            $this->elines->remove($entry->mask);
            $this->network->remove($entry->mask, $command->actorNickname);
        }

        $this->record($command, 'ELINE CLEAR', '*', null);

        return new ManageElineResult(ManageElineOutcome::Cleared, $active);
    }

    /**
     * user@ip or ip; the host must be an IP address or a CIDR range, the user part may
     * use wildcards. Returns the user@ip form, or null when the mask is not valid.
     */
    private static function normalizeMask(string $mask): ?string
    {
        if (!str_contains($mask, '@')) {
            $mask = '*@' . $mask;
        }

        [$user, $host] = explode('@', $mask, 2);
        if (1 !== preg_match('/^[^\s@!]{1,10}$/', $user)) {
            return null;
        }

        $address = explode('/', $host, 2);
        if (false === filter_var($address[0], FILTER_VALIDATE_IP)) {
            return null;
        }

        if (isset($address[1])) {
            $maxPrefix = str_contains($address[0], ':') ? 128 : 32;
            if (!ctype_digit($address[1]) || (int) $address[1] > $maxPrefix) {
                return null;
            }
        }

        return $user . '@' . strtolower($host);
    }

    /**
     * A mask, an entry number of LIST, or a list of numbers and ranges such as 1,3-5.
     *
     * @param list<ElineEntry> $active
     *
     * @return list<ElineEntry>
     */
    private static function select(string $target, array $active): array
    {
        if (1 !== preg_match('/^\d+(?:-\d+)?(?:,\d+(?:-\d+)?)*$/', $target)) {
            $mask = self::normalizeMask($target) ?? $target;
            foreach ($active as $entry) {
                if ($entry->mask === $mask) {
                    return [$entry];
                }
            }

            return [];
        }

        $selected = [];
        foreach (explode(',', $target) as $part) {
            [$from, $to] = str_contains($part, '-') ? explode('-', $part, 2) : [$part, $part];
            for ($number = (int) $from; $number <= (int) $to; ++$number) {
                if (isset($active[$number - 1])) {
                    $selected[$number] = $active[$number - 1];
                }
            }
        }

        return array_values($selected);
    }

    private function record(ManageEline $command, string $operation, string $mask, ?string $reason): void
    {
        $this->audit->record(new CommandAuditRecord(
            category: CommandAuditCategory::OperatorAction,
            service: 'OperServ',
            actor: $command->actorNickname,
            operation: $operation,
            occurredAt: $command->occurredAt,
            target: $mask,
            reason: $reason,
            permission: OperServPermission::ELINE,
        ));
    }
}
