<?php

declare(strict_types=1);

namespace App\CRegServ\Adapter\In\Irc\Command;

use App\ChanServ\Application\Port\In\ChannelAdministration;
use App\ChanServ\Application\Port\In\ChannelAdministrationOutcome;
use App\CRegServ\Adapter\In\Irc\CRegServCommandInterface;
use App\CRegServ\Adapter\In\Irc\CRegServContext;
use App\CRegServ\Application\Security\CRegServPermission;
use App\Irc\Application\Port\In\Command\CommandOutcome;
use App\Irc\Application\Port\In\Command\IrcopAuditData;
use DateTimeImmutable;

/**
 * APODERA <#channel> [nickname]; IRC parsing and presentation only.
 *
 * The administration takes an Interés General channel over: its founder becomes the
 * given nickname or, without one, the operator. For interventions, or when the
 * founder's nickname expired. Only IG channels can be taken.
 */
final readonly class ApoderaCommand implements CRegServCommandInterface
{
    public function __construct(private ChannelAdministration $channels) {}

    public function getName(): string
    {
        return 'APODERA';
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
        return 'apodera.syntax';
    }

    public function getHelpKey(): string
    {
        return 'apodera.help';
    }

    public function getOrder(): int
    {
        return 21;
    }

    public function getShortDescKey(): string
    {
        return 'apodera.short';
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
        return CRegServPermission::APODERA;
    }

    public function getExampleKey(): string
    {
        return 'apodera.example';
    }

    public function execute(CRegServContext $context): ?CommandOutcome
    {
        $actor = $context->getSenderNickname() ?? '';
        $channelName = $context->args[0];
        $founder = $context->args[1] ?? $actor;
        $params = ['%channel%' => $channelName, '%nickname%' => $founder];

        $outcome = $this->channels->takeOver($channelName, $founder, $actor, new DateTimeImmutable());

        $context->reply(match ($outcome) {
            ChannelAdministrationOutcome::Updated => 'apodera.done',
            ChannelAdministrationOutcome::Unchanged => 'apodera.already_founder',
            ChannelAdministrationOutcome::NotRegistered => 'apodera.not_registered',
            ChannelAdministrationOutcome::NotInterestGeneral => 'apodera.not_ig',
            ChannelAdministrationOutcome::FounderSuspended => 'apodera.founder_suspended',
            ChannelAdministrationOutcome::FounderNotRegistered => 'apodera.founder_not_registered',
        }, $params);

        return ChannelAdministrationOutcome::Updated === $outcome
            ? CommandOutcome::success(new IrcopAuditData(target: $channelName, extra: ['founder' => $founder]))
            : null;
    }
}
