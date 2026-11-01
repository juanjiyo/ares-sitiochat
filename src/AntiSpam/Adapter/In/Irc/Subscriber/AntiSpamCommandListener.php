<?php

declare(strict_types=1);

namespace App\AntiSpam\Adapter\In\Irc\Subscriber;

use App\AntiSpam\Adapter\In\Irc\Bot\AntiSpamBot;
use App\AntiSpam\Adapter\In\Irc\AntiSpamService;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ServiceCommandListenerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Registers AntiSpam with the Service Command Gateway. Receives (senderUid, text)
 * and delegates to AntiSpamService with SenderView from NetworkUserLookupPort.
 */
final readonly class AntiSpamCommandListener implements ServiceCommandListenerInterface
{
    public function __construct(
        private AntiSpamBot $globalServBot,
        private AntiSpamService $globalServService,
        private NetworkUserLookupPort $userLookup,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getServiceName(): string
    {
        return $this->globalServBot->getNick();
    }

    public function getServiceUid(): string
    {
        return $this->globalServBot->getUid();
    }

    public function onCommand(string $senderUid, string $text): void
    {
        if ('' === $text) {
            return;
        }

        $sender = $this->userLookup->findByUid($senderUid);

        if (null === $sender) {
            $this->logger->warning('AntiSpam: could not resolve sender UID: ' . $senderUid);

            return;
        }

        try {
            $this->globalServService->dispatch($text, $sender);
        } catch (Throwable $e) {
            $this->logger->error('AntiSpam dispatch error: ' . $e->getMessage(), [
                'exception' => $e,
                'sender' => $sender->uid,
            ]);
        }
    }
}
