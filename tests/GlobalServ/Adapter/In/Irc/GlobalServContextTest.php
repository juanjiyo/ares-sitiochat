<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc;

use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandRegistry;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Adapter\In\Irc\GlobalServNotifierInterface;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_scalar;

#[CoversClass(GlobalServContext::class)]
#[CoversClass(GlobalServCommandRegistry::class)]
final class GlobalServContextTest extends TestCase
{
    #[Test]
    public function registryFindsCommandsByNameAndAliasAndDeduplicatesAll(): void
    {
        $command = $this->createCommand('GLOBAL', ['G']);
        $registry = new GlobalServCommandRegistry([$command]);

        self::assertSame($command, $registry->find('global'));
        self::assertSame($command, $registry->find('G'));
        self::assertNull($registry->find('NOPE'));
        self::assertSame([$command], $registry->all());
    }

    #[Test]
    public function contextExposesTheSenderAndTranslatesWithServicePlaceholders(): void
    {
        $replies = [];
        $context = $this->createContext($replies, new SenderView('001ABC', 'Alice', 'a', 'h', 'c', 'ip', isIdentified: true, isOper: true), 7);

        self::assertSame('Alice', $context->getSenderNickname());
        self::assertSame(7, $context->getSenderAccountId());
        self::assertTrue($context->isSenderIdentified());
        self::assertTrue($context->isSenderIrcOperator());
        self::assertSame($context->getSender(), $context->sender);
        self::assertCount(0, $context->getRegistry()->all());

        $context->reply('greeting', ['name' => 'Bob']);
        $context->replyRaw('plain');

        self::assertSame(['greeting:%bot%=GlobalServ|%globalserv%=GlobalServ|%name%=Bob', 'plain'], $replies);
        self::assertSame('greeting:%bot%=GlobalServ|%globalserv%=GlobalServ|%name%=Bob', $context->trans('greeting', ['name' => 'Bob']));
    }

    #[Test]
    public function contextStaysSilentWithoutASender(): void
    {
        $replies = [];
        $context = $this->createContext($replies, null, null);

        self::assertNull($context->getSenderNickname());
        self::assertNull($context->getSenderAccountId());
        self::assertFalse($context->isSenderIdentified());
        self::assertFalse($context->isSenderIrcOperator());

        $context->reply('greeting');
        $context->replyRaw('plain');

        self::assertSame([], $replies);
    }

    /**
     * @param list<string> $replies
     */
    private function createContext(array &$replies, ?SenderView $sender, ?int $accountId): GlobalServContext
    {
        $notifier = $this->createStub(GlobalServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('GlobalServ');
        $notifier->method('sendMessage')->willReturnCallback(static function (string $target, string $message) use (&$replies): void {
            $replies[] = $message;
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $params = []): string {
                $parts = [];
                foreach ($params as $key => $value) {
                    $parts[] = $key . '=' . (is_scalar($value) ? (string) $value : '');
                }

                return $id . ([] === $parts ? '' : ':' . implode('|', $parts));
            },
        );

        return new GlobalServContext(
            sender: $sender,
            senderAccountId: $accountId,
            command: 'GLOBAL',
            args: [],
            notifier: $notifier,
            translator: $translator,
            language: 'en',
            registry: new GlobalServCommandRegistry([]),
            serviceNicks: $this->serviceNicks(),
        );
    }

    /**
     * @param list<string> $aliases
     */
    private function createCommand(string $name, array $aliases): GlobalServCommandInterface
    {
        $command = $this->createStub(GlobalServCommandInterface::class);
        $command->method('getName')->willReturn($name);
        $command->method('getAliases')->willReturn($aliases);

        return $command;
    }

    private function serviceNicks(): ServiceNicknameRegistry
    {
        $provider = new class implements ServiceNicknameProviderInterface {
            public function getServiceKey(): string
            {
                return 'globalserv';
            }

            public function getNickname(): string
            {
                return 'GlobalServ';
            }
        };

        return new ServiceNicknameRegistry([$provider]);
    }
}
