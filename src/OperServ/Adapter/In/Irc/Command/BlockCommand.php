<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\Clones\BanCloneHostHandler;
use App\OperServ\Application\UseCase\Clones\CloneHostBan;

/** IRC adapter for BLOCK <nick> [reason]: G-Lines the host of the nick for five hours. */
final readonly class BlockCommand implements OperServCommandInterface
{
    public function __construct(private BanCloneHostHandler $handler) {}

    public function getName(): string
    {
        return 'BLOCK';
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
        return 'block.syntax';
    }

    public function getHelpKey(): string
    {
        return 'block.help';
    }

    public function getExampleKey(): string
    {
        return 'block.example';
    }

    public function getOrder(): int
    {
        return 22;
    }

    public function getShortDescKey(): string
    {
        return 'block.short';
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
        CloneHostBanPresenter::run($context, $this->handler, CloneHostBan::Block, 'block.done');
    }
}
