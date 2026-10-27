<?php

declare(strict_types=1);

namespace App\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServCommandInterface;
use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Application\UseCase\KickUser\KickUser;
use App\ChanServ\Application\UseCase\KickUser\KickUserHandler;
use App\ChanServ\Application\UseCase\KickUser\KickUserOutcome;
use App\Irc\Application\Port\In\Command\IrcopAuditableCommandInterface;
use App\Irc\Application\Port\In\Command\IrcopAuditData;

use function array_slice;
use function implode;
use function trim;

final class KickCommand implements ChanServCommandInterface, IrcopAuditableCommandInterface
{
    use BuildsChannelLifecycleRequest;

    public function __construct(private KickUserHandler $handler) {}

    public function getName(): string
    {
        return 'KICK';
    }

    /** @return list<string> */
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
        return 'kick.syntax';
    }

    public function getHelpKey(): string
    {
        return 'kick.help';
    }

    public function getOrder(): int
    {
        return 67;
    }

    public function getShortDescKey(): string
    {
        return 'kick.short';
    }

    public function getExampleKey(): string
    {
        return 'kick.example';
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
        return false;
    }

    public function getRequiredPermission(): string
    {
        return 'IDENTIFIED';
    }

    public function usesLevelFounder(): bool
    {
        return true;
    }

    public function allowsSuspendedChannel(): bool
    {
        return false;
    }

    public function allowsForbiddenChannel(): bool
    {
        return false;
    }

    public function execute(ChanServContext $context): void
    {
        $channelName = $context->getChannelNameArg(0);
        if (null === $channelName) {
            $context->reply('error.invalid_channel');

            return;
        }

        $senderAccount = $context->senderAccount;
        if (null === $senderAccount) {
            $context->reply('error.not_identified');

            return;
        }

        $target = $context->args[1] ?? '';
        $reason = trim(implode(' ', array_slice($context->args, 2)));

        $actorNickname = null === $context->sender ? '' : $context->sender->nick;

        $result = $this->handler->handle(new KickUser(
            channelName: $channelName,
            targetNickname: $target,
            actorNickname: $actorNickname,
            reason: '' === $reason ? $context->trans('kick.default_reason') : $reason,
        ));

        match ($result->outcome) {
            KickUserOutcome::Kicked => $context->reply('kick.done', [
                'channel' => $result->channelName ?? $channelName,
                'nick' => $result->targetNickname ?? $target,
            ]),
            KickUserOutcome::ChannelNotRegistered => $context->reply('error.channel_not_registered', ['channel' => $channelName]),
            KickUserOutcome::ChannelNotOnNetwork => $context->reply('error.channel_not_on_network', ['channel' => $channelName]),
            KickUserOutcome::TargetNotConnected => $context->reply('kick.not_connected', ['nick' => $target]),
        };
    }

    public function auditData(ChanServContext $context): IrcopAuditData
    {
        return new IrcopAuditData(target: $context->getChannelNameArg(0) ?? '*', reason: implode(' ', array_slice($context->args, 2)));
    }
}
