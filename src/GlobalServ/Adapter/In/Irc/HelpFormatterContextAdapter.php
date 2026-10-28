<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

use App\GlobalServ\Adapter\In\Irc\Help\HelpableCommandInterface;
use App\GlobalServ\Adapter\In\Irc\Help\HelpFormatterContextInterface;
use App\GlobalServ\Application\Security\GlobalServPermission;

use function str_starts_with;

/**
 * Adapter from GlobalServContext to HelpFormatterContextInterface for UnifiedHelpFormatter.
 */
final readonly class HelpFormatterContextAdapter implements HelpFormatterContextInterface
{
    public function __construct(
        private GlobalServContext $context,
        private GlobalServAuthorizationCheckerInterface $authorizationChecker,
    ) {}

    /**
     * @param array<string, mixed> $params
     */
    public function reply(string $key, array $params = []): void
    {
        $this->context->reply($key, $params);
    }

    public function replyRaw(string $message): void
    {
        $this->context->replyRaw($message);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function trans(string $key, array $params = []): string
    {
        return $this->context->trans($key, $params);
    }

    /**
     * @return iterable<HelpableCommandInterface>
     */
    public function getCommandsForGeneralHelp(): iterable
    {
        return $this->context->getRegistry()->all();
    }

    public function shouldShowCommandInGeneralHelp(HelpableCommandInterface $command): bool
    {
        $permission = $command instanceof GlobalServCommandInterface ? $command->getRequiredPermission() : null;
        if (null !== $permission && self::isGlobalServIrcopPermission($permission)) {
            return false;
        }

        if ($command->isOperOnly()) {
            return $this->context->isSenderIrcOperator();
        }

        return true;
    }

    public function canViewCommandInHelp(HelpableCommandInterface $command): bool
    {
        if ($this->shouldShowCommandInGeneralHelp($command)) {
            return true;
        }

        foreach ($this->getIrcopCommands() as $ircopCommand) {
            if ($ircopCommand->getName() === $command->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return iterable<HelpableCommandInterface>
     */
    public function getIrcopCommands(): iterable
    {
        foreach ($this->context->getRegistry()->all() as $command) {
            $permission = $command->getRequiredPermission();
            if (null !== $permission
                && self::isGlobalServIrcopPermission($permission)
                && $this->authorizationChecker->isGranted($permission, $this->context)) {
                yield $command;
            }
        }
    }

    public function hasIrcopAccess(): bool
    {
        foreach (GlobalServPermission::allIrcop() as $permission) {
            if ($this->authorizationChecker->isGranted($permission, $this->context)) {
                return true;
            }
        }

        return false;
    }

    private static function isGlobalServIrcopPermission(string $permission): bool
    {
        return str_starts_with($permission, 'globalserv.');
    }
}
