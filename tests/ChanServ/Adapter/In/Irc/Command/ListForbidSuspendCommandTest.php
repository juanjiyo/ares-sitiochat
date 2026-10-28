<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServCommandRegistry;
use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Adapter\In\Irc\ChanServNotifierInterface;
use App\ChanServ\Adapter\In\Irc\Command\ListforbidCommand;
use App\ChanServ\Adapter\In\Irc\Command\ListsuspendCommand;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
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

#[CoversClass(ListforbidCommand::class)]
#[CoversClass(ListsuspendCommand::class)]
final class ListForbidSuspendCommandTest extends TestCase
{
    #[Test]
    public function listforbidReportsAnEmptyList(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findForbiddenChannels')->willReturn([]);
        $messages = [];

        new ListforbidCommand($repository)->execute($this->context($messages));

        self::assertSame(['listforbid.empty'], $messages);
    }

    #[Test]
    public function listforbidListsEveryForbiddenChannelWithItsReason(): void
    {
        $forbidden = RegisteredChannel::createForbidden(new DateTimeImmutable(), '#bad', 'abuse');
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findForbiddenChannels')->willReturn([$forbidden]);
        $messages = [];

        $command = new ListforbidCommand($repository);
        $command->execute($this->context($messages));

        self::assertSame('LISTFORBID', $command->getName());
        self::assertTrue($command->isOperOnly());
        self::assertSame('chanserv.listforbid', $command->getRequiredPermission());
        self::assertSame(['listforbid.header', 'listforbid.line'], $messages);
    }

    #[Test]
    public function listsuspendReportsAnEmptyList(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findSuspendedChannels')->willReturn([]);
        $messages = [];

        new ListsuspendCommand($repository)->execute($this->context($messages));

        self::assertSame(['listsuspend.empty'], $messages);
    }

    #[Test]
    public function listsuspendListsEverySuspendedChannelWithReasonAndExpiry(): void
    {
        $channel = $this->createStub(RegisteredChannel::class);
        $channel->method('getName')->willReturn('#bad');
        $channel->method('getSuspendedReason')->willReturn('flood');
        $channel->method('getSuspendedUntil')->willReturn(new DateTimeImmutable('2026-11-01 10:00:00'));
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findSuspendedChannels')->willReturn([$channel]);
        $messages = [];

        $command = new ListsuspendCommand($repository);
        $command->execute($this->context($messages));

        self::assertSame('LISTSUSPEND', $command->getName());
        self::assertSame('chanserv.listsuspend', $command->getRequiredPermission());
        self::assertSame(['listsuspend.header', 'listsuspend.line'], $messages);
    }

    #[Test]
    public function listingsExposeTheirMetadataAndAuditData(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $messages = [];

        $forbid = new ListforbidCommand($repository);
        self::assertSame([], $forbid->getAliases());
        self::assertTrue($forbid->isOperOnly());
        self::assertSame(0, $forbid->getMinArgs());
        self::assertSame('listforbid.syntax', $forbid->getSyntaxKey());
        self::assertSame('listforbid.help', $forbid->getHelpKey());
        self::assertSame('listforbid.short', $forbid->getShortDescKey());
        self::assertSame('listforbid.example', $forbid->getExampleKey());
        self::assertSame(81, $forbid->getOrder());
        self::assertSame([], $forbid->getSubCommandHelp());
        self::assertFalse($forbid->usesLevelFounder());
        self::assertTrue($forbid->allowsSuspendedChannel());
        self::assertTrue($forbid->allowsForbiddenChannel());
        self::assertSame('*', $forbid->auditData($this->context($messages))->target);

        $suspend = new ListsuspendCommand($repository);
        self::assertSame([], $suspend->getAliases());
        self::assertTrue($suspend->isOperOnly());
        self::assertSame(0, $suspend->getMinArgs());
        self::assertSame('listsuspend.syntax', $suspend->getSyntaxKey());
        self::assertSame('listsuspend.help', $suspend->getHelpKey());
        self::assertSame('listsuspend.short', $suspend->getShortDescKey());
        self::assertSame('listsuspend.example', $suspend->getExampleKey());
        self::assertSame(80, $suspend->getOrder());
        self::assertSame([], $suspend->getSubCommandHelp());
        self::assertFalse($suspend->usesLevelFounder());
        self::assertTrue($suspend->allowsSuspendedChannel());
        self::assertTrue($suspend->allowsForbiddenChannel());
        self::assertSame('*', $suspend->auditData($this->context($messages))->target);
    }

    /**
     * @param list<string> $messages
     */
    private function context(array &$messages): ChanServContext
    {
        $notifier = $this->createStub(ChanServNotifierInterface::class);
        $notifier->method('sendMessage')->willReturnCallback(static function (string $target, string $message) use (&$messages): void {
            $messages[] = $message;
        });
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return new ChanServContext(
            new SenderView('UID1', 'Oper', 'ident', 'host', 'cloak', 'ip'),
            null,
            'LISTSUSPEND',
            [],
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
