<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServCommandRegistry;
use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Adapter\In\Irc\ChanServNotifierInterface;
use App\ChanServ\Adapter\In\Irc\Command\SetDefaultTopicHandler;
use App\ChanServ\Adapter\In\Irc\Command\SetKeepTopicHandler;
use App\ChanServ\Application\Model\ChanAccountView;
use App\ChanServ\Application\Port\Out\ChanUserAccountPort;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\UseCase\UpdateSetting\ChannelSetting;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSetting;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSettingHandler;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSettingOutcome;
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

#[CoversClass(SetKeepTopicHandler::class)]
#[CoversClass(SetDefaultTopicHandler::class)]
#[CoversClass(UpdateChannelSettingHandler::class)]
#[CoversClass(RegisteredChannel::class)]
final class SetKeepDefaultTopicHandlerTest extends TestCase
{
    #[Test]
    public function keepTopicRejectsValuesOtherThanOnAndOff(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::never())->method('save');
        $messages = [];

        new SetKeepTopicHandler($this->updater($repository))->handle($this->context($messages, 'KEEPTOPIC', 'maybe'), $channel, 'maybe');

        self::assertSame(['error.syntax'], $messages);
        self::assertFalse($channel->isKeepTopic());
    }

    #[Test]
    public function keepTopicTogglesTheStoredFlag(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('save')->with($channel);
        $messages = [];

        $handler = new SetKeepTopicHandler($this->updater($repository));
        $handler->handle($this->context($messages, 'KEEPTOPIC', ' on '), $channel, ' on ');
        self::assertTrue($channel->isKeepTopic());
        $handler->handle($this->context($messages, 'KEEPTOPIC', 'OFF'), $channel, 'OFF');
        self::assertFalse($channel->isKeepTopic());

        self::assertSame(['set.keeptopic.on', 'set.keeptopic.off'], $messages);
    }

    #[Test]
    public function defaultTopicStoresTheTextAndClearsItWhenEmpty(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::exactly(2))->method('save')->with($channel);
        $messages = [];

        $handler = new SetDefaultTopicHandler($this->updater($repository));
        $handler->handle($this->context($messages, 'DEFAULTTOPIC', 'Welcome'), $channel, 'Welcome');
        self::assertSame('Welcome', $channel->getDefaultTopic());
        $handler->handle($this->context($messages, 'DEFAULTTOPIC', ''), $channel, '');
        self::assertNull($channel->getDefaultTopic());

        self::assertSame(['set.defaulttopic.changed', 'set.defaulttopic.cleared'], $messages);
    }

    #[Test]
    public function bothHandlersDoNothingWithoutASender(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::never())->method('save');
        $messages = [];

        new SetKeepTopicHandler($this->updater($repository))->handle($this->context($messages, 'KEEPTOPIC', 'ON', withSender: false), $channel, 'ON');
        new SetDefaultTopicHandler($this->updater($repository))
            ->handle($this->context($messages, 'DEFAULTTOPIC', 'text', withSender: false), $channel, 'text');

        self::assertFalse($channel->isKeepTopic());
        self::assertNull($channel->getDefaultTopic());
        self::assertSame([], $messages);
    }

    #[Test]
    public function keepTopicHandlerReportsMissingValueForInvalidInput(): void
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), '#test', 1, 'Test');
        $repository = $this->createMock(RegisteredChannelRepositoryInterface::class);
        $repository->expects(self::never())->method('save');

        $request = new UpdateChannelSetting(
            channel: $channel,
            setting: ChannelSetting::KeepTopic,
            value: 'maybe',
            actorNickname: 'User',
            actorAccountId: 1,
            actorIp: '*',
            actorHost: 'User@host',
            occurredAt: new DateTimeImmutable(),
        );

        $result = $this->updater($repository)->handle($request);

        self::assertSame(UpdateChannelSettingOutcome::MissingValue, $result->outcome);
        self::assertFalse($channel->isKeepTopic());
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
            $withSender ? new ChanAccountView(1, 'User', 'en') : null,
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
