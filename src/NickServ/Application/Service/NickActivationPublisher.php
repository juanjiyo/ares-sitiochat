<?php

declare(strict_types=1);

namespace App\NickServ\Application\Service;

use App\NickServ\Application\Port\Out\RegistrationEventPublisher;
use App\NickServ\Application\PublishedEvent\NickPasswordHashAvailable;
use App\NickServ\Domain\Entity\RegisteredNick;

/**
 * Publishes the projection event for a nick that has just become active.
 *
 * Every activation path must go through here: the UDB N-block record is only
 * written when this event is dispatched, so a path that skips it leaves the
 * nick unusable for native authentication until the snapshot is rebuilt.
 */
final readonly class NickActivationPublisher
{
    public function __construct(private RegistrationEventPublisher $events) {}

    public function passwordHashAvailable(RegisteredNick $nick): void
    {
        $this->events->publish(new NickPasswordHashAvailable(
            nickId: $nick->getId(),
            nickname: $nick->getNickname(),
            passwordHash: $nick->getPasswordHash(),
        ));
    }
}
