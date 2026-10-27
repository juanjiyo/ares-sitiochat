<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServCommandRegistry;
use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Adapter\In\Irc\ChanServNotifierInterface;
use App\ChanServ\Adapter\In\Irc\Command\KickCommand;
use App\ChanServ\Application\Model\ChanAccountView;
use App\ChanServ\Application\Port\Out\ChanNetworkActions;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\UseCase\KickUser\KickUserHandler;
use App\ChanServ\Domain\Entity\RegisteredChannel;
use App\Irc\Adapter\Protocol\NullChannelModeSupport;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(KickCommand::class)]
final class KickCommandTest extends TestCase
{
    #[Test]
    public function exposesItsMetadata(): void
    {
        $command = new KickCommand($this->handler($this->stubRepo(), $this->stubNetwork(), $this->stubUsers()));

        self::assertSame('KICK', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('kick.syntax', $command->getSyntaxKey());
        self::assertSame('kick.help', $command->getHelpKey());
        self::assertSame('kick.short', $command->getShortDescKey());
        self::assertSame('kick.example', $command->getExampleKey());
        self::assertSame(67, $command->getOrder());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertFalse($command->isOperOnly());
        self::assertSame('IDENTIFIED', $command->getRequiredPermission());
        self::assertFalse($command->allowsSuspendedChannel());
        self::assertFalse($command->allowsForbiddenChannel());
        self::assertTrue($command->usesLevelFounder());
    }

    #[Test]
    public function kicksTheConnectedUser(): void
    {
        $repository = $this->stubRepo();
        $network = $this->createMock(ChanNetworkActions::class);
        $network->expects(self::once())->method('isChannelOnNetwork')->with('#room')->willReturn(true);
        $network->expects(self::once())->method('kickFromChannel')->with('#room', 'UID1', 'bye');

        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByNick')->willReturn(new SenderView('UID1', 'Bob', 'i', 'h', 'c', 'ip'));

        $command = new KickCommand($this->handler($repository, $network, $users));
        $messages = [];
        $command->execute($this->context($messages, ['#room', 'Bob', 'bye']));

        self::assertSame(['kick.done'], $messages);
    }

    #[Test]
    public function reportsAnUnregisteredChannel(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findByChannelName')->willReturn(null);

        $command = new KickCommand($this->handler($repository, $this->stubNetwork(), $this->stubUsers()));
        $messages = [];
        $command->execute($this->context($messages, ['#ghost', 'Bob']));

        self::assertSame(['error.channel_not_registered'], $messages);
    }

    #[Test]
    public function reportsAChannelThatIsNotOnTheNetwork(): void
    {
        $repository = $this->stubRepo();
        $network = $this->createStub(ChanNetworkActions::class);
        $network->method('isChannelOnNetwork')->willReturn(false);

        $command = new KickCommand($this->handler($repository, $network, $this->stubUsers()));
        $messages = [];
        $command->execute($this->context($messages, ['#room', 'Bob']));

        self::assertSame(['error.channel_not_on_network'], $messages);
    }

    #[Test]
    public function reportsANonConnectedTarget(): void
    {
        $repository = $this->stubRepo();
        $network = $this->createStub(ChanNetworkActions::class);
        $network->method('isChannelOnNetwork')->willReturn(true);

        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByNick')->willReturn(null);

        $command = new KickCommand($this->handler($repository, $network, $users));
        $messages = [];
        $command->execute($this->context($messages, ['#room', 'Ghost']));

        self::assertSame(['kick.not_connected'], $messages);
    }

    #[Test]
    public function doesNothingWithoutAChannelNameOrSender(): void
    {
        $command = new KickCommand($this->handler($this->stubRepo(), $this->stubNetwork(), $this->stubUsers()));
        $messages = [];
        $context = $this->context($messages, []);
        $command->execute($context);

        self::assertSame(['error.invalid_channel'], $messages);
    }

    #[Test]
    public function doesNothingWithoutASender(): void
    {
        $command = new KickCommand($this->handler($this->stubRepo(), $this->stubNetwork(), $this->stubUsers()));
        $messages = [];
        $context = $this->context($messages, ['#room', 'Bob'], withSender: false);

        $command->execute($context);

        self::assertSame([], $messages);
    }

    #[Test]
    public function exposesItsAuditData(): void
    {
        $command = new KickCommand($this->handler($this->stubRepo(), $this->stubNetwork(), $this->stubUsers()));
        $messages = [];
        $context = $this->context($messages, ['#room', 'Bob', 'bye']);

        self::assertSame('#room', $command->auditData($context)->target);
    }

    private function handler(
        RegisteredChannelRepositoryInterface $channels,
        ChanNetworkActions $network,
        NetworkUserLookupPort $users,
    ): KickUserHandler {
        return new KickUserHandler($channels, $network, $users);
    }

    private function stubRepo(): RegisteredChannelRepositoryInterface
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findByChannelName')->willReturn(
            RegisteredChannel::register(new DateTimeImmutable(), '#room', 1, 'Test'),
        );

        return $repository;
    }

    private function stubNetwork(): ChanNetworkActions
    {
        $network = $this->createStub(ChanNetworkActions::class);
        $network->method('isChannelOnNetwork')->willReturn(true);

        return $network;
    }

    private function stubUsers(): NetworkUserLookupPort
    {
        return $this->createStub(NetworkUserLookupPort::class);
    }

    /**
     * @param list<string> $messages
     * @param list<string> $args
     */
    private function context(array &$messages, array $args, bool $withSender = true): ChanServContext
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
            'KICK',
            $args,
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
