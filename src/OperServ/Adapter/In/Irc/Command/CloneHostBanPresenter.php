<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\UseCase\Clones\BanCloneHost;
use App\OperServ\Application\UseCase\Clones\BanCloneHostHandler;
use App\OperServ\Application\UseCase\Clones\BanCloneHostOutcome;
use App\OperServ\Application\UseCase\Clones\BanCloneHostResult;
use App\OperServ\Application\UseCase\Clones\CloneHostBan;
use DateTimeImmutable;

use function array_slice;
use function implode;

/** Parses and presents KILLCLONES and BLOCK, which only differ in the G-Line duration and the success reply. */
final class CloneHostBanPresenter
{
    private function __construct() {}

    /** Runs `<COMMAND> <nick> [reason]` for the given ban and presents its outcome. */
    public static function run(OperServContext $context, BanCloneHostHandler $handler, CloneHostBan $ban, string $doneKey): void
    {
        if (null === $context->sender) {
            return;
        }

        self::present($context, $handler->handle(new BanCloneHost(
            ban: $ban,
            actorNickname: $context->sender->nick,
            actorAccountId: $context->senderAccountId(),
            serviceNickname: $context->getBotName(),
            targetNickname: $context->args[0],
            reason: implode(' ', array_slice($context->args, 1)),
            occurredAt: new DateTimeImmutable(),
        )), $doneKey);
    }

    public static function present(OperServContext $context, BanCloneHostResult $result, string $doneKey): void
    {
        match ($result->outcome) {
            BanCloneHostOutcome::Banned => $context->reply($doneKey, [
                'nickname' => $result->nickname,
                'host' => $result->host,
                'mask' => $result->mask,
                'duration' => $result->duration,
                'reason' => $result->reason,
                'count' => (string) $result->sessionCount,
            ]),
            BanCloneHostOutcome::NotOnline => $context->reply('channel.user_not_online', ['nickname' => $result->nickname]),
            BanCloneHostOutcome::LoopbackHost => $context->reply('clones.loopback_host', ['host' => $result->host]),
            BanCloneHostOutcome::ProtectedRoot => $context->reply('channel.protected_root', ['nickname' => (string) $result->protectedNickname]),
            BanCloneHostOutcome::ProtectedIrcOperator => $context->reply('channel.protected_ircop', ['nickname' => (string) $result->protectedNickname]),
            BanCloneHostOutcome::ProtectedSubject => $context->reply('gline.protected_user', ['nickname' => (string) $result->protectedNickname]),
            BanCloneHostOutcome::AlreadyBanned => $context->reply('gline.already_exists', ['mask' => $result->mask]),
            BanCloneHostOutcome::UnsafeHost => $context->reply('gline.dangerous_mask', ['mask' => $result->mask]),
            BanCloneHostOutcome::LimitReached => $context->reply('gline.max_entries', ['max' => (string) $result->limit]),
            BanCloneHostOutcome::Failed => $context->reply('clones.failed', ['mask' => $result->mask]),
        };
    }
}
