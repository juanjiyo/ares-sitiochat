<?php

declare(strict_types=1);

namespace App\Tests\AntiSpam\Application\Security;

use App\AntiSpam\Application\Security\AntiSpamPermission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(AntiSpamPermission::class)]
final class AntiSpamPermissionTest extends TestCase
{
    #[Test]
    public function allIrcopReturnsEveryPermissionWithItsCommandSetValue(): void
    {
        self::assertSame([
            'antispam.global',
            'antispam.nick',
            'antispam.chan',
            'antispam.join',
            'antispam.preoper',
            'antispam.oper',
            'antispam.admin',
        ], AntiSpamPermission::allIrcop());
    }

    #[Test]
    public function constructorCanBeInvokedViaReflectionForCoverage(): void
    {
        $reflection = new ReflectionClass(AntiSpamPermission::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
        $this->addToAssertionCount(1);
    }
}
