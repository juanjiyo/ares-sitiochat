<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServCommandRegistry;
use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Adapter\In\Irc\ChanServNotifierInterface;
use App\ChanServ\Adapter\In\Irc\Command\SetNoexpireHandler;
use App\ChanServ\Adapter\In\Irc\Command\SetTopicHandler;
use App\ChanServ\Application\Port\Out\ChanUserAccountPort;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\UseCase\ApplyStoredTopic\ApplyStoredChannelTopic;
use App\ChanServ\Application\UseCase\ApplyStoredTopic\ApplyStoredChannelTopicHandlerInterface;
use App\ChanServ\Application\UseCase\ApplyStoredTopic\StoredTopicApplicationTrigger;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSetting;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSettingHandler;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSettingResult;
use App\ChanServ\Domain\Entity\RegisteredChannel;
use App\Irc\Adapter\Protocol\NullChannelModeSupport;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use App\Shared\Application\Port\EventBusInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(SetNoexpireHandler::class)]
#[CoversClass(SetTopicHandler::class)]
#[CoversClass(UpdateChannelSetting::class)]
#[CoversClass(UpdateChannelSettingHandler::class)]
#[CoversClass(UpdateChannelSettingResult::class)]
final class SetTopicNoexpireHandlerTest extends TestCase
{
    #[Test]
    public function noexpireRejectsValuesOtherThanOnAndOff(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::never())->method('save');
        $messages = [];

        $handler = new SetNoexpireHandler($this->updater($repository));
        $handler->handle($this->context($messages, 'NOEXPIRE', 'maybe'), $channel, 'maybe');

        self::assertSame(['error.syntax'], $messages);
        self::assertFalse($channel->isNoExpire());
    }

    #[Test]
    public function noexpireTogglesTheStoredFlag(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('save')->with($channel);
        $messages = [];

        $handler = new SetNoexpireHandler($this->updater($repository));
        $handler->handle($this->context($messages, 'NOEXPIRE', ' on '), $channel, ' on ');
        self::assertTrue($channel->isNoExpire());
        $handler->handle($this->context($messages, 'NOEXPIRE', 'OFF'), $channel, 'OFF');
        self::assertFalse($channel->isNoExpire());

        self::assertSame(['set.noexpire.on', 'set.noexpire.off'], $messages);
    }

    #[Test]
    public function topicStoresTheTextAndAppliesItOnTheNetwork(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with($channel);
        $applier = $this->createMock(ApplyStoredChannelTopicHandlerInterface::class);
        $applier->expects(self::once())->method('handle')->with(self::callback(
            static fn (ApplyStoredChannelTopic $command): bool => '#test' === $command->channelName
                && StoredTopicApplicationTrigger::ChannelSynchronized === $command->trigger,
        ));
        $messages = [];

        $handler = new SetTopicHandler($this->updater($repository), $applier);
        $handler->handle($this->context($messages, 'TOPIC', 'Bienvenidos a #test'), $channel, 'Bienvenidos a #test');

        self::assertSame('Bienvenidos a #test', $channel->getTopic());
        self::assertSame(['set.topic.changed'], $messages);
    }

    #[Test]
    public function topicWithAnEmptyValueClearsTheStoredTopic(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $channel->updateTopic('old', new DateTimeImmutable(), 'Someone');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::once())->method('save')->with($channel);
        $applier = $this->createMock(ApplyStoredChannelTopicHandlerInterface::class);
        $applier->expects(self::once())->method('handle');
        $messages = [];

        $handler = new SetTopicHandler($this->updater($repository), $applier);
        $handler->handle($this->context($messages, 'TOPIC', ''), $channel, '');

        self::assertNull($channel->getTopic());
        self::assertSame(['set.topic.cleared'], $messages);
    }

    private function updater(RegisteredChannelRepositoryInterface $channels): UpdateChannelSettingHandler
    {
        return new UpdateChannelSettingHandler(
            $channels,
            $this->createStub(ChanUserAccountPort::class),
            $this->createStub(EventBusInterface::class),
        );
    }

    /**
     * @param list<string> $messages
     */
    private function context(array &$messages, string $option, string $value, bool $withSender = true): ChanServContext
    {
        $notifier = $this->createStub(ChanServNotifierInterface::class);
        $notifier->method('sendMessage')->willReturnCallback(static function (string $target, string $message) use (&$messages): void {
            $messages[] = $message;
        });
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return new ChanServContext(
            $withSender ? new SenderView('UID1', 'User', 'ident', 'host', 'cloak', 'ip') : null,
            null,
            'SET',
            ['#test', $option, $value],
            $notifier,
            $translator,
            'en',
            'UTC',
            'NOTICE',
            new ChanServCommandRegistry([]),
            $this->createStub(ChannelLookupPort::class),
            new NullChannelModeSupport(),
            $this->createStub(NetworkUserLookupPort::class),
            $this->serviceNicks(),
        );
    }

    private function serviceNicks(): ServiceNicknameRegistry
    {
        $provider = new class implements ServiceNicknameProviderInterface {
            public function getServiceKey(): string
            {
                return 'chanserv';
            }

            public function getNickname(): string
            {
                return 'ChanServ';
            }
        };

        return new ServiceNicknameRegistry([$provider]);
    }
}
