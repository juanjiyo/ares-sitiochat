<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\Out\Irc;

use App\GlobalServ\Adapter\Out\Irc\ActiveConnectionGlobalBroadcast;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SendNoticePort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveConnectionGlobalBroadcast::class)]
final class ActiveConnectionGlobalBroadcastTest extends TestCase
{
    private const string SERVICE_UID = '001GHS';

    private const string MESSAGE = "[ Global Message ]\nhello";

    /**
     * @param list<string> $connectedUids
     */
    private function createUserLookup(array $connectedUids): NetworkUserLookupPort
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn($connectedUids);

        return $users;
    }

    private function createConnection(bool $connected): ActiveConnectionHolderInterface
    {
        $connection = $this->createStub(ActiveConnectionHolderInterface::class);
        $connection->method('isConnected')->willReturn($connected);

        return $connection;
    }

    #[Test]
    public function sendsOneNoticePerConnectedUserAndReturnsTheRecipientCount(): void
    {
        $sent = [];
        $messages = $this->createMock(SendNoticePort::class);
        $messages->expects(self::exactly(3))->method('sendNotice')->willReturnCallback(
            static function (string $senderUid, string $targetUid, string $message) use (&$sent): void {
                $sent[] = [$senderUid, $targetUid, $message];
            },
        );

        $broadcast = new ActiveConnectionGlobalBroadcast(
            $this->createConnection(true),
            $this->createUserLookup(['001AAA', '001BBB', '001CCC']),
            $messages,
        );

        $count = $broadcast->broadcastNotice(self::SERVICE_UID, self::MESSAGE);

        self::assertSame(3, $count);
        self::assertSame(
            [
                [self::SERVICE_UID, '001AAA', self::MESSAGE],
                [self::SERVICE_UID, '001BBB', self::MESSAGE],
                [self::SERVICE_UID, '001CCC', self::MESSAGE],
            ],
            $sent,
        );
    }

    #[Test]
    public function returnsNullWhenTheConnectionIsDown(): void
    {
        $messages = $this->createMock(SendNoticePort::class);
        $messages->expects(self::never())->method('sendNotice');

        $broadcast = new ActiveConnectionGlobalBroadcast(
            $this->createConnection(false),
            $this->createUserLookup(['001AAA']),
            $messages,
        );

        self::assertNull($broadcast->broadcastNotice(self::SERVICE_UID, self::MESSAGE));
    }

    #[Test]
    public function returnsZeroWhenNoUserIsConnected(): void
    {
        $messages = $this->createMock(SendNoticePort::class);
        $messages->expects(self::never())->method('sendNotice');

        $broadcast = new ActiveConnectionGlobalBroadcast(
            $this->createConnection(true),
            $this->createUserLookup([]),
            $messages,
        );

        self::assertSame(0, $broadcast->broadcastNotice(self::SERVICE_UID, self::MESSAGE));
    }
}
