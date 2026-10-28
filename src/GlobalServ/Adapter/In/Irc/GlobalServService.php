<?php

declare(strict_types=1);

namespace App\GlobalServ\Adapter\In\Irc;

use App\GlobalServ\Adapter\In\Irc\Bot\GlobalServBot;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\NickServ\Application\Port\In\NickAccountQuery;
use App\OperServ\Application\Model\MessageDelivery;
use App\OperServ\Application\Port\In\OperatorActor;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\UseCase\Global\SendGlobalMessage;
use App\OperServ\Application\UseCase\Global\SendGlobalMessageHandler;
use App\OperServ\Application\UseCase\Global\SendGlobalMessageOutcome;
use App\OperServ\Application\UseCase\Global\SendGlobalMessageResult;
use App\OperServ\Domain\ValueObject\GlobalMessageMask;
use DateTimeImmutable;
use ValueError;

use function array_shift;
use function implode;
use function is_string;
use function preg_split;
use function sprintf;
use function str_starts_with;
use function strtoupper;
use function trim;

use const PREG_SPLIT_NO_EMPTY;

/**
 * GlobalServ: network-wide announcements and staff broadcasts.
 *
 * This adapter owns IRC parsing and presentation. Authorization delegates to
 * OperServ's OperatorAuthorizationQuery, and delivery reuses the shared
 * broadcast transports so the protocol stays in one place.
 */
final readonly class GlobalServService
{
    public function __construct(
        private GlobalServBot $bot,
        private ActiveProtocolModuleHolderInterface $connection,
        private NetworkUserLookupPort $userLookup,
        private NickAccountQuery $nickAccounts,
        private OperatorAuthorizationQuery $authorization,
        private ChannelLookupPort $channels,
        private SendGlobalMessageHandler $globalHandler,
    ) {}

    public function dispatch(string $rawText, SenderView $sender): void
    {
        $parts = preg_split('/\s+/', trim($rawText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $command = array_shift($parts);
        $commandName = strtoupper(is_string($command) ? $command : '');

        if ('' === $commandName) {
            return;
        }

        match ($commandName) {
            'GLOBAL' => $this->onGlobal($sender, $parts),
            'NICK' => $this->onNick($sender, $parts),
            'CHAN' => $this->onChan($sender, $parts),
            'JOIN' => $this->onJoin($sender, $parts),
            'PRE-OPER', 'PREOPER' => $this->onRank($sender, $parts, 'PRE-OPER'),
            'OPER' => $this->onRank($sender, $parts, 'OPER'),
            'ADMIN' => $this->onRank($sender, $parts, 'ADMIN'),
            'HELP', 'AYUDA' => $this->onHelp($sender),
            default => $this->reply($sender, sprintf('Comando desconocido %s. /msg %s HELP para ayuda.', $commandName, $this->bot->getNick())),
        };
    }

    /** @param list<string> $args */
    private function onGlobal(SenderView $sender, array $args): void
    {
        if (!$this->requireOper($sender)) {
            return;
        }

        $text = implode(' ', $args);
        if ('' === $text) {
            $this->reply($sender, 'Error de sintaxis: GLOBAL <mensaje>');

            return;
        }

        $result = $this->globalHandler->handle(new SendGlobalMessage(
            actorNickname: $sender->nick,
            senderMaskOrServiceNickname: $this->bot->getNick(),
            delivery: MessageDelivery::Interactive,
            message: $text,
            occurredAt: new DateTimeImmutable(),
        ));

        $this->presentGlobal($sender, $result);
    }

    /** @param list<string> $args */
    private function onNick(SenderView $sender, array $args): void
    {
        if (!$this->requireOper($sender)) {
            return;
        }

        $nick = array_shift($args);
        $text = implode(' ', $args);

        if (null === $nick || '' === $text) {
            $this->reply($sender, 'Error de sintaxis: NICK <nick> <mensaje>');

            return;
        }

        try {
            $mask = GlobalMessageMask::fromString(sprintf('%s!%s@%s', $nick, $nick, $this->bot->getVhost()));
        } catch (ValueError) {
            $this->reply($sender, sprintf('El nick %s no es válido.', $nick));

            return;
        }

        if (null !== $this->nickAccounts->findIdByNick($mask->nickname)) {
            $this->reply($sender, sprintf('El nick %s está registrado.', $nick));

            return;
        }

        if (null !== $this->userLookup->findByNick($mask->nickname)) {
            $this->reply($sender, sprintf('El nick %s está en uso.', $nick));

            return;
        }

        $result = $this->globalHandler->handle(new SendGlobalMessage(
            actorNickname: $sender->nick,
            senderMaskOrServiceNickname: (string) $mask,
            delivery: MessageDelivery::Interactive,
            message: $text,
            occurredAt: new DateTimeImmutable(),
        ));

        $this->presentGlobal($sender, $result);
    }

    /** @param list<string> $args */
    private function onChan(SenderView $sender, array $args): void
    {
        if (!$this->requireOper($sender)) {
            return;
        }

        $channelName = array_shift($args);
        $text = implode(' ', $args);

        if (null === $channelName || '' === $text) {
            $this->reply($sender, 'Error de sintaxis: CHAN <#canal> <mensaje>');

            return;
        }

        if (!str_starts_with($channelName, '#')) {
            $this->reply($sender, 'El canal debe comenzar por #.');

            return;
        }

        $channel = $this->channels->findByChannelName($channelName);
        if (null === $channel) {
            $this->reply($sender, sprintf('El canal %s no existe.', $channelName));

            return;
        }

        $this->broadcast($channel->members, $text, '[ Mensaje Global ]');
    }

    /** @param list<string> $args */
    private function onJoin(SenderView $sender, array $args): void
    {
        if (!$this->requireRoot($sender)) {
            return;
        }

        $channelName = array_shift($args);
        $text = implode(' ', $args);

        if (null === $channelName || '' === $text) {
            $this->reply($sender, 'Error de sintaxis: JOIN <#canal> <mensaje>');

            return;
        }

        if (!str_starts_with($channelName, '#')) {
            $this->reply($sender, 'El canal debe comenzar por #.');

            return;
        }

        $module = $this->connection->getProtocolModule();
        $serverSid = $this->connection->getServerSid();

        if (null === $module || null === $serverSid) {
            $this->reply($sender, 'La red no está disponible.');

            return;
        }

        $uids = $this->userLookup->listConnectedUids();
        foreach ($uids as $uid) {
            $module->getServiceActions()->forceJoin($serverSid, $uid, $channelName);
        }

        $this->broadcastUids($uids, $text, '[ Mensaje Global ]');
        $this->reply($sender, sprintf('Forzada la entrada de todos los usuarios de la red al canal %s.', $channelName));
    }

    /** @param list<string> $args */
    private function onRank(SenderView $sender, array $args, string $rank): void
    {
        if ('ADMIN' === $rank ? !$this->requireRoot($sender) : !$this->requireOper($sender)) {
            return;
        }

        $text = implode(' ', $args);
        if ('' === $text) {
            $this->reply($sender, sprintf('Error de sintaxis: %s <mensaje>', $rank));

            return;
        }

        $uids = 'ADMIN' === $rank ? $this->rootUids() : $this->operatorUids();

        $header = match ($rank) {
            'PRE-OPER' => '[ Mensaje Global para Pre-Operadores (Y superiores) ]',
            'OPER' => '[ Mensaje Global para Operadores (Y superiores) ]',
            'ADMIN' => '[ Mensaje Global para Administradores ]',
            default => '[ Mensaje Global ]',
        };

        $this->broadcastUids($uids, $text, $header);
    }

    private function onHelp(SenderView $sender): void
    {
        $this->reply($sender, 'Comandos: GLOBAL <mensaje> | NICK <nick> <mensaje> | CHAN <#canal> <mensaje> | JOIN <#canal> <mensaje> | PRE-OPER <mensaje> | OPER <mensaje> | ADMIN <mensaje>');
        $this->reply($sender, 'GLOBAL, NICK, CHAN, PRE-OPER y OPER requieren ser IRC oper. JOIN y ADMIN requieren root.');
    }

    /** @param array<array{uid: string, roleLetter: string, prefixLetters?: list<string>}> $members */
    private function broadcast(array $members, string $text, string $header): int
    {
        $uids = [];
        foreach ($members as $member) {
            $uids[] = $member['uid'];
        }

        return $this->broadcastUids($uids, $text, $header);
    }

    /** @param array<string> $uids */
    private function broadcastUids(array $uids, string $text, string $header): int
    {
        $count = 0;

        foreach ($uids as $uid) {
            $this->bot->sendMessage($uid, $header, 'PRIVMSG');
            $this->bot->sendMessage($uid, $text, 'PRIVMSG');
            ++$count;
        }

        return $count;
    }

    /** @return array<string> */
    private function operatorUids(): array
    {
        $uids = [];
        foreach ($this->userLookup->listConnectedUids() as $uid) {
            $view = $this->userLookup->findByUid($uid);
            if (null !== $view && $view->isOper) {
                $uids[] = $uid;
            }
        }

        return $uids;
    }

    /** @return array<string> */
    private function rootUids(): array
    {
        $uids = [];
        foreach ($this->userLookup->listConnectedUids() as $uid) {
            $view = $this->userLookup->findByUid($uid);
            if (null === $view || !$view->isIdentified) {
                continue;
            }

            $accountId = $this->nickAccounts->findIdByNick($view->nick);
            if (null === $accountId) {
                continue;
            }

            $actor = new OperatorActor(
                nickname: $view->nick,
                identifiedAccountId: $accountId,
                identified: true,
                ircOperator: $view->isOper,
            );

            if ($this->authorization->root($actor)->granted) {
                $uids[] = $uid;
            }
        }

        return $uids;
    }

    private function presentGlobal(SenderView $sender, SendGlobalMessageResult $result): void
    {
        match ($result->outcome) {
            SendGlobalMessageOutcome::Sent => $this->sendGlobalConfirmation($sender, $result->recipientCount ?? 0),
            SendGlobalMessageOutcome::NetworkUnavailable => $this->reply($sender, 'La red no está disponible.'),
            default => $this->reply($sender, 'No se pudo enviar el mensaje global.'),
        };
    }

    private function sendGlobalConfirmation(SenderView $sender, int $count): void
    {
        $this->reply($sender, 'Mensaje global enviado correctamente.');
        $this->reply($sender, sprintf('Número de Impactos: %d', $count));
    }

    private function requireOper(SenderView $sender): bool
    {
        $actor = $this->actor($sender);
        if ($this->authorization->ircOperator($actor)->granted) {
            return true;
        }

        $this->reply($sender, 'Permiso denegado.');

        return false;
    }

    private function requireRoot(SenderView $sender): bool
    {
        $actor = $this->actor($sender);
        if ($this->authorization->root($actor)->granted) {
            return true;
        }

        $this->reply($sender, 'Permiso denegado.');

        return false;
    }

    private function actor(SenderView $sender): OperatorActor
    {
        $accountId = $this->nickAccounts->findIdByNick($sender->nick);

        return new OperatorActor(
            nickname: $sender->nick,
            identifiedAccountId: $accountId,
            identified: $sender->isIdentified,
            ircOperator: $sender->isOper,
        );
    }

    private function reply(SenderView $sender, string $message): void
    {
        $this->bot->sendMessage($sender->uid, $message, 'PRIVMSG');
    }
}
