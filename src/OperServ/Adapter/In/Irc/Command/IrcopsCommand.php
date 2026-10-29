<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\UseCase\NetworkInfo\ListOnlineOperatorsHandler;

use function count;

/** IRC adapter for IRCOPS: IRC operators online, by nickname. Available to every IRC operator. */
final readonly class IrcopsCommand implements OperServCommandInterface
{
    public function __construct(private ListOnlineOperatorsHandler $handler) {}

    public function getName(): string
    {
        return 'IRCOPS';
    }

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
        return 'ircops.syntax';
    }

    public function getHelpKey(): string
    {
        return 'ircops.help';
    }

    public function getExampleKey(): string
    {
        return 'ircops.example';
    }

    public function getOrder(): int
    {
        return 31;
    }

    public function getShortDescKey(): string
    {
        return 'ircops.short';
    }

    public function getSubCommandHelp(): array
    {
        return [];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): ?string
    {
        return null;
    }

    /** @return array<string, string> */
    public function getHelpParams(): array
    {
        return [];
    }

    public function execute(OperServContext $context): void
    {
        $operators = $this->handler->handle();
        if ([] === $operators) {
            $context->reply('ircops.empty');

            return;
        }

        $context->reply('ircops.header', ['count' => (string) count($operators)]);
        foreach ($operators as $nickname) {
            $context->reply('ircops.entry', ['nickname' => $nickname]);
        }
        $context->reply('ircops.footer');
    }
}
