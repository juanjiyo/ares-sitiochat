<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Event;

use App\GlobalServ\Adapter\In\Irc\Bot\GlobalServBot;
use App\GlobalServ\Adapter\In\Irc\GlobalServService;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ServiceCommandListenerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final readonly class GlobalServCommandListener implements ServiceCommandListenerInterface
{
    public function __construct(
        private GlobalServBot $bot,
        private GlobalServService $service,
        private NetworkUserLookupPort $userLookup,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getServiceName(): string
    {
        return $this->bot->getNick();
    }

    public function getServiceUid(): string
    {
        return $this->bot->getUid();
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
            $this->service->dispatch($text, $sender);
        } catch (Throwable $e) {
            $this->logger->error('GlobalServ dispatch error', [
                'exception_class' => $e::class,
                'sender' => $sender->uid,
            ]);
        }
    }
}
