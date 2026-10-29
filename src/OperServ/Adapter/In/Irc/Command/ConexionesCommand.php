<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\OperServCommandInterface;
use App\OperServ\Adapter\In\Irc\OperServContext;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\Clones\ConnectionSessionsHandler;

use function ctype_digit;
use function strtoupper;
use function trim;

/** IRC adapter for CONEXIONES LIST <minimum> | VIEW <host>: simultaneous sessions per host. */
final readonly class ConexionesCommand implements OperServCommandInterface
{
    public function __construct(private ConnectionSessionsHandler $handler) {}

    public function getName(): string
    {
        return 'CONEXIONES';
    }

    public function getAliases(): array
    {
        return [];
    }

    public function getMinArgs(): int
    {
        return 1;
    }

    public function getSyntaxKey(): string
    {
        return 'conexiones.syntax';
    }

    public function getHelpKey(): string
    {
        return 'conexiones.help';
    }

    public function getExampleKey(): string
    {
        return 'conexiones.example';
    }

    public function getOrder(): int
    {
        return 33;
    }

    public function getShortDescKey(): string
    {
        return 'conexiones.short';
    }

    public function getSubCommandHelp(): array
    {
        return [
            ['name' => 'LIST', 'desc_key' => 'conexiones.list.short', 'help_key' => 'conexiones.list.help', 'syntax_key' => 'conexiones.list.syntax', 'example_key' => 'conexiones.list.example'],
            ['name' => 'VIEW', 'desc_key' => 'conexiones.view.short', 'help_key' => 'conexiones.view.help', 'syntax_key' => 'conexiones.view.syntax', 'example_key' => 'conexiones.view.example'],
        ];
    }

    public function isOperOnly(): bool
    {
        return true;
    }

    public function getRequiredPermission(): string
    {
        return OperServPermission::CONEXIONES;
    }

    /** @return array<string, string> */
    public function getHelpParams(): array
    {
        return [];
    }

    public function execute(OperServContext $context): void
    {
        $value = trim($context->args[1] ?? '');

        match (strtoupper($context->args[0])) {
            'LIST' => '' === $value ? $this->syntaxError($context, 'conexiones.list.syntax') : $this->list($context, $value),
            'VIEW' => '' === $value ? $this->syntaxError($context, 'conexiones.view.syntax') : $this->view($context, $value),
            default => $this->syntaxError($context, 'conexiones.syntax'),
        };
    }

    private function list(OperServContext $context, string $minimum): void
    {
        if (!ctype_digit($minimum) || 2 > (int) $minimum) {
            $context->reply('conexiones.list.invalid_minimum');

            return;
        }

        $hosts = $this->handler->hostsWithAtLeast((int) $minimum);
        if ([] === $hosts) {
            $context->reply('conexiones.list.empty', ['minimum' => $minimum]);

            return;
        }

        $context->reply('conexiones.list.header', ['minimum' => $minimum]);
        foreach ($hosts as $host) {
            $context->reply('conexiones.list.entry', ['count' => (string) $host->sessions, 'host' => $host->host]);
        }
        $context->reply('conexiones.list.footer');
    }

    private function view(OperServContext $context, string $host): void
    {
        $sessions = $this->handler->sessionsOn($host);
        if (0 === $sessions) {
            $context->reply('conexiones.view.not_found', ['host' => $host]);

            return;
        }

        $context->reply('conexiones.view.result', ['host' => $host, 'count' => (string) $sessions]);
    }

    private function syntaxError(OperServContext $context, string $syntaxKey): void
    {
        $context->reply('error.syntax', ['syntax' => $context->trans($syntaxKey)]);
    }
}
