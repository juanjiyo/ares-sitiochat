<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Domain\Policy;

use App\OperServ\Domain\Policy\ElineMaskPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElineMaskPolicy::class)]
final class ElineMaskPolicyTest extends TestCase
{
    /** @return iterable<string, array{string, string, string, bool}> */
    public static function connections(): iterable
    {
        yield 'exact IPv4' => ['*@46.39.209.77', 'irc', '46.39.209.77', true];
        yield 'other IPv4' => ['*@46.39.209.77', 'irc', '46.39.209.78', false];
        yield 'byte-aligned CIDR' => ['*@46.39.0.0/16', 'irc', '46.39.209.77', true];
        yield 'CIDR with partial byte' => ['*@46.39.208.0/23', 'irc', '46.39.209.77', true];
        yield 'outside partial byte' => ['*@46.39.210.0/23', 'irc', '46.39.209.77', false];
        yield 'zero prefix' => ['*@0.0.0.0/0', 'irc', '46.39.209.77', true];
        yield 'IPv6 range' => ['*@2001:db8::/32', 'irc', '2001:db8:1::5', true];
        yield 'family mismatch' => ['*@2001:db8::/32', 'irc', '46.39.209.77', false];
        yield 'ident must match' => ['znc@46.39.209.77', 'irc', '46.39.209.77', false];
        yield 'ident wildcard is case-insensitive' => ['ZN*@46.39.209.77', 'znc', '46.39.209.77', true];
        yield 'mask without @' => ['46.39.209.77', 'irc', '46.39.209.77', false];
        yield 'unknown connection IP' => ['*@46.39.209.77', 'irc', '', false];
        yield 'host is not an IP' => ['*@example.test', 'irc', '46.39.209.77', false];
    }

    #[Test]
    #[DataProvider('connections')]
    public function coversOnlyConnectionsInsideTheRange(string $mask, string $ident, string $ip, bool $covered): void
    {
        self::assertSame($covered, ElineMaskPolicy::covers($mask, $ident, $ip));
    }
}
