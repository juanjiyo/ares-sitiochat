<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\Clones\BanCloneHostHandler;
use App\OperServ\Application\UseCase\Clones\CloneHostBan;

/** IRC adapter for KILLCLONES <nick> [reason]: G-Lines the host of the nick for one hour. */
final readonly class KillclonesCommand implements OperServCommandInterface
{
    public function __construct(private BanCloneHostHandler $handler) {}

    public function getName(): string
    {
        return 'KILLCLONES';
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
        return 'killclones.syntax';
    }

    public function getHelpKey(): string
    {
        return 'killclones.help';
    }

    public function getExampleKey(): string
    {
        return 'killclones.example';
    }

    public function getOrder(): int
    {
        return 21;
    }

    public function getShortDescKey(): string
    {
        return 'killclones.short';
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
        CloneHostBanPresenter::run($context, $this->handler, CloneHostBan::KillClones, 'killclones.done');
    }
}
