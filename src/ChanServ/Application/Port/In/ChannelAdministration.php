<?php

declare(strict_types=1);

namespace App\ChanServ\Application\Port\In;

use DateTimeImmutable;

/**
 * Administrative changes to registered channels, decided by the network staff.
 *
 * Public entry point for CReG: the Interés General (IG) mark, APODERA (the takeover
 * of a channel by the administration) and OFICIAL (registering a network channel).
 */
interface ChannelAdministration
{
    /** Marks or unmarks a registered channel as Interés General; Unchanged when it already was. */
    public function markInterestGeneral(string $channelName, bool $interestGeneral): ChannelAdministrationOutcome;

    /** @return list<string> the names of the channels marked as Interés General */
    public function interestGeneralChannels(): array;

    /**
     * Gives a channel to a new founder, without the founder's consent and without the
     * per-nick channel limit, and strips +q, +a, +o and +h from every user but the services:
     * the administration is taking the channel over. $interestGeneral says which kind of
     * channel the operator meant; a channel of the other kind is refused.
     */
    public function takeOver(
        string $channelName,
        bool $interestGeneral,
        string $newFounderNickname,
        string $actorNickname,
        DateTimeImmutable $occurredAt,
    ): ChannelAdministrationOutcome;

    /**
     * Registers a channel of the network itself in the name of a registered, unsuspended
     * founder, without the per-nick channel limit, marks it as official and turns NOEXPIRE on.
     */
    public function registerOfficial(
        string $channelName,
        string $founderNickname,
        string $description,
        string $actorNickname,
        DateTimeImmutable $occurredAt,
    ): OfficialRegistrationOutcome;
}
