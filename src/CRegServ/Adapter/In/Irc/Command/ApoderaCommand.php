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

use function array_slice;
use function strtoupper;

/**
 * APODERA [IG] <#channel> [nickname]; IRC parsing and presentation only.
 *
 * The administration takes a channel over: its founder becomes the given nickname or,
 * without one, the operator, and every user but the services loses +q, +a, +o and +h.
 * For interventions, or when the founder's nickname expired. Plain APODERA is for
 * channels that are not Interés General; APODERA IG is for the IG ones.
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
        $interestGeneral = 'IG' === strtoupper($context->args[0]);
        $args = $interestGeneral ? array_slice($context->args, 1) : $context->args;
        if (!isset($args[0])) {
            $context->reply('error.syntax', ['%syntax%' => $context->trans('apodera.syntax')]);

            return null;
        }

        $channelName = $args[0];
        $founder = $args[1] ?? $actor;
        $params = ['%channel%' => $channelName, '%nickname%' => $founder];

        $outcome = $this->channels->takeOver($channelName, $interestGeneral, $founder, $actor, new DateTimeImmutable());

        $context->reply(match ($outcome) {
            ChannelAdministrationOutcome::Updated => 'apodera.done',
            ChannelAdministrationOutcome::Unchanged => 'apodera.already_founder',
            ChannelAdministrationOutcome::NotRegistered => 'apodera.not_registered',
            ChannelAdministrationOutcome::NotInterestGeneral => 'apodera.not_ig',
            ChannelAdministrationOutcome::InterestGeneral => 'apodera.is_ig',
            ChannelAdministrationOutcome::FounderSuspended => 'apodera.founder_suspended',
            ChannelAdministrationOutcome::FounderNotRegistered => 'apodera.founder_not_registered',
        }, $params);

        return ChannelAdministrationOutcome::Updated === $outcome
            ? CommandOutcome::success(new IrcopAuditData(target: $channelName, extra: ['founder' => $founder]))
            : null;
    }
}
