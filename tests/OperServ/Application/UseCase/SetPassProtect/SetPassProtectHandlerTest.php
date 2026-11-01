<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\SetPassProtect;

use App\OperServ\Application\Port\In\Audit\CommandAuditCategory;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\In\OperatorAuthorizationAttribute;
use App\OperServ\Application\Port\Out\PasswordFloodSettings;
use App\OperServ\Application\UseCase\SetPassProtect\SetPassProtect;
use App\OperServ\Application\UseCase\SetPassProtect\SetPassProtectHandler;
use App\OperServ\Application\UseCase\SetPassProtect\SetPassProtectOutcome;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SetPassProtectHandler::class)]
#[CoversClass(SetPassProtect::class)]
final class SetPassProtectHandlerTest extends TestCase
{
    #[Test]
    public function publishesTheThresholdAndAuditsIt(): void
    {
        $settings = new RecordingPasswordFloodSettings(true);
        $audit = new RecordingPassProtectAudit();
        $at = new DateTimeImmutable('2026-11-01 12:00:00');

        $outcome = new SetPassProtectHandler($settings, $audit)->handle(new SetPassProtect('Root', $at, '3:120'));

        self::assertSame(SetPassProtectOutcome::Set, $outcome);
        self::assertSame([[3, 120]], $settings->calls);
        self::assertCount(1, $audit->records);
        self::assertSame(CommandAuditCategory::RootAdministration, $audit->records[0]->category);
        self::assertSame('Root', $audit->records[0]->actor);
        self::assertSame('SET PASSPROTECT', $audit->records[0]->operation);
        self::assertSame('3:120', $audit->records[0]->target);
        self::assertSame(OperatorAuthorizationAttribute::ROOT, $audit->records[0]->permission);
        self::assertSame($at, $audit->records[0]->occurredAt);
    }

    #[Test]
    public function acceptsTheLargestValue(): void
    {
        $settings = new RecordingPasswordFloodSettings(true);

        $outcome = new SetPassProtectHandler($settings, new RecordingPassProtectAudit())->handle(new SetPassProtect('Root', new DateTimeImmutable(), '2147483647:2147483647'));

        self::assertSame(SetPassProtectOutcome::Set, $outcome);
        self::assertSame([[2147483647, 2147483647]], $settings->calls);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidValues(): iterable
    {
        yield 'no separator' => ['5'];
        yield 'zero attempts' => ['0:60'];
        yield 'zero seconds' => ['5:0'];
        yield 'negative' => ['-5:60'];
        yield 'letters' => ['a:b'];
        yield 'three parts' => ['5:60:1'];
        yield 'attempts too big' => ['2147483648:60'];
        yield 'seconds too big' => ['5:2147483648'];
        yield 'trailing newline' => ["5:60\n"];
    }

    #[Test]
    #[DataProvider('invalidValues')]
    public function rejectsMalformedValuesWithoutTouchingTheNetwork(string $value): void
    {
        $settings = new RecordingPasswordFloodSettings(true);
        $audit = new RecordingPassProtectAudit();

        $outcome = new SetPassProtectHandler($settings, $audit)->handle(new SetPassProtect('Root', new DateTimeImmutable(), $value));

        self::assertSame(SetPassProtectOutcome::InvalidValue, $outcome);
        self::assertSame([], $settings->calls);
        self::assertSame([], $audit->records);
    }

    #[Test]
    public function reportsANetworkRejectionWithoutAuditing(): void
    {
        $settings = new RecordingPasswordFloodSettings(false);
        $audit = new RecordingPassProtectAudit();

        $outcome = new SetPassProtectHandler($settings, $audit)->handle(new SetPassProtect('Root', new DateTimeImmutable(), '5:60'));

        self::assertSame(SetPassProtectOutcome::Rejected, $outcome);
        self::assertSame([[5, 60]], $settings->calls);
        self::assertSame([], $audit->records);
    }
}

final class RecordingPasswordFloodSettings implements PasswordFloodSettings
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

final class RecordingPassProtectAudit implements CommandAuditRecorder
{
    /** @var list<CommandAuditRecord> */
    public array $records = [];

    public function record(CommandAuditRecord $record): void
    {
        $this->records[] = $record;
    }
}
