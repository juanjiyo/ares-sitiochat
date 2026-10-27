<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\Command\GlobalCommand;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandRegistry;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Adapter\In\Irc\GlobalServNotifierInterface;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessage;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageHandlerInterface;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageResult;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(GlobalCommand::class)]
final class GlobalCommandTest extends TestCase
{
    private const string SENDER_UID = '001ABC';

    /**
     * @param array<string, list<string>> $replies
     * @param array<string, string>       $translations
     */
    private function createContext(
        array &$replies,
        array $translations = ['global.prefix' => '[ Global Message ]'],
        bool $withSender = true,
    ): GlobalServContext {
        $notifier = $this->createStub(GlobalServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('GlobalServ');
        $notifier->method('sendMessage')->willReturnCallback(
            static function (string $target, string $message) use (&$replies): void {
                $replies[$target][] = $message;
            },
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => $translations[$id] ?? $id,
        );

        return new GlobalServContext(
            sender: $withSender ? new SenderView(self::SENDER_UID, 'Alice', 'i', 'h', 'c', 'ip', isIdentified: true, isOper: true) : null,
            senderAccountId: 7,
            command: 'GLOBAL',
            args: ['Network', 'maintenance', 'soon'],
            notifier: $notifier,
            translator: $translator,
            language: 'en',
            registry: new GlobalServCommandRegistry([]),
            serviceNicks: new ServiceNicknameRegistry([$this->createServiceNickProvider()]),
        );
    }

    private function createServiceNickProvider(): ServiceNicknameProviderInterface
    {
        return new class implements ServiceNicknameProviderInterface {
            public function getServiceKey(): string
            {
                return 'globalserv';
            }

            public function getNickname(): string
            {
                return 'GlobalServ';
            }
        };
    }

    private function createHandler(
        SendPublicGlobalMessageResult $result,
        ?SendPublicGlobalMessage &$handled = null,
    ): SendPublicGlobalMessageHandlerInterface {
        $handler = $this->createMock(SendPublicGlobalMessageHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->willReturnCallback(
            static function (SendPublicGlobalMessage $command) use ($result, &$handled): SendPublicGlobalMessageResult {
                $handled = $command;

                return $result;
            },
        );

        return $handler;
    }

    #[Test]
    public function metadataDeclaresTheIrcopGlobalPermission(): void
    {
        $command = new GlobalCommand($this->createStub(SendPublicGlobalMessageHandlerInterface::class));

        self::assertSame('GLOBAL', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertTrue($command->isOperOnly());
        self::assertSame(GlobalServPermission::GLOBAL, $command->getRequiredPermission());
        self::assertSame('global.syntax', $command->getSyntaxKey());
        self::assertSame('global.help', $command->getHelpKey());
        self::assertSame('global.short', $command->getShortDescKey());
    }

    #[Test]
    public function broadcastsThePresentedTwoLineMessageAndRepliesWithTheRecipientCount(): void
    {
        $replies = [];
        $handled = null;
        $command = new GlobalCommand($this->createHandler(
            SendPublicGlobalMessageResult::sent(42),
            $handled,
        ));
        $context = $this->createContext($replies);

        $command->execute($context);

        self::assertNotNull($handled);
        self::assertSame('Alice', $handled->actorNickname);
        self::assertSame("[ Global Message ]\nNetwork maintenance soon", $handled->message);
        self::assertSame([self::SENDER_UID => ['global.done']], $replies);
    }

    #[Test]
    public function staysSilentWhenTheNetworkIsUnavailable(): void
    {
        $replies = [];
        $handled = null;
        $command = new GlobalCommand($this->createHandler(
            SendPublicGlobalMessageResult::networkUnavailable(),
            $handled,
        ));
        $context = $this->createContext($replies);

        $command->execute($context);

        self::assertNotNull($handled);
        self::assertSame([], $replies);
    }

    #[Test]
    public function doesNothingWithoutASender(): void
    {
        $replies = [];
        $handler = $this->createMock(SendPublicGlobalMessageHandlerInterface::class);
        $handler->expects(self::never())->method('handle');
        $command = new GlobalCommand($handler);
        $context = $this->createContext($replies, withSender: false);

        $command->execute($context);

        self::assertSame([], $replies);
    }

    #[Test]
    public function getSubCommandHelpIsEmpty(): void
    {
        $command = new GlobalCommand($this->createStub(SendPublicGlobalMessageHandlerInterface::class));

        self::assertSame([], $command->getSubCommandHelp());
        self::assertSame(1, $command->getOrder());
    }
}
