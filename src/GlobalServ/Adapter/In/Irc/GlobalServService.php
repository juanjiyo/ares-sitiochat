<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

use App\GlobalServ\Application\Port\Out\GlobalServNickAccountPort;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function count;
use function is_string;
use function preg_split;
use function sprintf;
use function strtoupper;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * Routes a raw command string (from a PRIVMSG to GlobalServ) to the correct
 * command handler. Owns parsing, authorization presentation and output only.
 */
final readonly class GlobalServService
{
    public function __construct(
        private GlobalServCommandRegistry $commandRegistry,
        private GlobalServNotifierInterface $notifier,
        private TranslatorInterface $translator,
        private ServiceNicknameRegistry $serviceNicks,
        private GlobalServAuthorizationContextInterface $authorizationContext,
        private GlobalServAuthorizationCheckerInterface $authorizationChecker,
        private GlobalServNickAccountPort $userAccountPort,
        private string $defaultLanguage = 'en',
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @param string     $rawText Full text of the PRIVMSG (e.g. "GLOBAL hello")
     * @param SenderView $sender  The user who sent the message (from NetworkUserLookupPort)
     */
    public function dispatch(string $rawText, SenderView $sender): void
    {
        $parts = preg_split('/\s+/', trim($rawText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $cmdPart = array_shift($parts);
        $cmdName = strtoupper(is_string($cmdPart) ? $cmdPart : '');
        /** @var list<string> $args */
        $args = $parts;

        if ('' === $cmdName) {
            return;
        }

        $context = new GlobalServContext(
            sender: $sender,
            senderAccountId: $this->senderAccountId($sender->nick),
            command: $cmdName,
            args: $args,
            notifier: $this->notifier,
            translator: $this->translator,
            language: $this->defaultLanguage,
            registry: $this->commandRegistry,
            serviceNicks: $this->serviceNicks,
        );

        $handler = $this->commandRegistry->find($cmdName);

        if (null === $handler) {
            $context->reply('unknown_command', ['command' => $cmdName]);

            return;
        }

        $this->authorizationContext->setCurrentUser($sender->uid, $sender->isIdentified, $sender->isOper);
        $this->dispatchToHandler($context, $handler, $sender, $cmdName);
    }

    private function senderAccountId(string $nickname): ?int
    {
        return $this->userAccountPort->findAccountIdByNick($nickname);
    }

    private function dispatchToHandler(GlobalServContext $context, GlobalServCommandInterface $handler, SenderView $sender, string $cmdName): void
    {
        try {
            if ($handler->isOperOnly() && !$sender->isOper) {
                $context->reply('error.permission_denied');

                return;
            }

            $requiredPermission = $handler->getRequiredPermission();
            if (null !== $requiredPermission && !$this->authorizationChecker->isGranted($requiredPermission, $context)) {
                $context->reply('error.permission_denied');

                return;
            }

            if (count($context->args) < $handler->getMinArgs()) {
                $context->reply('error.syntax', [
                    'syntax' => $context->trans($handler->getSyntaxKey()),
                ]);

                return;
            }

            $this->logger->debug(sprintf(
                'GlobalServ: %s executed %s [args: %d]',
                $sender->nick,
                $cmdName,
                count($context->args),
            ));

            $handler->execute($context);
        } catch (Throwable $e) {
            $this->logger->error('GlobalServ dispatch error: ' . $e->getMessage(), [
                'exception' => $e,
                'sender' => $sender->uid,
            ]);
            throw $e;
        } finally {
            $this->authorizationContext->clear();
        }
    }
}
