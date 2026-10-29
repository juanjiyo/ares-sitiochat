<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\UseCase\NetworkInfo\CountOnlineUsersHandler;

/** IRC adapter for STATS: number of users online. Available to every IRC operator. */
final readonly class StatsCommand implements OperServCommandInterface
{
    public function __construct(private CountOnlineUsersHandler $handler) {}

    public function getName(): string
    {
        return 'STATS';
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
        return 'stats.syntax';
    }

    public function getHelpKey(): string
    {
        return 'stats.help';
    }

    public function getExampleKey(): string
    {
        return 'stats.example';
    }

    public function getOrder(): int
    {
        return 30;
    }

    public function getShortDescKey(): string
    {
        return 'stats.short';
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
        $context->reply('stats.users', ['count' => (string) $this->handler->handle()]);
    }
}
