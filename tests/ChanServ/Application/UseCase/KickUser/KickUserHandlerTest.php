<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Application\UseCase\KickUser;

use App\ChanServ\Application\Port\Out\ChanNetworkActions;
use App\ChanServ\Application\Port\Out\ChanNetworkUserLookup;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\UseCase\KickUser\KickUser;
use App\ChanServ\Application\UseCase\KickUser\KickUserHandler;
use App\ChanServ\Application\UseCase\KickUser\KickUserOutcome;
use App\ChanServ\Application\UseCase\KickUser\KickUserResult;
use App\ChanServ\Domain\Entity\RegisteredChannel;
use App\Irc\Application\Port\In\SenderView;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(KickUser::class)]
#[CoversClass(KickUserHandler::class)]
#[CoversClass(KickUserResult::class)]
final class KickUserHandlerTest extends TestCase
{
    #[Test]
    public function reportsAnUnregisteredChannel(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findByChannelName')->willReturn(null);
        $result = new KickUserHandler($repository, $this->createStub(ChanNetworkActions::class), $this->createStub(ChanNetworkUserLookup::class))
            ->handle(new KickUser('#room', 'Bob', 'Oper', 'bye'));
        self::assertSame(KickUserOutcome::ChannelNotRegistered, $result->outcome);
    }

    #[Test]
    public function reportsAChannelThatIsNotOnTheNetwork(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findByChannelName')->willReturn(RegisteredChannel::register(new DateTimeImmutable(), '#room', 1, 'Test'));
        $network = $this->createStub(ChanNetworkActions::class);
        $network->method('isChannelOnNetwork')->willReturn(false);
        $result = new KickUserHandler($repository, $network, $this->createStub(ChanNetworkUserLookup::class))
            ->handle(new KickUser('#room', 'Bob', 'Oper', 'bye'));
        self::assertSame(KickUserOutcome::ChannelNotOnNetwork, $result->outcome);
    }

    #[Test]
    public function reportsANonConnectedTarget(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findByChannelName')->willReturn(RegisteredChannel::register(new DateTimeImmutable(), '#room', 1, 'Test'));
        $network = $this->createStub(ChanNetworkActions::class);
        $network->method('isChannelOnNetwork')->willReturn(true);
        $users = $this->createStub(ChanNetworkUserLookup::class);
        $users->method('findByNick')->willReturn(null);
        $result = new KickUserHandler($repository, $network, $users)
            ->handle(new KickUser('#room', 'Ghost', 'Oper', 'bye'));
        self::assertSame(KickUserOutcome::TargetNotConnected, $result->outcome);
    }

    #[Test]
    public function kicksTheConnectedUser(): void
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findByChannelName')->willReturn(RegisteredChannel::register(new DateTimeImmutable(), '#room', 1, 'Test'));
        $network = $this->createMock(ChanNetworkActions::class);
        $network->expects(self::once())->method('isChannelOnNetwork')->with('#room')->willReturn(true);
        $network->expects(self::once())->method('kickFromChannel')->with('#room', 'UID1', 'bye');
        $users = $this->createStub(ChanNetworkUserLookup::class);
        $users->method('findByNick')->willReturn(new SenderView('UID1', 'Bob', 'i', 'h', 'c', 'ip'));
        $result = new KickUserHandler($repository, $network, $users)
            ->handle(new KickUser('#room', 'Bob', 'Oper', 'bye'));
        self::assertSame(KickUserOutcome::Kicked, $result->outcome);
    }

    #[Test]
    public function resultFactoriesDescribeTheirOutcomes(): void
    {
        $kicked = KickUserResult::kicked('#room', 'Bob');
        self::assertSame(KickUserOutcome::Kicked, $kicked->outcome);

        $unregistered = KickUserResult::channelNotRegistered('#room');
        self::assertSame(KickUserOutcome::ChannelNotRegistered, $unregistered->outcome);

        $offline = KickUserResult::channelNotOnNetwork('#room');
        self::assertSame(KickUserOutcome::ChannelNotOnNetwork, $offline->outcome);

        $missing = KickUserResult::targetNotConnected('Bob');
        self::assertSame(KickUserOutcome::TargetNotConnected, $missing->outcome);

        $command = new KickUser('#room', 'Bob', 'Oper', 'bye');
        self::assertSame('#room', $command->channelName);
    }
}
