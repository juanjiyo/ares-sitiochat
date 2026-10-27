<?php

declare(strict_types=1);

namespace App\HelpServ\Adapter\In\Irc\Subscriber;

use App\HelpServ\Adapter\In\Irc\Bot\HelpServBot;
use App\HelpServ\Adapter\In\Irc\HelpServService;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ServiceCommandListenerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Registers HeLPServ with the Service Command Gateway. Receives (senderUid, text)
 * and delegates to HelpServService with SenderView from NetworkUserLookupPort.
 */
final readonly class HelpServCommandListener implements ServiceCommandListenerInterface
{
    public function __construct(
        private HelpServBot $helpServBot,
        private HelpServService $helpServService,
        private NetworkUserLookupPort $userLookup,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getServiceName(): string
    {
        return $this->helpServBot->getNick();
    }

    public function getServiceUid(): string
    {
        return $this->helpServBot->getUid();
    }

    public function onCommand(string $senderUid, string $text): void
    {
        if ('' === $text) {
            return;
        }

        $sender = $this->userLookup->findByUid($senderUid);

        if (null === $sender) {
            $this->logger->warning('HelpServ: could not resolve sender UID: ' . $senderUid);

            return;
        }

        try {
            $this->helpServService->dispatch($text, $sender);
        } catch (Throwable $e) {
            $this->logger->error('HelpServ dispatch error: ' . $e->getMessage(), [
                'exception' => $e,
                'sender' => $sender->uid,
            ]);
        }
    }
}
