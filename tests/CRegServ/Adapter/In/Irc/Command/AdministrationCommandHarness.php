<?php

declare(strict_types=1);

namespace App\Tests\CRegServ\Adapter\In\Irc\Command;

use App\CRegServ\Adapter\In\Irc\CRegServCommandRegistry;
use App\CRegServ\Adapter\In\Irc\CRegServContext;
use App\CRegServ\Adapter\In\Irc\CRegServNotifierInterface;
use App\CRegServ\Application\Model\CRegServAccountView;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_diff_key;

/** Builds CReG contexts for the IG and APODERA command tests and records every reply with its parameters. */
final class AdministrationCommandHarness implements TranslatorInterface, CRegServNotifierInterface
{
    /** @var list<array{string, array<string, mixed>}> */
    public array $replies = [];

    /** @param list<string> $arguments */
    public function context(string $command, array $arguments): CRegServContext
    {
        return new CRegServContext(
            new SenderView('UID1', 'NetAdmin', 'i', 'h', 'c', 'ip', true, true),
            new CRegServAccountView(5, 'NetAdmin', 'es'),
            $command,
            $arguments,
            $this,
            $this,
            'es',
            'UTC',
            'NOTICE',
            new CRegServCommandRegistry([]),
            new ServiceNicknameRegistry([]),
        );
    }

    /** @param array<string, mixed> $parameters */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $this->replies[] = [$id, array_diff_key($parameters, ['%bot%' => true, '%nickserv%' => true, '%chanserv%' => true])];

        return $id;
    }

    public function getLocale(): string
    {
        return 'es';
    }

    public function getNick(): string
    {
        return 'CReG';
    }

    public function getServiceKey(): string
    {
        return 'cregserv';
    }

    public function sendNotice(string $targetUidOrNick, string $message): void {}

    public function sendMessage(string $targetUidOrNick, string $message, string $messageType): void {}
}
