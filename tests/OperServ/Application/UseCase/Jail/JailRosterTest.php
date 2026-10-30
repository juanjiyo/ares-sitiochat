<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\Jail;

use App\OperServ\Application\UseCase\Jail\JailRoster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JailRoster::class)]
final class JailRosterTest extends TestCase
{
    #[Test]
    public function keepsJailedUsersUntilReleased(): void
    {
        $roster = new JailRoster();
        $roster->jail('UID1');
        $roster->jail('UID2');

        self::assertTrue($roster->contains('UID1'));
        self::assertSame(['UID1', 'UID2'], $roster->all());
        self::assertTrue($roster->release('UID1'));
        self::assertFalse($roster->release('UID1'));
        self::assertFalse($roster->contains('UID1'));
        self::assertSame(['UID2'], $roster->all());
    }
}
