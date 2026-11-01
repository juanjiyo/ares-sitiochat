<?php

declare(strict_types=1);

namespace App\Irc\Application\Port\In;

use DateTimeImmutable;

/** A G-Line placed by the IRCd UDB module after too many failed password attempts from one IP. */
final readonly class PasswordFloodGline
{
    public function __construct(
        public string $user,
        public string $host,
        public string $reason,
        public ?DateTimeImmutable $expiresAt,
    ) {}

    public function mask(): string
    {
        return $this->user . '@' . $this->host;
    }
}
