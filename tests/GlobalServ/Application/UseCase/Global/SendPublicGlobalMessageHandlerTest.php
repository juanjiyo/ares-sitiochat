<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Application\UseCase\Global;

use App\GlobalServ\Application\Port\Out\GlobalBroadcastPort;
use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessage;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageHandler;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageOutcome;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendPublicGlobalMessageHandler::class)]
final class SendPublicGlobalMessageHandlerTest extends TestCase
{
    private const string SERVICE_UID = '001GHS';

    private GlobalServNetworkIdentity&Stub $identity;

    private GlobalBroadcastPort&MockObject $broadcast;

    protected function setUp(): void
    {
        $this->identity = $this->createStub(GlobalServNetworkIdentity::class);
        $this->broadcast = $this->createMock(GlobalBroadcastPort::class);
    }

    private function createHandler(): SendPublicGlobalMessageHandler
    {
        return new SendPublicGlobalMessageHandler($this->identity, $this->broadcast);
    }

    private function stubIdentity(?string $uid): void
    {
        $this->identity->method('serviceUid')->willReturn($uid);
    }

    #[Test]
    public function broadcastNoticeFromTheGlobalServServiceUid(): void
    {
        $this->stubIdentity(self::SERVICE_UID);
        $this->broadcast->expects(self::once())->method('broadcastNotice')->with(self::SERVICE_UID, "[ Global Message ]\nhello")->willReturn(3);

        $result = $this->createHandler()->handle($this->createMessage());

        self::assertSame(SendPublicGlobalMessageOutcome::Sent, $result->outcome);
        self::assertSame(3, $result->recipientCount);
    }

    #[Test]
    public function returnsNetworkUnavailableWhenServiceUidIsMissing(): void
    {
        $this->stubIdentity(null);
        $this->broadcast->expects(self::never())->method('broadcastNotice');

        $result = $this->createHandler()->handle($this->createMessage());

        self::assertSame(SendPublicGlobalMessageOutcome::NetworkUnavailable, $result->outcome);
        self::assertNull($result->recipientCount);
    }

    #[Test]
    public function returnsNetworkUnavailableWhenTheBroadcastCannotCountRecipients(): void
    {
        $this->stubIdentity(self::SERVICE_UID);
        $this->broadcast->expects(self::once())->method('broadcastNotice')->willReturn(null);

        $result = $this->createHandler()->handle($this->createMessage());

        self::assertSame(SendPublicGlobalMessageOutcome::NetworkUnavailable, $result->outcome);
        self::assertNull($result->recipientCount);
    }

    private function createMessage(): SendPublicGlobalMessage
    {
        return new SendPublicGlobalMessage(
            actorNickname: 'Alice',
            message: '[ Global Message ]' . "\n" . 'hello',
            occurredAt: new DateTimeImmutable(),
        );
    }
}
