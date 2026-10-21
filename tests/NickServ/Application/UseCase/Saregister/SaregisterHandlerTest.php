<?php

declare(strict_types=1);

namespace App\Tests\NickServ\Application\UseCase\Saregister;

use App\NickServ\Application\Port\Out\Clock;
use App\NickServ\Application\Port\Out\PasswordHasher;
use App\NickServ\Application\Port\Out\RegisterNickRepository;
use App\NickServ\Application\Port\Out\RegistrationEventPublisher;
use App\NickServ\Application\PublishedEvent\NickPasswordHashAvailable;
use App\NickServ\Application\Service\NickActivationPublisher;
use App\NickServ\Application\UseCase\Saregister\Saregister;
use App\NickServ\Application\UseCase\Saregister\SaregisterHandler;
use App\NickServ\Application\UseCase\Saregister\SaregisterOutcome;
use App\NickServ\Application\UseCase\Saregister\SaregisterResult;
use App\NickServ\Domain\Entity\RegisteredNick;
use App\NickServ\Domain\ValueObject\NickStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(NickActivationPublisher::class)]
#[CoversClass(SaregisterHandler::class)]
#[CoversClass(Saregister::class)]
#[CoversClass(SaregisterResult::class)]
final class SaregisterHandlerTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new DateTimeImmutable('2026-10-13 03:00:00 UTC');
    }

    #[Test]
    public function registersAnActiveNicknameWithoutVerificationOrMail(): void
    {
        $saved = null;

        $repository = $this->createMock(RegisterNickRepository::class);
        $repository->method('countByEmail')->willReturn(0);
        $repository->method('findByNick')->willReturn(null);
        $repository->expects(self::once())->method('save')->willReturnCallback(
            static function (RegisteredNick $nick) use (&$saved): void {
                $saved = self::withId($nick, 7);
            },
        );

        $result = $this->handler($repository)->handle($this->command());

        self::assertSame(SaregisterOutcome::Registered, $result->outcome);
        self::assertSame('NewNick', $result->nickname);
        self::assertInstanceOf(RegisteredNick::class, $saved);
        self::assertSame(NickStatus::Registered, $saved->getStatus());
        self::assertNull($saved->getExpiresAt());
        self::assertSame('user@example.com', $saved->getEmail());
        self::assertSame('hashed-password', $saved->getPasswordHash());
    }

    #[Test]
    public function ignoresThePerAddressLimitBecauseStaffRegistrationOverridesIt(): void
    {
        $saved = null;

        $repository = $this->createMock(RegisterNickRepository::class);
        // The address is shared far beyond any configured limit, and it still registers.
        $repository->method('countByEmail')->willReturn(99);
        $repository->method('findByNick')->willReturn(null);
        $repository->expects(self::once())->method('save')->willReturnCallback(
            static function (RegisteredNick $nick) use (&$saved): void {
                $saved = self::withId($nick, 7);
            },
        );

        $result = $this->handler($repository)->handle($this->command());

        self::assertSame(SaregisterOutcome::Registered, $result->outcome);
        self::assertInstanceOf(RegisteredNick::class, $saved);
    }

    #[Test]
    public function rejectsAnInvalidEmailBeforeTouchingStorage(): void
    {
        $repository = $this->createMock(RegisterNickRepository::class);
        $repository->expects(self::never())->method('save');

        $result = $this->handler($repository)->handle($this->command(email: 'not-an-address'));

        self::assertSame(SaregisterOutcome::InvalidEmail, $result->outcome);
    }

    #[Test]
    public function rejectsAnAlreadyRegisteredNickname(): void
    {
        $result = $this->rejectedForStatus(NickStatus::Registered);

        self::assertSame(SaregisterOutcome::AlreadyRegistered, $result->outcome);
    }

    #[Test]
    public function rejectsAPendingNickname(): void
    {
        $result = $this->rejectedForStatus(NickStatus::Pending);

        self::assertSame(SaregisterOutcome::AlreadyPending, $result->outcome);
    }

    #[Test]
    public function rejectsAForbiddenNickname(): void
    {
        $result = $this->rejectedForStatus(NickStatus::Forbidden);

        self::assertSame(SaregisterOutcome::Forbidden, $result->outcome);
    }

    #[Test]
    public function rejectsANicknamePendingDeletion(): void
    {
        $result = $this->rejectedForStatus(NickStatus::PendingDeletion);

        self::assertSame(SaregisterOutcome::PendingDeletion, $result->outcome);
    }

    private function rejectedForStatus(NickStatus $status): SaregisterResult
    {
        $existing = $this->createStub(RegisteredNick::class);
        $existing->method('getStatus')->willReturn($status);

        $repository = $this->createMock(RegisterNickRepository::class);
        $repository->method('countByEmail')->willReturn(0);
        $repository->method('findByNick')->willReturn($existing);
        $repository->expects(self::never())->method('save');

        return $this->handler($repository)->handle($this->command());
    }

    #[Test]
    public function publishesThePasswordHashSoTheNetworkProjectionIsWritten(): void
    {
        $saved = null;

        $repository = $this->createMock(RegisterNickRepository::class);
        $repository->method('findByNick')->willReturn(null);
        $repository->expects(self::once())->method('save')->willReturnCallback(
            static function (RegisteredNick $nick) use (&$saved): void {
                $saved = self::withId($nick, 7);
            },
        );

        $publisher = $this->createMock(RegistrationEventPublisher::class);
        $publisher->expects(self::once())->method('publish')->with(self::callback(
            static fn (NickPasswordHashAvailable $event): bool => 7 === $event->nickId
                && 'NewNick' === $event->nickname
                && 'hashed-password' === $event->passwordHash,
        ));

        $this->handler($repository, $publisher)->handle($this->command());
    }

    #[Test]
    public function doesNotPublishWhenTheNicknameAlreadyExists(): void
    {
        $repository = $this->createMock(RegisterNickRepository::class);
        $existing = $this->createStub(RegisteredNick::class);
        $existing->method('getStatus')->willReturn(NickStatus::Registered);
        $repository->method('findByNick')->willReturn($existing);
        $repository->expects(self::never())->method('save');

        $publisher = $this->createMock(RegistrationEventPublisher::class);
        $publisher->expects(self::never())->method('publish');

        $this->handler($repository, $publisher)->handle($this->command());
    }

    private static function withId(RegisteredNick $nick, int $id): RegisteredNick
    {
        new ReflectionProperty($nick, 'id')->setValue($nick, $id);

        return $nick;
    }

    private function handler(
        RegisterNickRepository $repository,
        ?RegistrationEventPublisher $publisher = null,
    ): SaregisterHandler {
        $hasher = $this->createStub(PasswordHasher::class);
        $hasher->method('hash')->willReturn('hashed-password');

        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn($this->now);

        return new SaregisterHandler(
            $repository,
            $hasher,
            $clock,
            new NickActivationPublisher($publisher ?? $this->createStub(RegistrationEventPublisher::class)),
        );
    }

    private function command(string $email = 'user@example.com'): Saregister
    {
        return new Saregister(
            nickname: 'NewNick',
            password: 'plain-password',
            email: $email,
            language: 'es',
            actorNickname: 'Oper',
            actorAccountId: 42,
            occurredAt: $this->now,
        );
    }
}
