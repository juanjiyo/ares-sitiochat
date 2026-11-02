<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Event;

use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\PublishedEvent\UserModesChangedEvent;
use App\NickServ\Application\Port\In\NickAccountQuery;
use App\OperServ\Application\Port\Out\OperatorRoleAccess;
use App\OperServ\Domain\ValueObject\StaffRank;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function str_split;

/**
 * Joins OPER, ADMIN and ROOT staff to their channels whenever the IRCd makes
 * them IRCops (+o), for example when they identify after reconnecting.
 */
final readonly class StaffChannelsOnOperUpSubscriber implements EventSubscriberInterface
{
    /**
     * @param string $staffChannels comma-separated channels OPER, ADMIN and ROOT join
     * @param string $adminChannels comma-separated channels ADMIN and ROOT also join
     */
    public function __construct(
        private NetworkUserLookupPort $users,
        private NickAccountQuery $accounts,
        private OperatorRoleAccess $roles,
        private ActiveProtocolModuleHolderInterface $connection,
        private string $staffChannels,
        private string $adminChannels,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [UserModesChangedEvent::class => ['onUserModesChanged', 0]];
    }

    public function onUserModesChanged(UserModesChangedEvent $event): void
    {
        if (!self::addsOper($event->modeDelta)) {
            return;
        }

        $user = $this->users->findByUid($event->uid);
        $accountId = null !== $user && $user->isIdentified ? $this->accounts->findIdByNick($user->nick) : null;
        $rank = StaffRank::fromName((null !== $accountId ? $this->roles->roleName($accountId) : null) ?? '');
        $actions = $this->connection->getProtocolModule()?->getServiceActions();
        $serverSid = $this->connection->getServerSid();
        if (null === $rank || null === $actions || null === $serverSid) {
            return;
        }

        foreach (StaffChannels::forRank($rank, $this->staffChannels, $this->adminChannels) as $channel) {
            $actions->forceJoin($serverSid, $event->uid, $channel);
        }
    }

    private static function addsOper(string $delta): bool
    {
        $adding = true;
        foreach (str_split($delta) as $letter) {
            if ('+' === $letter || '-' === $letter) {
                $adding = '+' === $letter;
            } elseif ('o' === $letter && $adding) {
                return true;
            }
        }

        return false;
    }
}
