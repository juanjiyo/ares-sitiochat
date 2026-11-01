<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageSpamfilter;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\Out\NetworkSpamfilters;
use App\OperServ\Application\Port\Out\SpamfilterEntry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Domain\Policy\SpamfilterPolicy;
use App\Shared\Application\Time\RelativeExpiryParser;

use function ctype_digit;
use function preg_match;
use function str_contains;
use function str_replace;
use function strtolower;

/**
 * Manages the IRCd global spamfilters. The IRCd owns and persists them;
 * services only ask it to add or remove one and read its mirror.
 */
final readonly class ManageSpamfilterHandler
{
    private const string DEFAULT_DURATION = '1d';

    public function __construct(
        private NetworkSpamfilters $spamfilters,
        private CommandAuditRecorder $audit,
    ) {}

    public function handle(ManageSpamfilter $command): ManageSpamfilterResult
    {
        return match ($command->action) {
            SpamfilterAction::Add => $this->add($command),
            SpamfilterAction::Delete => $this->delete($command),
            SpamfilterAction::List => $this->list($command),
            SpamfilterAction::Unknown => new ManageSpamfilterResult(ManageSpamfilterOutcome::UnknownAction),
        };
    }

    private function add(ManageSpamfilter $command): ManageSpamfilterResult
    {
        $pattern = $command->pattern ?? '';
        $reason = $command->reason ?? '';
        if ('' === ($command->targets ?? '') || '' === ($command->spamAction ?? '') || '' === $pattern || '' === $reason) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::InvalidRequest);
        }
        $targets = SpamfilterPolicy::targets((string) $command->targets);
        if (null === $targets) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::InvalidTargets);
        }
        $action = SpamfilterPolicy::actionLetter((string) $command->spamAction);
        if (null === $action) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::InvalidAction);
        }
        if ($command->regex && false === @preg_match('/' . str_replace('/', '\/', $pattern) . '/', '')) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::InvalidRegex);
        }
        $until = RelativeExpiryParser::parse($command->duration ?? self::DEFAULT_DURATION, $command->occurredAt);
        if (null === $until) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::InvalidDuration);
        }

        foreach ($this->spamfilters->all() as $existing) {
            if ($existing->targets === $targets && $existing->action === $action && $existing->match === $pattern) {
                return new ManageSpamfilterResult(ManageSpamfilterOutcome::AlreadyExists, $existing);
            }
        }

        $entry = new SpamfilterEntry(
            $targets,
            $action,
            $command->regex ? 'regex' : 'simple',
            $pattern,
            $until->getTimestamp() - $command->occurredAt->getTimestamp(),
            $reason,
            $command->actor,
        );
        if (!$this->spamfilters->add($entry)) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::Unavailable);
        }
        $this->recordAudit($command, 'SPAMFILTER ADD', $entry);

        return new ManageSpamfilterResult(ManageSpamfilterOutcome::Added, $entry);
    }

    private function delete(ManageSpamfilter $command): ManageSpamfilterResult
    {
        $item = $command->item ?? '';
        if ('' === $item) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::InvalidRequest);
        }

        $entry = $this->find($item);
        if (null === $entry) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::NotFound, item: $item);
        }
        if (!$this->spamfilters->remove($entry, $command->actor)) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::Unavailable);
        }
        $this->recordAudit($command, 'SPAMFILTER DEL', $entry);

        return new ManageSpamfilterResult(ManageSpamfilterOutcome::Deleted, $entry);
    }

    private function list(ManageSpamfilter $command): ManageSpamfilterResult
    {
        $filter = strtolower($command->listFilter ?? '');
        $entries = [];
        foreach ($this->spamfilters->all() as $index => $entry) {
            if ('' === $filter || str_contains(strtolower($entry->match), $filter)) {
                $entries[$index + 1] = $entry;
            }
        }

        if ([] === $entries) {
            return new ManageSpamfilterResult(ManageSpamfilterOutcome::ListEmpty);
        }

        return new ManageSpamfilterResult(ManageSpamfilterOutcome::Listed, entries: $entries);
    }

    /** By its LIST number, or by its exact pattern. */
    private function find(string $item): ?SpamfilterEntry
    {
        $all = $this->spamfilters->all();
        if (ctype_digit($item)) {
            return $all[(int) $item - 1] ?? null;
        }
        foreach ($all as $entry) {
            if ($entry->match === $item) {
                return $entry;
            }
        }

        return null;
    }

    private function recordAudit(ManageSpamfilter $command, string $operation, SpamfilterEntry $entry): void
    {
        $this->audit->record(new CommandAuditRecord(
            CommandAuditCategory::OperatorAction,
            'operserv',
            $command->actor,
            $operation,
            $command->occurredAt,
            $entry->match,
            $entry->reason,
            OperServPermission::SPAMFILTER,
            metadata: [
                'targets' => $entry->targets,
                'action' => SpamfilterPolicy::actionName($entry->action),
                'match_type' => $entry->matchType,
                'ban_duration' => $entry->banDuration,
            ],
        ));
    }
}
