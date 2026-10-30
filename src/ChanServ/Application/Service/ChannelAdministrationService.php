<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Service;

use App\ChanServ\Application\Model\MemberRankChange;
use App\ChanServ\Application\Port\In\ChannelAdministration;
use App\ChanServ\Application\Port\In\ChannelAdministrationOutcome;
use App\ChanServ\Application\Port\Out\ChannelAccessRepositoryInterface;
use App\ChanServ\Application\Port\Out\ChannelRankActions;
use App\ChanServ\Application\Port\Out\ChannelRankNetworkQuery;
use App\ChanServ\Application\Port\Out\ChanServEventPublisher;
use App\ChanServ\Application\Port\Out\ChanUserAccountPort;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\PublishedEvent\ChannelFounderChangedEvent;
use App\ChanServ\Domain\Entity\RegisteredChannel;
use App\ChanServ\Domain\ValueObject\ChannelRank;
use App\ChanServ\Domain\ValueObject\RankChange;
use App\ChanServ\Domain\ValueObject\RankChangeAction;
use DateTimeImmutable;

use function sort;

use const SORT_FLAG_CASE;
use const SORT_STRING;

final readonly class ChannelAdministrationService implements ChannelAdministration
{
    public function __construct(
        private RegisteredChannelRepositoryInterface $channels,
        private ChannelAccessRepositoryInterface $access,
        private ChanUserAccountPort $accounts,
        private ChanServEventPublisher $events,
        private ChannelRankNetworkQuery $network,
        private ChannelRankActions $ranks,
    ) {}

    public function markInterestGeneral(string $channelName, bool $interestGeneral): ChannelAdministrationOutcome
    {
        $channel = $this->registered($channelName);
        if (null === $channel) {
            return ChannelAdministrationOutcome::NotRegistered;
        }

        if ($channel->isInterestGeneral() === $interestGeneral) {
            return ChannelAdministrationOutcome::Unchanged;
        }

        $channel->changeInterestGeneral($interestGeneral);
        $this->channels->save($channel);

        return ChannelAdministrationOutcome::Updated;
    }

    public function interestGeneralChannels(): array
    {
        $names = [];
        foreach ($this->channels->iterateAll() as $channel) {
            if ($channel->isInterestGeneral() && !$channel->isForbidden()) {
                $names[] = $channel->getName();
            }
        }

        sort($names, SORT_STRING | SORT_FLAG_CASE);

        return $names;
    }

    public function takeOver(
        string $channelName,
        bool $interestGeneral,
        string $newFounderNickname,
        string $actorNickname,
        DateTimeImmutable $occurredAt,
    ): ChannelAdministrationOutcome {
        $channel = $this->registered($channelName);
        if (null === $channel) {
            return ChannelAdministrationOutcome::NotRegistered;
        }

        if ($channel->isInterestGeneral() !== $interestGeneral) {
            return $interestGeneral
                ? ChannelAdministrationOutcome::NotInterestGeneral
                : ChannelAdministrationOutcome::InterestGeneral;
        }

        $founder = $this->accounts->findAccountByNick($newFounderNickname);
        if (null === $founder || !$founder->registered) {
            return ChannelAdministrationOutcome::FounderNotRegistered;
        }

        if ($founder->suspended) {
            return ChannelAdministrationOutcome::FounderSuspended;
        }

        $oldFounderNickId = $channel->getFounderNickId();
        if ($oldFounderNickId === $founder->id) {
            return ChannelAdministrationOutcome::Unchanged;
        }

        $channel->changeFounder($founder->id);
        $this->channels->save($channel);

        // The founder holds every right; an access entry of their own would be redundant.
        $access = $this->access->findByChannelAndNick($channel->getId(), $founder->id);
        if (null !== $access) {
            $this->access->remove($access);
        }

        $this->events->publish(new ChannelFounderChangedEvent(
            channelId: $channel->getId(),
            channelName: $channel->getName(),
            oldFounderNickId: $oldFounderNickId,
            newFounderNickId: $founder->id,
            performedBy: $actorNickname,
            performedByNickId: $this->accounts->findIdByNick($actorNickname),
            performedByIp: '',
            performedByHost: '',
            occurredAt: $occurredAt,
            byOperator: true,
        ));

        $this->stripStaffRanks($channel->getName());

        return ChannelAdministrationOutcome::Updated;
    }

    /** Removes +q, +a, +o and +h from every user in the channel except the services. */
    private function stripStaffRanks(string $channelName): void
    {
        $state = $this->network->findChannel($channelName);
        if (null === $state) {
            return;
        }

        $changes = [];
        foreach ($state->members as $member) {
            if ($member->service) {
                continue;
            }
            foreach ($member->currentRanks as $rank) {
                if (ChannelRank::Voice !== $rank) {
                    $changes[] = new MemberRankChange($member->uid, new RankChange($rank, RankChangeAction::Revoke));
                }
            }
        }

        if ([] !== $changes) {
            $this->ranks->apply($channelName, $changes);
        }
    }

    private function registered(string $channelName): ?RegisteredChannel
    {
        $channel = $this->channels->findByChannelName($channelName);

        return null === $channel || $channel->isForbidden() ? null : $channel;
    }
}
