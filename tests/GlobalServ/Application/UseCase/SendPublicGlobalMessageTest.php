<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Application\UseCase;

use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessage;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageOutcome;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendPublicGlobalMessage::class)]
#[CoversClass(SendPublicGlobalMessageResult::class)]
final class SendPublicGlobalMessageTest extends TestCase
{
    #[Test]
    public function commandCarriesTheActorMessageAndTimestamp(): void
    {
        $occurredAt = new DateTimeImmutable('2026-10-27 10:00:00');
        $command = new SendPublicGlobalMessage('Alice', 'hi', $occurredAt);

        self::assertSame('Alice', $command->actorNickname);
        self::assertSame('hi', $command->message);
        self::assertSame($occurredAt, $command->occurredAt);
    }

    #[Test]
    public function resultFactoriesDescribeTheOutcome(): void
    {
        $sent = SendPublicGlobalMessageResult::sent(5);
        self::assertSame(SendPublicGlobalMessageOutcome::Sent, $sent->outcome);
        self::assertSame(5, $sent->recipientCount);

        $unavailable = SendPublicGlobalMessageResult::networkUnavailable();
        self::assertSame(SendPublicGlobalMessageOutcome::NetworkUnavailable, $unavailable->outcome);
        self::assertNull($unavailable->recipientCount);
    }
}
