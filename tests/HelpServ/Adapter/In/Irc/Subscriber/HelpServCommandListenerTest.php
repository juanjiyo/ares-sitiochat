<?php

declare(strict_types=1);

namespace App\Tests\HelpServ\Adapter\In\Irc\Subscriber;

use App\HelpServ\Adapter\In\Irc\Bot\HelpServBot;
use App\HelpServ\Adapter\In\Irc\HelpServCommandInterface;
use App\HelpServ\Adapter\In\Irc\HelpServCommandRegistry;
use App\HelpServ\Adapter\In\Irc\HelpServContext;
use App\HelpServ\Adapter\In\Irc\HelpServNotifierInterface;
use App\HelpServ\Adapter\In\Irc\HelpServService;
use App\HelpServ\Adapter\In\Irc\Subscriber\HelpServCommandListener;
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
use Throwable;

#[CoversClass(HelpServCommandListener::class)]
final class HelpServCommandListenerTest extends TestCase
{
    private const string HELPSERV_UID = '001DH';

    private const string SENDER_UID = '001ABC';

    private MockObject&NetworkUserLookupPort $userLookup;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->userLookup = $this->createMock(NetworkUserLookupPort::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createBot(): HelpServBot
    {
        $uidGenerator = $this->createStub(ServiceUidGeneratorInterface::class);
        $uidGenerator->method('generateUid')->willReturn(self::HELPSERV_UID);

        $bot = new HelpServBot(
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
                    return 'helpserv';
                }

                public function getNickname(): string
                {
                    return 'HeLP';
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
     * @param iterable<HelpServCommandInterface> $commands
     */
    private function createService(iterable $commands = []): HelpServService
    {
        return new HelpServService(
            commandRegistry: new HelpServCommandRegistry($commands),
            notifier: $this->createStub(HelpServNotifierInterface::class),
            translator: $this->createStub(TranslatorInterface::class),
            serviceNicks: $this->createServiceNicks(),
        );
    }

    private function createListener(HelpServService $service): HelpServCommandListener
    {
        return new HelpServCommandListener(
            $this->createBot(),
            $service,
            $this->userLookup,
            $this->logger,
        );
    }

    private function createSenderView(): SenderView
    {
        return new SenderView(self::SENDER_UID, 'TestUser', 'i', 'h', 'c', 'ip', isIdentified: true);
    }

    /**
     * A command that records the context it received and, optionally, fails.
     *
     * @param Closure(HelpServContext): void|null $onExecute
     */
    private function createCommand(string $name, ?Closure $onExecute = null, ?Throwable $failure = null): HelpServCommandInterface
    {
        return new class($name, $onExecute, $failure) implements HelpServCommandInterface {
            public function __construct(
                private readonly string $name,
                private readonly ?Closure $onExecute,
                private readonly ?Throwable $failure,
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

            public function execute(HelpServContext $context): ?CommandOutcome
            {
                if (null !== $this->failure) {
                    throw $this->failure;
                }
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

        self::assertSame('HeLP', $listener->getServiceName());
    }

    #[Test]
    public function getServiceUidReturnsBotUidAfterBurst(): void
    {
        $listener = $this->createListener($this->createService());
        $this->userLookup->expects(self::never())->method('findByUid');
        $this->logger->expects(self::never())->method('warning');

        self::assertSame(self::HELPSERV_UID, $listener->getServiceUid());
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
            ->with('HelpServ: could not resolve sender UID: 999XXX');

        $listener->onCommand('999XXX', 'UMODES');
    }

    #[Test]
    public function onCommandDispatchesToServiceWithResolvedSender(): void
    {
        $sender = $this->createSenderView();
        $this->userLookup->expects(self::once())->method('findByUid')->with(self::SENDER_UID)->willReturn($sender);

        $executed = false;
        $command = $this->createCommand('STUB', static function (HelpServContext $context) use (&$executed, $sender): void {
            $executed = true;
            self::assertSame($sender->uid, $context->sender?->uid);
            self::assertSame('STUB', $context->command);
        });
        $listener = $this->createListener($this->createService([$command]));
        $this->logger->expects(self::never())->method('error');

        $listener->onCommand(self::SENDER_UID, 'STUB');

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

        $command = $this->createCommand('STUB', failure: new RuntimeException('boom'));
        $listener = $this->createListener($this->createService([$command]));

        $listener->onCommand(self::SENDER_UID, 'STUB');

        self::assertCount(1, $errors);
        self::assertSame('HelpServ dispatch error: boom', $errors[0][0]);
        self::assertInstanceOf(RuntimeException::class, $errors[0][1]['exception']);
        self::assertSame(self::SENDER_UID, $errors[0][1]['sender']);
    }
}
