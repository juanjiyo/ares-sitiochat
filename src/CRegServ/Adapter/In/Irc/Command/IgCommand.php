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

use function implode;
use function strtoupper;

/**
 * IG {ADD|DEL} <#channel> | IG LIST; IRC parsing and presentation only.
 *
 * The registration is the normal one: IG only marks the channel in ChanServ as
 * Interés General, which is what lets the administration take it over with APODERA.
 */
final readonly class IgCommand implements CRegServCommandInterface
{
    public function __construct(private ChannelAdministration $channels) {}

    public function getName(): string
    {
        return 'IG';
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
        return 'ig.syntax';
    }

    public function getHelpKey(): string
    {
        return 'ig.help';
    }

    public function getOrder(): int
    {
        return 20;
    }

    public function getShortDescKey(): string
    {
        return 'ig.short';
    }

    public function getSubCommandHelp(): array
    {
        return [
            ['name' => 'ADD', 'desc_key' => 'ig.add.short', 'help_key' => 'ig.add.help', 'syntax_key' => 'ig.add.syntax'],
            ['name' => 'DEL', 'desc_key' => 'ig.del.short', 'help_key' => 'ig.del.help', 'syntax_key' => 'ig.del.syntax'],
            ['name' => 'LIST', 'desc_key' => 'ig.list.short', 'help_key' => 'ig.list.help', 'syntax_key' => 'ig.list.syntax'],
        ];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return CRegServPermission::IG;
    }

    public function getExampleKey(): string
    {
        return 'ig.example';
    }

    public function execute(CRegServContext $context): ?CommandOutcome
    {
        $action = strtoupper($context->args[0]);

        if ('LIST' === $action) {
            $channels = $this->channels->interestGeneralChannels();
            $context->reply([] === $channels ? 'ig.list.empty' : 'ig.list.channels', ['%channels%' => implode(', ', $channels)]);

            return null;
        }

        $channelName = $context->args[1] ?? '';
        if (('ADD' !== $action && 'DEL' !== $action) || '' === $channelName) {
            $context->reply('error.syntax', ['%syntax%' => $context->trans('ig.syntax')]);

            return null;
        }

        $add = 'ADD' === $action;
        $params = ['%channel%' => $channelName];

        return match ($this->channels->markInterestGeneral($channelName, $add)) {
            ChannelAdministrationOutcome::Updated => $this->updated($context, $add ? 'ig.add.done' : 'ig.del.done', $params, $action),
            ChannelAdministrationOutcome::NotRegistered => $this->refused($context, 'ig.not_registered', $params),
            default => $this->refused($context, $add ? 'ig.add.already' : 'ig.del.not_marked', $params),
        };
    }

    /** @param array<string, string> $params */
    private function updated(CRegServContext $context, string $key, array $params, string $action): CommandOutcome
    {
        $context->reply($key, $params);

        return CommandOutcome::success(new IrcopAuditData(target: $params['%channel%'], extra: ['action' => $action]));
    }

    /** @param array<string, string> $params */
    private function refused(CRegServContext $context, string $key, array $params): null
    {
        $context->reply($key, $params);

        return null;
    }
}
