<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\Clones\ListClones;
use App\OperServ\Application\UseCase\Clones\ListClonesHandler;

/** IRC adapter for LISTCLONES <nick>: host of the nick and the other nicks connected from it. */
final readonly class ListclonesCommand implements OperServCommandInterface
{
    public function __construct(private ListClonesHandler $handler) {}

    public function getName(): string
    {
        return 'LISTCLONES';
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
        return 'listclones.syntax';
    }

    public function getHelpKey(): string
    {
        return 'listclones.help';
    }

    public function getExampleKey(): string
    {
        return 'listclones.example';
    }

    public function getOrder(): int
    {
        return 32;
    }

    public function getShortDescKey(): string
    {
        return 'listclones.short';
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
        return OperServPermission::CLONES;
    }

    /** @return array<string, string> */
    public function getHelpParams(): array
    {
        return [];
    }

    public function execute(OperServContext $context): void
    {
        $result = $this->handler->handle(new ListClones($context->args[0]));
        if (!$result->found) {
            $context->reply('channel.user_not_online', ['nickname' => $result->nickname]);

            return;
        }

        if ([] === $result->clones) {
            $context->reply('listclones.none', ['nickname' => $result->nickname, 'host' => $result->host]);

            return;
        }

        $context->reply('listclones.header', ['nickname' => $result->nickname, 'host' => $result->host, 'count' => (string) $result->sessionCount]);
        foreach ($result->clones as $clone) {
            $context->reply('listclones.entry', ['nickname' => $clone->nickname, 'ident' => $clone->ident]);
        }
        $context->reply('listclones.footer');
    }
}
