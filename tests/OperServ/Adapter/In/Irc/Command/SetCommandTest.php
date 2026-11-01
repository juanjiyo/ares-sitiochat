<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\SetCommand;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\PasswordFloodSettings;
use App\OperServ\Application\UseCase\SetPassProtect\SetPassProtectHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;

#[CoversClass(SetCommand::class)]
final class SetCommandTest extends TestCase
{
    #[Test]
    public function exposesRootOnlyHelpMetadata(): void
    {
        $command = $this->command(true);

        self::assertSame('SET', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('set.syntax', $command->getSyntaxKey());
        self::assertSame('set.help', $command->getHelpKey());
        self::assertSame('set.example', $command->getExampleKey());
        self::assertSame(41, $command->getOrder());
        self::assertSame('set.short', $command->getShortDescKey());
        self::assertSame([
            ['name' => 'PASSPROTECT', 'desc_key' => 'set.passprotect.short', 'help_key' => 'set.passprotect.help', 'syntax_key' => 'set.passprotect.syntax', 'example_key' => 'set.passprotect.example'],
        ], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame('operserv.set', $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function setsThePasswordFloodThreshold(): void
    {
        $settings = new SetCommandTestSettings(true);
        $harness = new ChannelOverrideCommandHarness();

        $this->command(true, $settings)->execute($harness->context('SET', ['passprotect', '3:120'], $this->auth()));

        self::assertSame([[3, 120]], $settings->calls);
        self::assertSame(['set.passprotect.done'], array_column($harness->replies, 0));
        self::assertSame('3:120', $harness->replies[0][1]['%value%']);
    }

    #[Test]
    public function reportsAnInvalidValue(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command(true)->execute($harness->context('SET', ['PASSPROTECT', '0:60'], $this->auth()));

        self::assertSame(['set.passprotect.invalid'], array_column($harness->replies, 0));
    }

    #[Test]
    public function reportsANetworkRejection(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command(false)->execute($harness->context('SET', ['PASSPROTECT', '5:60'], $this->auth()));

        self::assertSame(['set.passprotect.rejected'], array_column($harness->replies, 0));
    }

    #[Test]
    public function asksForTheValueWhenMissing(): void
    {
        $settings = new SetCommandTestSettings(true);
        $harness = new ChannelOverrideCommandHarness();

        $this->command(true, $settings)->execute($harness->context('SET', ['PASSPROTECT'], $this->auth()));

        self::assertSame([], $settings->calls);
        self::assertSame('error.syntax', $harness->replies[1][0]);
        self::assertSame('set.passprotect.syntax', $harness->replies[0][0]);
    }

    #[Test]
    public function rejectsUnknownSettings(): void
    {
        $settings = new SetCommandTestSettings(true);
        $harness = new ChannelOverrideCommandHarness();

        $this->command(true, $settings)->execute($harness->context('SET', ['PREFIJOS', '@'], $this->auth()));

        self::assertSame([], $settings->calls);
        self::assertSame(['set.unknown_sub'], array_column($harness->replies, 0));
    }

    #[Test]
    public function ignoresCallsWithoutSender(): void
    {
        $settings = new SetCommandTestSettings(true);
        $harness = new ChannelOverrideCommandHarness();

        $this->command(true, $settings)->execute($harness->context('SET', ['PASSPROTECT', '5:60'], $this->auth(), false));

        self::assertSame([], $settings->calls);
        self::assertSame([], $harness->replies);
    }

    private function command(bool $accept, ?SetCommandTestSettings $settings = null): SetCommand
    {
        $audit = new class implements CommandAuditRecorder {
            public function record(CommandAuditRecord $record): void {}
        };

        return new SetCommand(new SetPassProtectHandler($settings ?? new SetCommandTestSettings($accept), $audit));
    }

    private function auth(): OperatorAuthorizationQuery
    {
        return $this->createStub(OperatorAuthorizationQuery::class);
    }
}

final class SetCommandTestSettings implements PasswordFloodSettings
{
    /** @var list<array{int, int}> */
    public array $calls = [];

    public function __construct(private readonly bool $accept) {}

    public function set(int $attempts, int $seconds): bool
    {
        $this->calls[] = [$attempts, $seconds];

        return $this->accept;
    }
}
