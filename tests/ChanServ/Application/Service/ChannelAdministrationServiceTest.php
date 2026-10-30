<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Application\Service;

use App\ChanServ\Application\Model\ChanAccountView;
use App\ChanServ\Application\Port\In\ChannelAdministrationOutcome;
use App\ChanServ\Application\Port\Out\ChannelAccessRepositoryInterface;
use App\ChanServ\Application\Port\Out\ChanServEventPublisher;
use App\ChanServ\Application\Port\Out\ChanUserAccountPort;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\PublishedEvent\ChannelFounderChangedEvent;
use App\ChanServ\Application\Service\ChannelAdministrationService;
use App\ChanServ\Domain\Entity\ChannelAccess;
use App\ChanServ\Domain\Entity\RegisteredChannel;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(ChannelAdministrationService::class)]
final class ChannelAdministrationServiceTest extends TestCase
{
    /** @var array<string, RegisteredChannel> */
    private array $channels = [];

    /** @var list<RegisteredChannel> */
    private array $saved = [];

    /** @var list<object> */
    private array $events = [];

    /** @var list<ChannelAccess> */
    private array $removedAccess = [];

    private ?ChannelAccess $founderAccess = null;

    protected function setUp(): void
    {
        $this->channels = [
            '#madrid' => $this->channel('#Madrid', 1, true, 10),
            '#casa' => $this->channel('#Casa', 1, false, 11),
            '#sevilla' => $this->channel('#Sevilla', 2, true, 12),
            '#prohibido' => RegisteredChannel::createForbidden(new DateTimeImmutable(), '#Prohibido', 'spam'),
        ];
    }

    private function channel(string $name, int $founder, bool $ig, int $id): RegisteredChannel
    {
        $channel = RegisteredChannel::register(new DateTimeImmutable(), $name, $founder, 'Desc');
        $channel->changeInterestGeneral($ig);
        new ReflectionProperty(RegisteredChannel::class, 'id')->setValue($channel, $id);

        return $channel;
    }

    private function service(): ChannelAdministrationService
    {
        $repository = $this->createStub(RegisteredChannelRepositoryInterface::class);
        $repository->method('findByChannelName')->willReturnCallback(fn (string $name): ?RegisteredChannel => $this->channels[strtolower($name)] ?? null);
        $repository->method('iterateAll')->willReturnCallback(fn (): array => array_values($this->channels));
        $repository->method('save')->willReturnCallback(function (RegisteredChannel $channel): void {
            $this->saved[] = $channel;
        });
        $access = $this->createStub(ChannelAccessRepositoryInterface::class);
        $access->method('findByChannelAndNick')->willReturnCallback(fn (): ?ChannelAccess => $this->founderAccess);
        $access->method('remove')->willReturnCallback(function (ChannelAccess $entry): void {
            $this->removedAccess[] = $entry;
        });
        $accounts = $this->createStub(ChanUserAccountPort::class);
        $accounts->method('findAccountByNick')->willReturnCallback(static fn (string $nick): ?ChanAccountView => match ($nick) {
            'SitioChat' => new ChanAccountView(7, 'SitioChat', 'es'),
            'Owner' => new ChanAccountView(1, 'Owner', 'es'),
            'Pending' => new ChanAccountView(8, 'Pending', 'es', registered: false),
            'Banned' => new ChanAccountView(9, 'Banned', 'es', suspended: true),
            default => null,
        });
        $accounts->method('findIdByNick')->willReturn(3);
        $events = $this->createStub(ChanServEventPublisher::class);
        $events->method('publish')->willReturnCallback(function (object $event): void {
            $this->events[] = $event;
        });

        return new ChannelAdministrationService($repository, $access, $accounts, $events);
    }

    #[Test]
    public function marksAndUnmarksInterestGeneralChannels(): void
    {
        $service = $this->service();

        self::assertSame(ChannelAdministrationOutcome::Updated, $service->markInterestGeneral('#casa', true));
        self::assertTrue($this->channels['#casa']->isInterestGeneral());
        self::assertSame(ChannelAdministrationOutcome::Unchanged, $service->markInterestGeneral('#Casa', true));
        self::assertSame(ChannelAdministrationOutcome::Updated, $service->markInterestGeneral('#Casa', false));
        self::assertSame(ChannelAdministrationOutcome::Unchanged, $service->markInterestGeneral('#Casa', false));
        self::assertSame(ChannelAdministrationOutcome::NotRegistered, $service->markInterestGeneral('#nadie', true));
        self::assertSame(ChannelAdministrationOutcome::NotRegistered, $service->markInterestGeneral('#Prohibido', true));
        self::assertCount(2, $this->saved);
    }

    #[Test]
    public function listsInterestGeneralChannelsSortedWithoutForbiddenOnes(): void
    {
        $this->channels['#prohibido']->changeInterestGeneral(true);
        $this->channels['#albacete'] = $this->channel('#albacete', 1, true, 13);

        self::assertSame(['#albacete', '#Madrid', '#Sevilla'], $this->service()->interestGeneralChannels());
    }

    #[Test]
    public function takesAnInterestGeneralChannelOverForTheAdministration(): void
    {
        $this->founderAccess = $this->createStub(ChannelAccess::class);
        $at = new DateTimeImmutable('2026-10-30 12:00:00');

        self::assertSame(ChannelAdministrationOutcome::Updated, $this->service()->takeOver('#Madrid', 'SitioChat', 'NetAdmin', $at));

        self::assertSame(7, $this->channels['#madrid']->getFounderNickId());
        self::assertSame([$this->channels['#madrid']], $this->saved);
        self::assertSame([$this->founderAccess], $this->removedAccess);
        self::assertCount(1, $this->events);
        $event = $this->events[0];
        self::assertInstanceOf(ChannelFounderChangedEvent::class, $event);
        self::assertSame([10, '#Madrid', 1, 7, 'NetAdmin', 3, true, $at], [
            $event->channelId, $event->channelName, $event->oldFounderNickId, $event->newFounderNickId,
            $event->performedBy, $event->performedByNickId, $event->byOperator, $event->occurredAt,
        ]);
    }

    #[Test]
    public function takeOverLeavesNoAccessEntryToRemoveWhenThereIsNone(): void
    {
        self::assertSame(ChannelAdministrationOutcome::Updated, $this->service()->takeOver('#Sevilla', 'SitioChat', 'NetAdmin', new DateTimeImmutable()));
        self::assertSame([], $this->removedAccess);
    }

    #[Test]
    public function refusesTakeOversThatAreNotAllowed(): void
    {
        $service = $this->service();
        $at = new DateTimeImmutable();

        self::assertSame(ChannelAdministrationOutcome::NotRegistered, $service->takeOver('#nadie', 'SitioChat', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::NotInterestGeneral, $service->takeOver('#Casa', 'SitioChat', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::FounderNotRegistered, $service->takeOver('#Madrid', 'Ghost', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::FounderNotRegistered, $service->takeOver('#Madrid', 'Pending', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::FounderSuspended, $service->takeOver('#Madrid', 'Banned', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::Unchanged, $service->takeOver('#Madrid', 'Owner', 'NetAdmin', $at));
        self::assertSame([], $this->saved);
        self::assertSame([], $this->events);
    }
}
