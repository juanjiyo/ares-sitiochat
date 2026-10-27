<?php

declare(strict_types=1);

namespace App\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServCommandInterface;
use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\Security\ChanServPermission;
use App\Irc\Application\Port\In\Command\CommandOutcome;
use App\Irc\Application\Port\In\Command\IrcopAuditableCommandInterface;
use App\Irc\Application\Port\In\Command\IrcopAuditData;

use function count;

/**
 * LISTFORBID — lists every forbidden channel with its reason.
 *
 * Mirrors the ch.mrc listing so opers can audit which names are blocked.
 */
final readonly class ListforbidCommand implements ChanServCommandInterface, IrcopAuditableCommandInterface
{
    public function __construct(private RegisteredChannelRepositoryInterface $channels) {}

    public function getName(): string
    {
        return 'LISTFORBID';
    }

    /** @return list<string> */
    public function getAliases(): array
    {
        return [];
    }

    public function getMinArgs(): int
    {
        return 0;
    }

    public function getSyntaxKey(): string
    {
        return 'listforbid.syntax';
    }

    public function getHelpKey(): string
    {
        return 'listforbid.help';
    }

    public function getOrder(): int
    {
        return 81;
    }

    public function getShortDescKey(): string
    {
        return 'listforbid.short';
    }

    public function getExampleKey(): string
    {
        return 'listforbid.example';
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
        return ChanServPermission::LISTFORBID;
    }

    public function usesLevelFounder(): bool
    {
        return false;
    }

    public function allowsSuspendedChannel(): bool
    {
        return true;
    }

    public function allowsForbiddenChannel(): bool
    {
        return true;
    }

    public function execute(ChanServContext $context): ?CommandOutcome
    {
        $forbidden = $this->channels->findForbiddenChannels();

        if ([] === $forbidden) {
            $context->reply('listforbid.empty');

            return null;
        }

        $context->reply('listforbid.header', ['count' => (string) count($forbidden)]);
        foreach ($forbidden as $channel) {
            $context->reply('listforbid.line', [
                'channel' => $channel->getName(),
                'reason' => $channel->getForbiddenReason() ?? '-',
            ]);
        }

        return null;
    }

    public function auditData(ChanServContext $context): IrcopAuditData
    {
        return new IrcopAuditData(target: '*');
    }
}
