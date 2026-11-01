<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\Security;

use App\OperServ\Application\Security\OperServPermission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function sprintf;

#[CoversClass(OperServPermission::class)]
final class OperServPermissionTest extends TestCase
{
    #[Test]
    public function killConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('KILL');

        self::assertSame('operserv.kill', $constant);
    }

    #[Test]
    public function rawConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('RAW');

        self::assertSame('operserv.raw', $constant);
    }

    #[Test]
    public function kickConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('KICK');

        self::assertSame('operserv.kick', $constant);
    }

    #[Test]
    public function opConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('OP');

        self::assertSame('operserv.op', $constant);
    }

    #[Test]
    public function modeConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('MODE');

        self::assertSame('operserv.mode', $constant);
    }

    #[Test]
    public function inviteConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('INVITE');

        self::assertSame('operserv.invite', $constant);
    }

    #[Test]
    public function clonesConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('CLONES');

        self::assertSame('operserv.clones', $constant);
    }

    #[Test]
    public function conexionesConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('CONEXIONES');

        self::assertSame('operserv.conexiones', $constant);
    }

    #[Test]
    public function umodeConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('UMODE');

        self::assertSame('operserv.umode', $constant);
    }

    #[Test]
    public function jailConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('JAIL');

        self::assertSame('operserv.jail', $constant);
    }

    #[Test]
    public function elineConstantHasExpectedValue(): void
    {
        $constant = new ReflectionClass(OperServPermission::class)->getConstant('ELINE');

        self::assertSame('operserv.eline', $constant);
    }

    #[Test]
    public function allConstantsAreStrings(): void
    {
        $reflection = new ReflectionClass(OperServPermission::class);
        $constants = $reflection->getReflectionConstants();

        foreach ($constants as $constant) {
            if ($constant->isPublic()) {
                self::assertIsString($constant->getValue(), sprintf('Constant %s should be a string', $constant->getName()));
            }
        }
    }

    #[Test]
    public function constructorIsPrivate(): void
    {
        $reflection = new ReflectionClass(OperServPermission::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate(), 'Constructor should be private');
    }

    #[Test]
    public function cannotBeInstantiated(): void
    {
        $reflection = new ReflectionClass(OperServPermission::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate(), 'Class should not be instantiable');

        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
    }
}
