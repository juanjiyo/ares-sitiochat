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
 * LISTSUSPEND — lists every suspended channel with its reason and expiry.
 *
 * Mirrors the ch.mrc listing so opers can audit active suspensions.
 */
final readonly class ListsuspendCommand implements ChanServCommandInterface, IrcopAuditableCommandInterface
{
    public function __construct(private RegisteredChannelRepositoryInterface $channels) {}

    public function getName(): string
    {
        return 'LISTSUSPEND';
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
        return 'listsuspend.syntax';
    }

    public function getHelpKey(): string
    {
        return 'listsuspend.help';
    }

    public function getOrder(): int
    {
        return 80;
    }

    public function getShortDescKey(): string
    {
        return 'listsuspend.short';
    }

    public function getExampleKey(): string
    {
        return 'listsuspend.example';
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
        return ChanServPermission::LISTSUSPEND;
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
        $suspended = $this->channels->findSuspendedChannels();

        if ([] === $suspended) {
            $context->reply('listsuspend.empty');

            return null;
        }

        $context->reply('listsuspend.header', ['count' => (string) count($suspended)]);
        foreach ($suspended as $channel) {
            $until = $channel->getSuspendedUntil();
            $context->reply('listsuspend.line', [
                'channel' => $channel->getName(),
                'reason' => $channel->getSuspendedReason() ?? '-',
                'until' => null === $until ? '-' : $until->format('Y-m-d H:i'),
            ]);
        }

        return null;
    }

    public function auditData(ChanServContext $context): IrcopAuditData
    {
        return new IrcopAuditData(target: '*');
    }
}
