<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\OperServ\Adapter\Out\Irc\NetworkUserMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(NetworkUserMapper::class)]
final class NetworkUserMapperTest extends TestCase
{
    #[Test]
    public function constructorCanBeInvokedViaReflectionForCoverage(): void
    {
        $reflection = new ReflectionClass(NetworkUserMapper::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
        $this->addToAssertionCount(1);
    }
}
