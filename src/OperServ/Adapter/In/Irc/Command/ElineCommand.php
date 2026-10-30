<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ManageEline\ElineAction;
use App\OperServ\Application\UseCase\ManageEline\ManageEline;
use App\OperServ\Application\UseCase\ManageEline\ManageElineHandler;
use App\OperServ\Application\UseCase\ManageEline\ManageElineOutcome;
use DateTimeImmutable;

use function array_map;
use function array_slice;
use function count;
use function implode;
use function str_starts_with;
use function strtoupper;

/** IRC parsing adapter for ELINE ADD [+expiry] <user@ip|ip> <reason> | DEL <mask|number|list> | LIST | VIEW | COUNT. */
final readonly class ElineCommand implements OperServCommandInterface
{
    public function __construct(private ManageElineHandler $handler) {}

    public function getName(): string
    {
        return 'ELINE';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getMinArgs(): int
    {
        return 1;
    }

    public function getSyntaxKey(): string
    {
        return 'eline.syntax';
    }

    public function getHelpKey(): string
    {
        return 'eline.help';
    }

    public function getExampleKey(): string
    {
        return 'eline.example';
    }

    public function getOrder(): int
    {
        return 24;
    }

    public function getShortDescKey(): string
    {
        return 'eline.short';
    }

    public function getSubCommandHelp(): array
    {
        return [
            ['name' => 'ADD', 'desc_key' => 'eline.add.short', 'help_key' => 'eline.add.help', 'syntax_key' => 'eline.add.syntax', 'example_key' => 'eline.add.example'],
            ['name' => 'DEL', 'desc_key' => 'eline.del.short', 'help_key' => 'eline.del.help', 'syntax_key' => 'eline.del.syntax', 'example_key' => 'eline.del.example'],
            ['name' => 'LIST', 'desc_key' => 'eline.list.short', 'help_key' => 'eline.list.help', 'syntax_key' => 'eline.list.syntax', 'example_key' => 'eline.list.example'],
            ['name' => 'VIEW', 'desc_key' => 'eline.view.short', 'help_key' => 'eline.view.help', 'syntax_key' => 'eline.view.syntax', 'example_key' => 'eline.view.example'],
            ['name' => 'COUNT', 'desc_key' => 'eline.count.short', 'help_key' => 'eline.count.help', 'syntax_key' => 'eline.count.syntax', 'example_key' => 'eline.count.example'],
        ];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return OperServPermission::ELINE;
    }

    /** @return array<string, string> */
    public function getHelpParams(): array
    {
        return [];
    }

    public function execute(OperServContext $context): void
    {
        if (null === $context->sender) {
            return;
        }

        $action = strtoupper($context->args[0]);
        $args = array_slice($context->args, 1);
        $now = new DateTimeImmutable();
        $actor = $context->sender->nick;

        $command = match ($action) {
            'ADD' => $this->addRequest($args, $actor, $now),
            'DEL' => isset($args[0]) ? new ManageEline(ElineAction::Delete, $actor, $now, $args[0]) : null,
            'LIST', 'VIEW' => new ManageEline(ElineAction::List, $actor, $now),
            'COUNT' => new ManageEline(ElineAction::Count, $actor, $now),
            default => null,
        };

        if (null === $command) {
            $context->reply('error.syntax', ['syntax' => $context->trans('eline.syntax')]);

            return;
        }

        $result = $this->handler->handle($command);

        match ($result->outcome) {
            ManageElineOutcome::Added => $context->reply('eline.added', ['mask' => $result->entries[0]->mask, 'expiration' => $this->expiration($context, $result->entries[0])]),
            ManageElineOutcome::AlreadyExists => $context->reply('eline.already_exists', ['mask' => $result->detail]),
            ManageElineOutcome::InvalidMask => $context->reply('eline.invalid_mask', ['mask' => $result->detail]),
            ManageElineOutcome::InvalidExpiry => $context->reply('eline.invalid_expiry', ['expiry' => $result->detail]),
            ManageElineOutcome::Deleted => $context->reply('eline.deleted', ['masks' => implode(', ', array_map(static fn (ElineEntry $entry): string => $entry->mask, $result->entries))]),
            ManageElineOutcome::NotFound => $context->reply('eline.not_found', ['item' => $result->detail]),
            ManageElineOutcome::Counted => $context->reply('eline.count.result', ['count' => (string) count($result->entries)]),
            ManageElineOutcome::Listed => $this->list($context, $result->entries, 'VIEW' === $action),
            ManageElineOutcome::NetworkUnavailable => null,
        };
    }

    /** @param list<string> $args */
    private function addRequest(array $args, string $actor, DateTimeImmutable $now): ?ManageEline
    {
        $expiry = isset($args[0]) && str_starts_with($args[0], '+') ? $args[0] : null;
        if (null !== $expiry) {
            $args = array_slice($args, 1);
        }

        if (count($args) < 2) {
            return null;
        }

        return new ManageEline(ElineAction::Add, $actor, $now, $args[0], implode(' ', array_slice($args, 1)), $expiry);
    }

    /** @param list<ElineEntry> $entries */
    private function list(OperServContext $context, array $entries, bool $detailed): void
    {
        if ([] === $entries) {
            $context->reply('eline.list.empty');

            return;
        }

        $context->reply('eline.list.header', ['count' => (string) count($entries)]);
        foreach ($entries as $index => $entry) {
            $params = ['number' => (string) ($index + 1), 'mask' => $entry->mask, 'expiration' => $this->expiration($context, $entry)];
            $context->reply($detailed ? 'eline.view.entry' : 'eline.list.entry', $detailed
                ? [...$params, 'setby' => $entry->setBy, 'created' => $context->formatDate($entry->createdAt), 'reason' => $entry->reason]
                : $params);
        }
    }

    private function expiration(OperServContext $context, ElineEntry $entry): string
    {
        return null === $entry->expiresAt ? $context->trans('eline.never_expires') : $context->formatDate($entry->expiresAt);
    }
}
