<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Port\Out\SpamfilterEntry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilter;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilterHandler;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilterOutcome;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilterResult;
use App\OperServ\Application\UseCase\ManageSpamfilter\SpamfilterAction;
use App\OperServ\Domain\Policy\SpamfilterPolicy;
use DateTimeImmutable;

use function array_shift;
use function array_slice;
use function count;
use function implode;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;

/**
 * IRC adapter for SPAMFILTER: global spamfilters of the IRCd (port of the
 * dbots nospam module). The IRCd owns them; OperServ adds, lists and removes.
 */
final readonly class SpamfilterCommand implements OperServCommandInterface
{
    public function __construct(private ManageSpamfilterHandler $handler) {}

    public function getName(): string
    {
        return 'SPAMFILTER';
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
        return 'spamfilter.syntax';
    }

    public function getHelpKey(): string
    {
        return 'spamfilter.help';
    }

    public function getExampleKey(): string
    {
        return 'spamfilter.example';
    }

    public function getOrder(): int
    {
        return 42;
    }

    public function getShortDescKey(): string
    {
        return 'spamfilter.short';
    }

    public function getSubCommandHelp(): array
    {
        return [
            ['name' => 'ADD', 'desc_key' => 'spamfilter.add.short', 'help_key' => 'spamfilter.add.help', 'syntax_key' => 'spamfilter.add.syntax', 'example_key' => 'spamfilter.add.example'],
            ['name' => 'DEL', 'desc_key' => 'spamfilter.del.short', 'help_key' => 'spamfilter.del.help', 'syntax_key' => 'spamfilter.del.syntax', 'example_key' => 'spamfilter.del.example'],
            ['name' => 'LIST', 'desc_key' => 'spamfilter.list.short', 'help_key' => 'spamfilter.list.help', 'syntax_key' => 'spamfilter.list.syntax', 'example_key' => 'spamfilter.list.example'],
        ];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return OperServPermission::SPAMFILTER;
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

        $sub = strtoupper($context->args[0] ?? '');
        $command = match ($sub) {
            'ADD' => $this->addCommand($context->sender->nick, array_slice($context->args, 1)),
            'DEL' => new ManageSpamfilter(SpamfilterAction::Delete, $context->sender->nick, new DateTimeImmutable(), item: trim($context->args[1] ?? '')),
            'LIST' => new ManageSpamfilter(SpamfilterAction::List, $context->sender->nick, new DateTimeImmutable(), listFilter: trim($context->args[1] ?? '')),
            default => new ManageSpamfilter(SpamfilterAction::Unknown, $context->sender->nick, new DateTimeImmutable()),
        };

        $this->present($context, $sub, $this->handler->handle($command));
    }

    /** @param list<string> $args [-regex] [+duration] <targets> <action> <pattern> <reason...> */
    private function addCommand(string $actor, array $args): ManageSpamfilter
    {
        $regex = false;
        $duration = null;
        while ([] !== $args && ('-regex' === strtolower($args[0]) || str_starts_with($args[0], '+'))) {
            $flag = (string) array_shift($args);
            if ('-regex' === strtolower($flag)) {
                $regex = true;
            } else {
                $duration = substr($flag, 1);
            }
        }

        return new ManageSpamfilter(
            SpamfilterAction::Add,
            $actor,
            new DateTimeImmutable(),
            targets: $args[0] ?? '',
            spamAction: $args[1] ?? '',
            pattern: $args[2] ?? '',
            reason: trim(implode(' ', array_slice($args, 3))),
            regex: $regex,
            duration: $duration,
        );
    }

    private function present(OperServContext $context, string $sub, ManageSpamfilterResult $result): void
    {
        $entry = $result->entry;
        match ($result->outcome) {
            ManageSpamfilterOutcome::Added => $context->reply('spamfilter.add.done', ['%pattern%' => $entry->match ?? '', '%action%' => SpamfilterPolicy::actionName($entry->action ?? '')]),
            ManageSpamfilterOutcome::AlreadyExists => $context->reply('spamfilter.add.exists', ['%pattern%' => $entry->match ?? '']),
            ManageSpamfilterOutcome::Deleted => $context->reply('spamfilter.del.done', ['%pattern%' => $entry->match ?? '']),
            ManageSpamfilterOutcome::NotFound => $context->reply('spamfilter.del.not_found', ['%item%' => $result->item ?? '']),
            ManageSpamfilterOutcome::Listed => $this->presentList($context, $result->entries),
            ManageSpamfilterOutcome::ListEmpty => $context->reply('spamfilter.list.empty'),
            ManageSpamfilterOutcome::InvalidRequest => $context->reply('error.syntax', ['%syntax%' => $context->trans('ADD' === $sub ? 'spamfilter.add.syntax' : 'spamfilter.del.syntax')]),
            ManageSpamfilterOutcome::InvalidTargets => $context->reply('spamfilter.add.invalid_targets', ['%valid%' => SpamfilterPolicy::TARGETS]),
            ManageSpamfilterOutcome::InvalidAction => $context->reply('spamfilter.add.invalid_action', ['%valid%' => implode(', ', SpamfilterPolicy::actionNames())]),
            ManageSpamfilterOutcome::InvalidRegex => $context->reply('spamfilter.add.invalid_regex'),
            ManageSpamfilterOutcome::InvalidDuration => $context->reply('spamfilter.add.invalid_duration'),
            ManageSpamfilterOutcome::Unavailable => $context->reply('spamfilter.unavailable'),
            ManageSpamfilterOutcome::UnknownAction => $context->reply('spamfilter.unknown_sub'),
        };
    }

    /** @param array<int, SpamfilterEntry> $entries */
    private function presentList(OperServContext $context, array $entries): void
    {
        $context->reply('spamfilter.list.header', ['%count%' => (string) count($entries)]);
        foreach ($entries as $number => $entry) {
            $context->reply('spamfilter.list.entry', [
                '%number%' => (string) $number,
                '%pattern%' => $entry->match,
                '%type%' => $entry->matchType,
                '%targets%' => $entry->targets,
                '%action%' => SpamfilterPolicy::actionName($entry->action),
                '%duration%' => SpamfilterPolicy::isTimedBan($entry->action) ? self::duration($entry->banDuration) : '-',
                '%reason%' => $entry->reason,
                '%by%' => $entry->setBy,
            ]);
        }
    }

    private static function duration(int $seconds): string
    {
        return match (true) {
            0 === $seconds % 86400 => ($seconds / 86400) . 'd',
            0 === $seconds % 3600 => ($seconds / 3600) . 'h',
            default => (int) ($seconds / 60) . 'm',
        };
    }
}
