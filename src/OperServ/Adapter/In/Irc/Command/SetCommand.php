<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\SetPassProtect\SetPassProtect;
use App\OperServ\Application\UseCase\SetPassProtect\SetPassProtectHandler;
use App\OperServ\Application\UseCase\SetPassProtect\SetPassProtectOutcome;
use DateTimeImmutable;

use function strtoupper;
use function trim;

/** IRC adapter for SET: network-wide settings (operserv.set, granted to the ROOT rank). */
final readonly class SetCommand implements OperServCommandInterface
{
    public function __construct(private SetPassProtectHandler $passProtect) {}

    public function getName(): string
    {
        return 'SET';
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
        return 'set.syntax';
    }

    public function getHelpKey(): string
    {
        return 'set.help';
    }

    public function getExampleKey(): string
    {
        return 'set.example';
    }

    public function getOrder(): int
    {
        return 41;
    }

    public function getShortDescKey(): string
    {
        return 'set.short';
    }

    public function getSubCommandHelp(): array
    {
        return [
            ['name' => 'PASSPROTECT', 'desc_key' => 'set.passprotect.short', 'help_key' => 'set.passprotect.help', 'syntax_key' => 'set.passprotect.syntax', 'example_key' => 'set.passprotect.example'],
        ];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return OperServPermission::SET;
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

        if ('PASSPROTECT' !== strtoupper($context->args[0] ?? '')) {
            $context->reply('set.unknown_sub');

            return;
        }

        $value = trim($context->args[1] ?? '');
        if ('' === $value) {
            $context->reply('error.syntax', ['%syntax%' => $context->trans('set.passprotect.syntax')]);

            return;
        }

        $outcome = $this->passProtect->handle(new SetPassProtect($context->sender->nick, new DateTimeImmutable(), $value));
        match ($outcome) {
            SetPassProtectOutcome::Set => $context->reply('set.passprotect.done', ['%value%' => $value]),
            SetPassProtectOutcome::InvalidValue => $context->reply('set.passprotect.invalid'),
            SetPassProtectOutcome::Rejected => $context->reply('set.passprotect.rejected'),
        };
    }
}
