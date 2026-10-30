<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\Security;

use App\OperServ\Application\Security\UserModeRank;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserModeRank::class)]
final class UserModeRankTest extends TestCase
{
    /** @return iterable<string, array{?string, UserModeRank}> */
    public static function operclasses(): iterable
    {
        yield 'no operclass' => [null, UserModeRank::Ircop];
        yield 'locop' => ['locop', UserModeRank::Ircop];
        yield 'globop' => ['globop', UserModeRank::Operator];
        yield 'admin' => ['admin', UserModeRank::Administrator];
        yield 'services-admin' => ['services-admin', UserModeRank::Administrator];
        yield 'netadmin' => ['NetAdmin', UserModeRank::Administrator];
        yield 'override suffix' => ['netadmin-with-override', UserModeRank::Administrator];
        yield 'unknown' => ['custom', UserModeRank::Ircop];
    }

    #[Test]
    #[DataProvider('operclasses')]
    public function derivesTheRankFromTheRoleOperclass(?string $operclass, UserModeRank $rank): void
    {
        self::assertSame($rank, UserModeRank::fromOperclass($operclass));
    }

    /** @return iterable<string, array{string, UserModeRank}> */
    public static function modes(): iterable
    {
        foreach (['i', 'w', 'x', 'p', 'R', 'Z', 'D', 'G', 'T', 'B', 'd', 'I'] as $mode) {
            yield 'user ' . $mode => [$mode, UserModeRank::Ircop];
        }
        yield 'snomask' => ['s', UserModeRank::Operator];
        yield 'whois notice' => ['W', UserModeRank::Operator];
        yield 'hide oper' => ['H', UserModeRank::Administrator];
        yield 'unkickable' => ['q', UserModeRank::Administrator];
        yield 'oper' => ['o', UserModeRank::Administrator];
        yield 'service' => ['S', UserModeRank::Root];
        yield 'secure' => ['z', UserModeRank::Root];
        yield 'vhost' => ['t', UserModeRank::Root];
        yield 'unlisted' => ['Y', UserModeRank::Root];
    }

    #[Test]
    #[DataProvider('modes')]
    public function knowsTheMinimumRankOfEveryMode(string $mode, UserModeRank $rank): void
    {
        self::assertSame($rank, UserModeRank::requiredFor($mode));
    }

    #[Test]
    public function forbidsOnlyRegisteredAndHelperModes(): void
    {
        self::assertTrue(UserModeRank::isForbidden('r'));
        self::assertTrue(UserModeRank::isForbidden('h'));
        self::assertFalse(UserModeRank::isForbidden('o'));
    }
}
