<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\Jail\JailUser;
use App\OperServ\Application\UseCase\Jail\JailUserHandler;
use DateTimeImmutable;

/** IRC parsing adapter for UNJAIL <nickname>. */
final readonly class UnjailCommand implements OperServCommandInterface
{
    public function __construct(private JailUserHandler $handler) {}

    public function getName(): string
    {
        return 'UNJAIL';
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
        return 'unjail.syntax';
    }

    public function getHelpKey(): string
    {
        return 'unjail.help';
    }

    public function getExampleKey(): string
    {
        return 'unjail.example';
    }

    public function getOrder(): int
    {
        return 27;
    }

    public function getShortDescKey(): string
    {
        return 'unjail.short';
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
        return OperServPermission::JAIL;
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

        JailPresenter::present($context, $this->handler->release(new JailUser(
            actorNickname: $context->sender->nick,
            targetNickname: $context->args[0],
            occurredAt: new DateTimeImmutable(),
        )));
    }
}
