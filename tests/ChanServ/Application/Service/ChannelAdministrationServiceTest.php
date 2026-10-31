<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Application\Service;

use App\ChanServ\Application\Model\ChanAccountView;
use App\ChanServ\Application\Model\ChannelMember;
use App\ChanServ\Application\Model\ChannelRankNetworkState;
use App\ChanServ\Application\Model\MemberRankChange;
use App\ChanServ\Application\Port\In\ChannelAdministrationOutcome;
use App\ChanServ\Application\Port\In\ChannelRegistration;
use App\ChanServ\Application\Port\In\ChannelRegistrationOutcome;
use App\ChanServ\Application\Port\In\OfficialRegistrationOutcome;
use App\ChanServ\Application\Port\Out\ChannelAccessRepositoryInterface;
use App\ChanServ\Application\Port\Out\ChannelRankActions;
use App\ChanServ\Application\Port\Out\ChannelRankNetworkQuery;
use App\ChanServ\Application\Port\Out\ChanServEventPublisher;
use App\ChanServ\Application\Port\Out\ChanUserAccountPort;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\PublishedEvent\ChannelFounderChangedEvent;
use App\ChanServ\Application\Service\ChannelAdministrationService;
use App\ChanServ\Domain\Entity\ChannelAccess;
use App\ChanServ\Domain\Entity\RegisteredChannel;
use App\ChanServ\Domain\ValueObject\ChannelRank;
use App\ChanServ\Domain\ValueObject\RankChangeAction;
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

    private ?ChannelRankNetworkState $networkChannel = null;

    /** @var list<array{string, list<MemberRankChange>}> */
    private array $rankChanges = [];

    private ChannelRegistrationOutcome $registrationOutcome = ChannelRegistrationOutcome::Registered;

    private bool $registrationStoresChannel = true;

    /** @var list<array{string, string, int, string}> */
    private array $registrations = [];

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

        $network = $this->createStub(ChannelRankNetworkQuery::class);
        $network->method('findChannel')->willReturnCallback(fn (): ?ChannelRankNetworkState => $this->networkChannel);
        $ranks = $this->createStub(ChannelRankActions::class);
        $ranks->method('apply')->willReturnCallback(function (string $channel, array $changes): void {
            $typed = [];
            foreach ($changes as $change) {
                self::assertInstanceOf(MemberRankChange::class, $change);
                $typed[] = $change;
            }
            $this->rankChanges[] = [$channel, $typed];
        });

        $registration = $this->createStub(ChannelRegistration::class);
        $registration->method('registerApproved')->willReturnCallback(function (string $name, string $description, int $founderId, string $actor): ChannelRegistrationOutcome {
            $this->registrations[] = [$name, $description, $founderId, $actor];
            if (ChannelRegistrationOutcome::Registered === $this->registrationOutcome && $this->registrationStoresChannel) {
                $this->channels[strtolower($name)] = RegisteredChannel::register(new DateTimeImmutable(), $name, $founderId, $description);
            }

            return $this->registrationOutcome;
        });

        return new ChannelAdministrationService($repository, $access, $accounts, $events, $network, $ranks, $registration);
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

        self::assertSame(ChannelAdministrationOutcome::Updated, $this->service()->takeOver('#Madrid', true, 'SitioChat', 'NetAdmin', $at));

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
        self::assertSame(ChannelAdministrationOutcome::Updated, $this->service()->takeOver('#Sevilla', true, 'SitioChat', 'NetAdmin', new DateTimeImmutable()));
        self::assertSame([], $this->removedAccess);
    }

    #[Test]
    public function refusesTakeOversThatAreNotAllowed(): void
    {
        $service = $this->service();
        $at = new DateTimeImmutable();

        self::assertSame(ChannelAdministrationOutcome::NotRegistered, $service->takeOver('#nadie', true, 'SitioChat', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::NotInterestGeneral, $service->takeOver('#Casa', true, 'SitioChat', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::InterestGeneral, $service->takeOver('#Madrid', false, 'SitioChat', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::FounderNotRegistered, $service->takeOver('#Madrid', true, 'Ghost', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::FounderNotRegistered, $service->takeOver('#Madrid', true, 'Pending', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::FounderSuspended, $service->takeOver('#Madrid', true, 'Banned', 'NetAdmin', $at));
        self::assertSame(ChannelAdministrationOutcome::Unchanged, $service->takeOver('#Madrid', true, 'Owner', 'NetAdmin', $at));
        self::assertSame([], $this->saved);
        self::assertSame([], $this->events);
        self::assertSame([], $this->rankChanges);
    }

    #[Test]
    public function takesAChannelThatIsNotInterestGeneralOverAndStripsStaffRanks(): void
    {
        $this->networkChannel = new ChannelRankNetworkState('#Casa', [
            new ChannelMember('UID1', 'Dueño', true, false, false, 1, [ChannelRank::Owner, ChannelRank::Operator]),
            new ChannelMember('UID2', 'Charla', false, false, false, null, [ChannelRank::Voice]),
            new ChannelMember('UID3', 'Ayuda', true, false, false, 4, [ChannelRank::Administrator, ChannelRank::HalfOperator, ChannelRank::Voice]),
            new ChannelMember('UID9', 'CHaN', true, true, true, null, [ChannelRank::Operator]),
        ], ChannelRank::highestFirst());

        self::assertSame(ChannelAdministrationOutcome::Updated, $this->service()->takeOver('#Casa', false, 'SitioChat', 'NetAdmin', new DateTimeImmutable()));

        self::assertSame(7, $this->channels['#casa']->getFounderNickId());
        self::assertCount(1, $this->rankChanges);
        [$channel, $changes] = $this->rankChanges[0];
        self::assertSame('#Casa', $channel);
        self::assertSame(
            [['UID1', ChannelRank::Owner], ['UID1', ChannelRank::Operator], ['UID3', ChannelRank::Administrator], ['UID3', ChannelRank::HalfOperator]],
            array_map(static fn (MemberRankChange $change): array => [$change->uid, $change->change->rank], $changes),
        );
        foreach ($changes as $change) {
            self::assertSame(RankChangeAction::Revoke, $change->change->action);
        }
    }

    #[Test]
    public function takeOverSendsNoModesWhenNobodyHoldsAStaffRank(): void
    {
        $this->networkChannel = new ChannelRankNetworkState('#Madrid', [
            new ChannelMember('UID2', 'Charla', false, false, false, null, [ChannelRank::Voice]),
        ], ChannelRank::highestFirst());

        self::assertSame(ChannelAdministrationOutcome::Updated, $this->service()->takeOver('#Madrid', true, 'SitioChat', 'NetAdmin', new DateTimeImmutable()));
        self::assertSame([], $this->rankChanges);
    }

    #[Test]
    public function registersAnOfficialChannelForTheGivenFounder(): void
    {
        $outcome = $this->service()->registerOfficial('#Oficial', 'SitioChat', 'Canal de la red', 'Oper', new DateTimeImmutable());

        self::assertSame(OfficialRegistrationOutcome::Registered, $outcome);
        self::assertSame([['#Oficial', 'Canal de la red', 7, 'Oper']], $this->registrations);
        self::assertTrue($this->channels['#oficial']->isOfficial());
        self::assertTrue($this->channels['#oficial']->isNoExpire());
        self::assertSame([$this->channels['#oficial']], $this->saved);
    }

    #[Test]
    public function refusesAnOfficialChannelForAnUnregisteredOrSuspendedFounder(): void
    {
        $service = $this->service();

        self::assertSame(OfficialRegistrationOutcome::FounderNotRegistered, $service->registerOfficial('#Oficial', 'Nadie', '', 'Oper', new DateTimeImmutable()));
        self::assertSame(OfficialRegistrationOutcome::FounderNotRegistered, $service->registerOfficial('#Oficial', 'Pending', '', 'Oper', new DateTimeImmutable()));
        self::assertSame(OfficialRegistrationOutcome::FounderSuspended, $service->registerOfficial('#Oficial', 'Banned', '', 'Oper', new DateTimeImmutable()));
        self::assertSame([], $this->registrations);
    }

    #[Test]
    public function reportsWhyAnOfficialChannelWasNotRegistered(): void
    {
        $service = $this->service();
        $cases = [
            [ChannelRegistrationOutcome::AlreadyRegistered, OfficialRegistrationOutcome::AlreadyRegistered],
            [ChannelRegistrationOutcome::PendingDeletion, OfficialRegistrationOutcome::PendingDeletion],
            [ChannelRegistrationOutcome::Failed, OfficialRegistrationOutcome::Failed],
        ];
        foreach ($cases as [$registration, $expected]) {
            $this->registrationOutcome = $registration;
            self::assertSame($expected, $service->registerOfficial('#Casa', 'SitioChat', '', 'Oper', new DateTimeImmutable()));
        }

        $this->registrationOutcome = ChannelRegistrationOutcome::Registered;
        $this->registrationStoresChannel = false;
        self::assertSame(OfficialRegistrationOutcome::Failed, $service->registerOfficial('#Perdido', 'SitioChat', '', 'Oper', new DateTimeImmutable()));
        self::assertFalse($this->channels['#casa']->isOfficial());
        self::assertSame([], $this->saved);
    }
}
