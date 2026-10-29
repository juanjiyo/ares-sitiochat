<?php

declare(strict_types=1);

namespace App\DebugServ\Adapter\In\Event;

use App\DebugServ\Adapter\In\Irc\Bot\DebugServBot;
use App\DebugServ\Adapter\In\Irc\NetworkEventTranslator;
use App\Irc\Adapter\Event\ConnectionLostEvent;
use App\Irc\Adapter\Event\IncomingIrcMessageEvent;
use App\Irc\Adapter\Event\NetworkSyncCompleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function in_array;
use function strtoupper;
use function time;
use function trim;

/**
 * Feeds every incoming network line to the translator and writes the result in
 * the debug channel.
 *
 * Lines are read before the protocol handles them, so a QUIT or a KILL can still
 * be shown with the nick instead of the UID. Nothing is written until the initial
 * burst is over: replaying the whole network would flood the channel.
 */
final class NetworkDebugRelaySubscriber implements EventSubscriberInterface
{
    private bool $synced = false;

    /** @var list<string> */
    private readonly array $disabledEvents;

    /**
     * @param string $disabledEvents comma-separated event types to keep out of the
     *                               channel (e.g. "SJOIN,PART,AWAY,RAW")
     */
    public function __construct(
        private readonly NetworkEventTranslator $translator,
        private readonly DebugServBot $bot,
        string $disabledEvents = '',
    ) {
        $this->disabledEvents = array_values(array_filter(array_map(
            static fn (string $type): string => strtoupper(trim($type)),
            explode(',', $disabledEvents),
        )));
    }

    public static function getSubscribedEvents(): array
    {
        return [
            IncomingIrcMessageEvent::class => ['onIncomingMessage', 100],
            NetworkSyncCompleteEvent::class => ['onSyncComplete', -30],
            ConnectionLostEvent::class => ['onConnectionLost', 0],
        ];
    }

    public function onIncomingMessage(IncomingIrcMessageEvent $event): void
    {
        if (!$this->bot->isActive()) {
            return;
        }

        // Always translated, even during the burst: it keeps servers and away state current.
        $line = $this->translator->translate($event->message, time());

        if (null === $line || !$this->synced || in_array($line[0], $this->disabledEvents, true)) {
            return;
        }

        $this->bot->say($line[1]);
    }

    public function onSyncComplete(NetworkSyncCompleteEvent $event): void
    {
        $this->synced = true;
    }

    public function onConnectionLost(ConnectionLostEvent $event): void
    {
        $this->synced = false;
    }
}
