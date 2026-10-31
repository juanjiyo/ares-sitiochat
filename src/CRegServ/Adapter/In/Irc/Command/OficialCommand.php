<?php

declare(strict_types=1);

namespace App\CRegServ\Adapter\In\Irc\Command;

use App\ChanServ\Application\Port\In\ChannelAdministration;
use App\ChanServ\Application\Port\In\OfficialRegistrationOutcome;
use App\CRegServ\Adapter\In\Irc\CRegServCommandInterface;
use App\CRegServ\Adapter\In\Irc\CRegServContext;
use App\CRegServ\Application\Security\CRegServPermission;
use App\Irc\Application\Port\In\Command\CommandOutcome;
use App\Irc\Application\Port\In\Command\IrcopAuditData;
use DateTimeImmutable;

use function array_slice;
use function implode;
use function str_starts_with;

/**
 * OFICIAL <#channel> <founder> [description]; IRC parsing and presentation only.
 *
 * Registers one of the network's own channels in the name of the given founder (for
 * example the network nick, which founds many of them), without the per-nick channel
 * limit, and marks it as official.
 */
final readonly class OficialCommand implements CRegServCommandInterface
{
    public function __construct(private ChannelAdministration $channels) {}

    public function getName(): string
    {
        return 'OFICIAL';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getMinArgs(): int
    {
        return 2;
    }

    public function getSyntaxKey(): string
    {
        return 'oficial.syntax';
    }

    public function getHelpKey(): string
    {
        return 'oficial.help';
    }

    public function getOrder(): int
    {
        return 9;
    }

    public function getShortDescKey(): string
    {
        return 'oficial.short';
    }

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
        return CRegServPermission::OFICIAL;
    }

    public function getExampleKey(): string
    {
        return 'oficial.example';
    }

    public function execute(CRegServContext $context): ?CommandOutcome
    {
        [$channelName, $founder] = $context->args;
        if (!str_starts_with($channelName, '#')) {
            $context->reply('oficial.invalid_channel', ['%channel%' => $channelName]);

            return null;
        }

        $outcome = $this->channels->registerOfficial(
            $channelName,
            $founder,
            implode(' ', array_slice($context->args, 2)),
            $context->getSenderNickname() ?? '',
            new DateTimeImmutable(),
        );

        $context->reply(match ($outcome) {
            OfficialRegistrationOutcome::Registered => 'oficial.done',
            OfficialRegistrationOutcome::AlreadyRegistered => 'oficial.already_registered',
            OfficialRegistrationOutcome::PendingDeletion => 'oficial.pending_deletion',
            OfficialRegistrationOutcome::Failed => 'oficial.failed',
            OfficialRegistrationOutcome::FounderNotRegistered => 'oficial.founder_not_registered',
            OfficialRegistrationOutcome::FounderSuspended => 'oficial.founder_suspended',
        }, ['%channel%' => $channelName, '%nickname%' => $founder]);

        return OfficialRegistrationOutcome::Registered === $outcome
            ? CommandOutcome::success(new IrcopAuditData(target: $channelName, extra: ['founder' => $founder]))
            : null;
    }
}
