<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\OperServ\Adapter\Out\Irc\ActiveConnectionElineNetworkActions;
use App\OperServ\Application\Port\Out\ElineEntry;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveConnectionElineNetworkActions::class)]
final class ActiveConnectionElineNetworkActionsTest extends TestCase
{
    #[Test]
    public function publishesAndRemovesThroughTheActiveProtocol(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::exactly(2))->method('addBanException')->with(
            '001',
            'znc',
            '203.0.113.10',
            'Oper',
            1000,
            self::logicalOr(4600, 0),
            'GkZz',
            'ZNC',
        );
        $actions->expects(self::once())->method('removeBanException')->with('001', '*', '1.2.3.4', 'Root');
        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn($module);
        $connection->method('getServerSid')->willReturn('001');
        $network = new ActiveConnectionElineNetworkActions($connection, 'GkZz');
        $setAt = new DateTimeImmutable('@1000');

        self::assertTrue($network->add(new ElineEntry('znc@203.0.113.10', 'Oper', 'ZNC', $setAt, new DateTimeImmutable('@4600'))));
        self::assertTrue($network->add(new ElineEntry('znc@203.0.113.10', 'Oper', 'ZNC', $setAt, null)));
        $network->remove('*@1.2.3.4', 'Root');
    }

    #[Test]
    public function doesNothingWithoutAnActiveProtocol(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('getProtocolModule')->willReturn(null);
        $network = new ActiveConnectionElineNetworkActions($connection, 'GkZz');

        self::assertFalse($network->add(new ElineEntry('*@1.2.3.4', 'Oper', 'r', new DateTimeImmutable(), null)));
        $network->remove('*@1.2.3.4', 'Root');
        $this->addToAssertionCount(1);
    }
}
