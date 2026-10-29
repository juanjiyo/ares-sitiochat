<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use App\OperServ\Adapter\In\Irc\OperServCommandRegistry;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Adapter\In\Irc\OperServNotifierInterface;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Builds OperServ contexts for channel override command tests and records every presented reply. */
final class ChannelOverrideCommandHarness implements TranslatorInterface, OperServNotifierInterface
{
    /** @var list<array{string, array<string, mixed>}> */
    public array $replies = [];

    /** @var list<string> */
    public array $notices = [];

    /** @param list<string> $arguments */
    public function context(string $command, array $arguments, OperatorAuthorizationQuery $authorization, bool $withSender = true): OperServContext
    {
        return new OperServContext(
            $withSender ? new SenderView('UIDOPER', 'Oper', 'ident', 'host', 'cloak', 'ip', true, true) : null,
            null,
            $command,
            $arguments,
            $this,
            $this,
            'en',
            'UTC',
            'NOTICE',
            new OperServCommandRegistry([]),
            new ServiceNicknameRegistry([]),
            $authorization,
        );
    }

    /** @param array<string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $this->replies[] = [$id, $parameters];

        return $id;
    }

    public function getLocale(): string
    {
        return 'en';
    }

    public function sendNotice(string $targetUidOrNick, string $message): void
    {
        $this->notices[] = $message;
    }

    public function sendMessage(string $targetUidOrNick, string $message, string $messageType): void
    {
        $this->notices[] = $message;
    }

    public function getNick(): string
    {
        return 'OperServ';
    }

    public function getUid(): string
    {
        return '001OS';
    }

    public function getServiceKey(): string
    {
        return 'operserv';
    }
}
