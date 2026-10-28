<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Application\Model\BroadcastOutcome;
use App\GlobalServ\Application\Model\BroadcastResult;
use App\GlobalServ\Application\UseCase\SendRank\SendRankBroadcast;
use App\GlobalServ\Application\UseCase\SendRank\SendRankBroadcastHandler;
use App\GlobalServ\Domain\ValueObject\GlobalServRank;
use App\Irc\Application\Port\In\Command\CommandOutcome;

use function implode;

/**
 * IRC parsing and presentation adapter for the rank-filtered announcements:
 * PRE-OPER, OPER and ADMIN.
 *
 * The three commands share the same behaviour and differ only in the staff rank
 * they reach, the permission they require and the translation keys they use, so
 * one module is wired four times instead of copying the adapter.
 */
final readonly class RankBroadcastCommand implements GlobalServCommandInterface
{
    public function __construct(
        private SendRankBroadcastHandler $handler,
        private string $name,
        private GlobalServRank $rank,
        private string $permission,
        private string $replyKey,
        private int $order,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    /** @return list<string> */
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
        return strtolower($this->name) . '.syntax';
    }

    public function getHelpKey(): string
    {
        return strtolower($this->name) . '.help';
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getShortDescKey(): string
    {
        return strtolower($this->name) . '.short';
    }

    /**
     * @return array<int, array{name: string, desc_key: string, help_key: string, syntax_key: string}>
     */
    public function getSubCommandHelp(): array
    {
        return [];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return $this->permission;
    }

    public function execute(GlobalServContext $context): ?CommandOutcome
    {
        if (null === $context->sender) {
            return null;
        }

        $result = $this->handler->handle(new SendRankBroadcast(
            actorNickname: $context->sender->nick,
            rank: $this->rank,
            message: $context->trans('global.prefix') . "\n" . implode(' ', $context->args),
        ));

        $this->present($context, $result);

        return null;
    }

    private function present(GlobalServContext $context, BroadcastResult $result): void
    {
        if (BroadcastOutcome::Sent === $result->outcome) {
            $context->reply($this->replyKey, ['count' => (string) ($result->recipientCount ?? 0)]);

            return;
        }

        if (BroadcastOutcome::RankUnsupported === $result->outcome) {
            $context->reply('rank.unsupported');

            return;
        }

        $context->reply('error.network_unavailable');
    }
}
