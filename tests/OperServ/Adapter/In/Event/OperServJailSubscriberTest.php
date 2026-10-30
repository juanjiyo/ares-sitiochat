<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Event;

use App\Irc\Application\PublishedEvent\ChannelSynchronizedEvent;
use App\Irc\Application\PublishedEvent\UserDepartedChannelEvent;
use App\Irc\Application\PublishedEvent\UserJoinedChannelEvent;
use App\Irc\Application\PublishedEvent\UserLeftNetworkEvent;
use App\OperServ\Adapter\In\Event\OperServJailSubscriber;
use App\OperServ\Application\UseCase\Jail\EnforceJail;
use App\OperServ\Application\UseCase\Jail\JailRoster;
use App\Tests\OperServ\Application\UseCase\Jail\RecordingJailNetworkActions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OperServJailSubscriber::class)]
final class OperServJailSubscriberTest extends TestCase
{
    #[Test]
    public function forwardsChannelAndNetworkMovesToTheJail(): void
    {
        self::assertSame([
            UserJoinedChannelEvent::class => ['onUserJoinedChannel', 0],
            UserDepartedChannelEvent::class => ['onUserDepartedChannel', 0],
            UserLeftNetworkEvent::class => ['onUserLeftNetwork', 0],
            ChannelSynchronizedEvent::class => ['onChannelSynchronized', 0],
        ], OperServJailSubscriber::getSubscribedEvents());

        $roster = new JailRoster();
        $roster->jail('PRESO');
        $network = new RecordingJailNetworkActions(['PRESO' => ['#nuevo']]);
        $subscriber = new OperServJailSubscriber(new EnforceJail($network, $roster, '#Sumidero'));

        $subscriber->onUserJoinedChannel(new UserJoinedChannelEvent('PRESO', '#otro'));
        $subscriber->onUserDepartedChannel(new UserDepartedChannelEvent('PRESO', '#Sumidero'));
        $subscriber->onChannelSynchronized(new ChannelSynchronizedEvent('#nuevo'));
        $subscriber->onUserLeftNetwork(new UserLeftNetworkEvent('PRESO', 'Preso', 'Quit'));

        self::assertSame([['part', 'PRESO', '#otro'], ['join', 'PRESO', '#Sumidero'], ['part', 'PRESO', '#nuevo']], $network->calls);
        self::assertFalse($roster->contains('PRESO'));
    }
}
