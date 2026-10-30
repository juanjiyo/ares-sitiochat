<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Service;

use App\ChanServ\Application\Port\In\ChannelAdministration;
use App\ChanServ\Application\Port\In\ChannelAdministrationOutcome;
use App\ChanServ\Application\Port\Out\ChannelAccessRepositoryInterface;
use App\ChanServ\Application\Port\Out\ChanServEventPublisher;
use App\ChanServ\Application\Port\Out\ChanUserAccountPort;
use App\ChanServ\Application\Port\Out\RegisteredChannelRepositoryInterface;
use App\ChanServ\Application\PublishedEvent\ChannelFounderChangedEvent;
use App\ChanServ\Domain\Entity\RegisteredChannel;
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
        string $newFounderNickname,
        string $actorNickname,
        DateTimeImmutable $occurredAt,
    ): ChannelAdministrationOutcome {
        $channel = $this->registered($channelName);
        if (null === $channel) {
            return ChannelAdministrationOutcome::NotRegistered;
        }

        if (!$channel->isInterestGeneral()) {
            return ChannelAdministrationOutcome::NotInterestGeneral;
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

        return ChannelAdministrationOutcome::Updated;
    }

    private function registered(string $channelName): ?RegisteredChannel
    {
        $channel = $this->channels->findByChannelName($channelName);

        return null === $channel || $channel->isForbidden() ? null : $channel;
    }
}
