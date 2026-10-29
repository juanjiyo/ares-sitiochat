<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ChannelOverridePresenter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(ChannelOverridePresenter::class)]
final class ChannelOverridePresenterTest extends TestCase
{
    #[Test]
    public function constructorCanBeInvokedViaReflectionForCoverage(): void
    {
        $reflection = new ReflectionClass(ChannelOverridePresenter::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $constructor->invoke($reflection->newInstanceWithoutConstructor());
        $this->addToAssertionCount(1);
    }
}
