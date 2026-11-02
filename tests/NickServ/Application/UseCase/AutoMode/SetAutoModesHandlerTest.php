<?php

declare(strict_types=1);

namespace App\Tests\NickServ\Application\UseCase\AutoMode;

use App\Irc\Adapter\Protocol\UnrealUdb\Model\UdbSchema;
use App\NickServ\Application\Port\Out\NickProtectionExemption;
use App\NickServ\Application\Port\Out\NickServEventPublisher;
use App\NickServ\Application\Port\Out\RegisteredNickRepositoryInterface;
use App\NickServ\Application\PublishedEvent\NickAutoModesChangedEvent;
use App\NickServ\Application\Service\NickTargetValidator;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModes;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesHandler;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesOutcome;
use App\NickServ\Application\UseCase\AutoMode\SetAutoModesResult;
use App\NickServ\Domain\Entity\RegisteredNick;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(SetAutoModesHandler::class)]
#[CoversClass(SetAutoModes::class)]
#[CoversClass(SetAutoModesResult::class)]
#[CoversClass(NickAutoModesChangedEvent::class)]
final class SetAutoModesHandlerTest extends TestCase
{
    /** @var list<object> */
    private array $events = [];

    private int $saves = 0;

    /** @return iterable<string, array{string, string}> */
    public static function validModes(): iterable
    {
        yield 'with plus' => ['+B', '+B'];
        yield 'without plus' => ['B', '+B'];
        yield 'several letters, duplicates removed' => ['+BpB', '+Bp'];
    }

    #[Test]
    #[DataProvider('validModes')]
    public function storesTheModesAndPublishesTheChange(string $given, string $stored): void
    {
        $account = $this->account();

        $result = $this->handler($account)->handle(new SetAutoModes('Pedro', $given));

        self::assertSame(SetAutoModesOutcome::Set, $result->outcome);
        self::assertSame('Pedro', $result->nickname);
        self::assertSame($stored, $result->modes);
        self::assertSame($stored, $account->getAutoModes());
        self::assertSame(1, $this->saves);
        self::assertSame([new NickAutoModesChangedEvent(7, 'Pedro', $stored)], $this->events);
    }

    #[Test]
    public function anEmptyValueClearsTheModes(): void
    {
        $account = $this->account();
        $account->changeAutoModes('+B');

        $result = $this->handler($account)->handle(new SetAutoModes('Pedro', '  '));

        self::assertSame(SetAutoModesOutcome::Cleared, $result->outcome);
        self::assertNull($account->getAutoModes());
        self::assertSame(1, $this->saves);
        self::assertSame([new NickAutoModesChangedEvent(7, 'Pedro', null)], $this->events);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidModes(): iterable
    {
        yield 'oper mode' => ['+o', 'o'];
        yield 'unknown letters' => ['+Bxo', 'xo'];
        yield 'removal' => ['-B', '-B'];
        yield 'mixed signs' => ['+B-i', '+B-i'];
        yield 'digits' => ['+B1', '+B1'];
    }

    #[Test]
    #[DataProvider('invalidModes')]
    public function rejectsInvalidModesWithoutStoringAnything(string $given, string $invalid): void
    {
        $account = $this->account();

        $result = $this->handler($account)->handle(new SetAutoModes('Pedro', $given));

        self::assertSame(SetAutoModesOutcome::InvalidModes, $result->outcome);
        self::assertSame($invalid, $result->invalidModes);
        self::assertNull($account->getAutoModes());
        self::assertSame(0, $this->saves);
        self::assertSame([], $this->events);
    }

    /** @return iterable<string, array{bool, bool, bool, SetAutoModesOutcome}> */
    public static function protectedTargets(): iterable
    {
        yield 'root' => [true, false, false, SetAutoModesOutcome::TargetIsRoot];
        yield 'service' => [false, true, false, SetAutoModesOutcome::TargetIsService];
        yield 'ircop' => [false, false, true, SetAutoModesOutcome::TargetIsIrcop];
    }

    #[Test]
    #[DataProvider('protectedTargets')]
    public function refusesProtectedTargets(bool $root, bool $service, bool $ircop, SetAutoModesOutcome $expected): void
    {
        $account = $this->account();
        $exemption = $this->createStub(NickProtectionExemption::class);
        $exemption->method('isRootNickname')->willReturn($root);
        $exemption->method('isServiceNickname')->willReturn($service);
        $exemption->method('isIrcopNickId')->willReturn($ircop);

        $result = $this->handler($account, $exemption)->handle(new SetAutoModes('Pedro', '+B'));

        self::assertSame($expected, $result->outcome);
        self::assertNull($account->getAutoModes());
        self::assertSame(0, $this->saves);
    }

    #[Test]
    public function refusesUnregisteredForbiddenAndPendingNicks(): void
    {
        $forbidden = RegisteredNick::createForbidden('Pedro', 'reason');
        $pending = RegisteredNick::createPending('Pedro', 'hash', 'p@example.org', 'es', new DateTimeImmutable('+1 day'), new DateTimeImmutable());

        foreach ([$forbidden, $pending] as $i => $account) {
            new ReflectionClass(RegisteredNick::class)->getProperty('id')->setValue($account, 20 + $i);
        }

        foreach ([null, $forbidden, $pending] as $account) {
            $result = $this->handler($account)->handle(new SetAutoModes('Pedro', '+B'));

            self::assertSame(SetAutoModesOutcome::NotRegistered, $result->outcome);
        }

        self::assertSame(0, $this->saves);
    }

    #[Test]
    public function allowedModesAreTheUdbProfileModes(): void
    {
        self::assertSame(UdbSchema::USER_MODES, SetAutoModesHandler::ALLOWED_MODES);
        self::assertNotContains('o', SetAutoModesHandler::ALLOWED_MODES);
    }

    private function account(): RegisteredNick
    {
        $account = RegisteredNick::createActive('Pedro', 'hash', 'p@example.org', 'es', new DateTimeImmutable());
        new ReflectionClass(RegisteredNick::class)->getProperty('id')->setValue($account, 7);

        return $account;
    }

    private function handler(?RegisteredNick $account, ?NickProtectionExemption $exemption = null): SetAutoModesHandler
    {
        $repository = $this->createStub(RegisteredNickRepositoryInterface::class);
        $repository->method('findByNick')->willReturn($account);
        $repository->method('save')->willReturnCallback(function (): void {
            ++$this->saves;
        });
        $events = $this->createStub(NickServEventPublisher::class);
        $events->method('publish')->willReturnCallback(function (object $event): void {
            $this->events[] = $event;
        });

        return new SetAutoModesHandler(
            $repository,
            new NickTargetValidator($exemption ?? $this->createStub(NickProtectionExemption::class), $repository),
            $events,
        );
    }
}
