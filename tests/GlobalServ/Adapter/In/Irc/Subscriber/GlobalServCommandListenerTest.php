<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc\Subscriber;

use App\GlobalServ\Adapter\In\Irc\Bot\GlobalServBot;
use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationCheckerInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationContextInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandRegistry;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Adapter\In\Irc\GlobalServNotifierInterface;
use App\GlobalServ\Adapter\In\Irc\GlobalServService;
use App\GlobalServ\Adapter\In\Irc\Subscriber\GlobalServCommandListener;
use App\GlobalServ\Application\Port\Out\GlobalServNickAccountPort;
use App\Irc\Adapter\Out\Connection\ActiveConnectionHolder;
use App\Irc\Application\Port\In\Command\CommandOutcome;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\SendNoticePort;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use App\Irc\Application\Port\In\ServiceUidGeneratorInterface;
use App\Irc\Application\PublishedEvent\ServiceIntroductionRequestedEvent;
use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(GlobalServCommandListener::class)]
final class GlobalServCommandListenerTest extends TestCase
{
    private const string GLOBALSERV_UID = '001GHS';

    private const string SENDER_UID = '001ABC';

    private MockObject&NetworkUserLookupPort $userLookup;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->userLookup = $this->createMock(NetworkUserLookupPort::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createBot(): GlobalServBot
    {
        $uidGenerator = $this->createStub(ServiceUidGeneratorInterface::class);
        $uidGenerator->method('generateUid')->willReturn(self::GLOBALSERV_UID);

        $bot = new GlobalServBot(
            new ActiveConnectionHolder(),
            $this->createStub(SendNoticePort::class),
            $uidGenerator,
            'services.example.com',
            logger: $this->createStub(LoggerInterface::class),
        );
        $bot->onBurstComplete(new ServiceIntroductionRequestedEvent('001'));

        return $bot;
    }

    private function createServiceNicks(): ServiceNicknameRegistry
    {
        $providers = [
            new class implements ServiceNicknameProviderInterface {
                public function getServiceKey(): string
                {
                    return 'globalserv';
                }

                public function getNickname(): string
                {
                    return 'GlobalServ';
                }
            },
            new class implements ServiceNicknameProviderInterface {
                public function getServiceKey(): string
                {
                    return 'nickserv';
                }

                public function getNickname(): string
                {
                    return 'NickServ';
                }
            },
        ];

        return new ServiceNicknameRegistry($providers);
    }

    /**
     * @param iterable<GlobalServCommandInterface> $commands
     */
    private function createService(iterable $commands = []): GlobalServService
    {
        $authorizationContext = $this->createStub(GlobalServAuthorizationContextInterface::class);
        $authorizationChecker = $this->createStub(GlobalServAuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);
        $userAccountPort = $this->createStub(GlobalServNickAccountPort::class);

        return new GlobalServService(
            commandRegistry: new GlobalServCommandRegistry($commands),
            notifier: $this->createStub(GlobalServNotifierInterface::class),
            translator: $this->createStub(TranslatorInterface::class),
            serviceNicks: $this->createServiceNicks(),
            authorizationContext: $authorizationContext,
            authorizationChecker: $authorizationChecker,
            userAccountPort: $userAccountPort,
        );
    }

    private function createListener(GlobalServService $service): GlobalServCommandListener
    {
        return new GlobalServCommandListener(
            $this->createBot(),
            $service,
            $this->userLookup,
            $this->logger,
        );
    }

    private function createSenderView(): SenderView
    {
        return new SenderView(self::SENDER_UID, 'TestUser', 'i', 'h', 'c', 'ip', isIdentified: true, isOper: true);
    }

    /**
     * A command that records the context it received and, optionally, fails.
     *
     * @param (Closure(GlobalServContext): void)|null $onExecute
     */
    private function createCommand(string $name, ?Closure $onExecute = null): GlobalServCommandInterface
    {
        return new class($name, $onExecute) implements GlobalServCommandInterface {
            public function __construct(
                private readonly string $name,
                private readonly ?Closure $onExecute,
            ) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function getAliases(): array
            {
                return [];
            }

            public function getMinArgs(): int
            {
                return 0;
            }

            public function getSyntaxKey(): string
            {
                return 'stub.syntax';
            }

            public function getHelpKey(): string
            {
                return 'stub.help';
            }

            public function getOrder(): int
            {
                return 0;
            }

            public function getShortDescKey(): string
            {
                return 'stub.short';
            }

            public function getSubCommandHelp(): array
            {
                return [];
            }

            public function isOperOnly(): bool
            {
                return false;
            }

            public function getRequiredPermission(): ?string
            {
                return null;
            }

            public function execute(GlobalServContext $context): ?CommandOutcome
            {
                if (null !== $this->onExecute) {
                    ($this->onExecute)($context);
                }

                return null;
            }
        };
    }

    #[Test]
    public function getServiceNameReturnsBotNickname(): void
    {
        $listener = $this->createListener($this->createService());
        $this->userLookup->expects(self::never())->method('findByUid');
        $this->logger->expects(self::never())->method('warning');

        self::assertSame('GlobalServ', $listener->getServiceName());
    }

    #[Test]
    public function getServiceUidReturnsBotUidAfterBurst(): void
    {
        $listener = $this->createListener($this->createService());
        $this->userLookup->expects(self::never())->method('findByUid');
        $this->logger->expects(self::never())->method('warning');

        self::assertSame(self::GLOBALSERV_UID, $listener->getServiceUid());
    }

    #[Test]
    public function onCommandWithEmptyTextDoesNothing(): void
    {
        $listener = $this->createListener($this->createService());
        $this->userLookup->expects(self::never())->method('findByUid');
        $this->logger->expects(self::never())->method('warning');

        $listener->onCommand(self::SENDER_UID, '');
    }

    #[Test]
    public function onCommandWhenSenderIsUnknownLogsWarningAndStops(): void
    {
        $listener = $this->createListener($this->createService());
        $this->userLookup->expects(self::once())->method('findByUid')->with('999XXX')->willReturn(null);
        $this->logger->expects(self::once())->method('warning')
            ->with('GlobalServ: could not resolve sender UID: 999XXX');

        $listener->onCommand('999XXX', 'GLOBAL');
    }

    #[Test]
    public function onCommandRoutesGlobalToTheGlobalCommand(): void
    {
        $sender = $this->createSenderView();
        $this->userLookup->expects(self::once())->method('findByUid')->with(self::SENDER_UID)->willReturn($sender);

        $executed = false;
        $command = $this->createCommand('GLOBAL', static function (GlobalServContext $context) use (&$executed, $sender): void {
            $executed = true;
            self::assertSame($sender->uid, $context->sender?->uid);
            self::assertSame('GLOBAL', $context->command);
            self::assertSame(['hello', 'world'], $context->args);
        });
        $listener = $this->createListener($this->createService([$command]));
        $this->logger->expects(self::never())->method('error');

        $listener->onCommand(self::SENDER_UID, 'GLOBAL hello world');

        self::assertTrue($executed);
    }

    #[Test]
    public function onCommandIsCaseInsensitiveOnTheCommandName(): void
    {
        $sender = $this->createSenderView();
        $this->userLookup->expects(self::once())->method('findByUid')->willReturn($sender);

        $executed = false;
        $command = $this->createCommand('GLOBAL', static function () use (&$executed): void {
            $executed = true;
        });
        $listener = $this->createListener($this->createService([$command]));
        $this->logger->expects(self::never())->method('error');

        $listener->onCommand(self::SENDER_UID, 'global hello');

        self::assertTrue($executed);
    }

    #[Test]
    public function onCommandLogsAndSwallowsDispatchFailures(): void
    {
        $sender = $this->createSenderView();
        $this->userLookup->expects(self::once())->method('findByUid')->with(self::SENDER_UID)->willReturn($sender);

        $errors = [];
        $this->logger->expects(self::once())->method('error')->willReturnCallback(
            static function (string $message, array $context = []) use (&$errors): void {
                $errors[] = [$message, $context];
            },
        );

        $command = $this->createCommand('GLOBAL', static function (): void {
            throw new RuntimeException('boom');
        });
        $listener = $this->createListener($this->createService([$command]));

        $listener->onCommand(self::SENDER_UID, 'GLOBAL hi');

        self::assertSame('GlobalServ dispatch error: boom', $errors[0][0]);
        self::assertInstanceOf(RuntimeException::class, $errors[0][1]['exception']);
        self::assertSame(self::SENDER_UID, $errors[0][1]['sender']);
    }
}
