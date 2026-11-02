<?php

declare(strict_types=1);

namespace App\NickServ\Adapter\In\Irc\Command;

use App\Irc\Application\Port\In\Command\CommandOutcome;
use App\Irc\Application\Port\In\Command\IrcopAuditableCommandInterface;
use App\Irc\Application\Port\In\Command\IrcopAuditData;
use App\NickServ\Adapter\In\Irc\NickServCommandInterface;
use App\NickServ\Adapter\In\Irc\NickServContext;
use App\NickServ\Application\Security\NickServPermission;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModes;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesHandler;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesHandlerInterface;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesOutcome;

use function implode;

/** AUTOMODE <nick> [+modes]: user modes the IRCd applies to the nick on every identification (bare form clears them). */
final readonly class AutomodeCommand implements NickServCommandInterface, IrcopAuditableCommandInterface
{
    public function __construct(private SetAutoModesHandlerInterface $handler) {}

    public function getName(): string
    {
        return 'AUTOMODE';
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
        return 'automode.syntax';
    }

    public function getHelpKey(): string
    {
        return 'automode.help';
    }

    public function getOrder(): int
    {
        return 76;
    }

    public function getShortDescKey(): string
    {
        return 'automode.short';
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
        return NickServPermission::AUTOMODE;
    }

    public function getExampleKey(): string
    {
        return 'automode.example';
    }

    public function getHelpParams(): array
    {
        return [];
    }

    public function execute(NickServContext $context): CommandOutcome
    {
        if (null === $context->sender) {
            return CommandOutcome::rejected();
        }

        if ([] === $context->args) {
            $context->reply('error.syntax', ['syntax' => $context->trans($this->getSyntaxKey())]);

            return CommandOutcome::rejected();
        }

        $result = $this->handler->handle(new SetAutoModes($context->args[0], $context->args[1] ?? ''));

        switch ($result->outcome) {
            case SetAutoModesOutcome::Set:
                $context->reply('automode.set', ['nickname' => $result->nickname, 'modes' => (string) $result->modes]);
                break;
            case SetAutoModesOutcome::Cleared:
                $context->reply('automode.cleared', ['nickname' => $result->nickname]);
                break;
            case SetAutoModesOutcome::InvalidModes:
                $context->reply('automode.invalid_modes', [
                    'modes' => (string) $result->invalidModes,
                    'allowed' => implode('', SetAutoModesHandler::ALLOWED_MODES),
                ]);

                return CommandOutcome::rejected();
            case SetAutoModesOutcome::NotRegistered:
                $context->reply('automode.not_registered', ['nickname' => $result->nickname]);

                return CommandOutcome::rejected();
            default:
                $context->reply('automode.cannot_modify_oper');

                return CommandOutcome::rejected();
        }

        return CommandOutcome::success(new IrcopAuditData(
            target: $result->nickname,
            extra: ['modes' => $result->modes],
        ));
    }
}
