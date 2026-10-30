<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Port\In;

use DateTimeImmutable;

/**
 * Administrative changes to registered channels, decided by the network staff.
 *
 * Public entry point for CReG: the Interés General (IG) mark and APODERA, the takeover
 * of an IG channel by the administration.
 */
interface ChannelAdministration
{
    /** Marks or unmarks a registered channel as Interés General; Unchanged when it already was. */
    public function markInterestGeneral(string $channelName, bool $interestGeneral): ChannelAdministrationOutcome;

    /** @return list<string> the names of the channels marked as Interés General */
    public function interestGeneralChannels(): array;

    /**
     * Gives an Interés General channel to a new founder, without the founder's consent and
     * without the per-nick channel limit: the administration is taking the channel over.
     */
    public function takeOver(
        string $channelName,
        string $newFounderNickname,
        string $actorNickname,
        DateTimeImmutable $occurredAt,
    ): ChannelAdministrationOutcome;
}
