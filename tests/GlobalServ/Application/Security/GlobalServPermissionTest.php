<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Application\Security;

use App\GlobalServ\Application\Security\GlobalServPermission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(GlobalServPermission::class)]
final class GlobalServPermissionTest extends TestCase
{
    #[Test]
    public function allIrcopReturnsEveryPermissionWithItsCommandSetValue(): void
    {
        self::assertSame([
            'globalserv.global',
            'globalserv.nick',
            'globalserv.chan',
            'globalserv.join',
            'globalserv.preoper',
            'globalserv.oper',
            'globalserv.admin',
        ], GlobalServPermission::allIrcop());
    }

    #[Test]
    public function constructorCanBeInvokedViaReflectionForCoverage(): void
    {
        $reflection = new ReflectionClass(GlobalServPermission::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
        $this->addToAssertionCount(1);
    }
}
