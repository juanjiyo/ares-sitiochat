<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc\Subscriber;

use App\GlobalServ\Adapter\In\Irc\Bot\GlobalServBot;
use App\GlobalServ\Adapter\In\Irc\GlobalServService;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ServiceCommandListenerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Registers GlobalServ with the Service Command Gateway. Receives (senderUid, text)
 * and delegates to GlobalServService with SenderView from NetworkUserLookupPort.
 */
final readonly class GlobalServCommandListener implements ServiceCommandListenerInterface
{
    public function __construct(
        private GlobalServBot $globalServBot,
        private GlobalServService $globalServService,
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
            $this->logger->warning('GlobalServ: could not resolve sender UID: ' . $senderUid);

            return;
        }

        try {
            $this->globalServService->dispatch($text, $sender);
        } catch (Throwable $e) {
            $this->logger->error('GlobalServ dispatch error: ' . $e->getMessage(), [
                'exception' => $e,
                'sender' => $sender->uid,
            ]);
        }
    }
}
