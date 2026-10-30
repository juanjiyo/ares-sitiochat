<?php

declare(strict_types=1);

namespace App\OperServ\Domain\Entity;

use DateTimeImmutable;

/** A ban exception by IP (E-Line): users matching the user@ip mask skip the network bans. */
final class Eline
{
    private int $id;

    private function __construct(
        private string $mask,
        private string $setBy,
        private string $reason,
        private DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $expiresAt,
    ) {}

    public static function create(string $mask, string $setBy, string $reason, DateTimeImmutable $createdAt, ?DateTimeImmutable $expiresAt): self
    {
        return new self($mask, $setBy, $reason, $createdAt, $expiresAt);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMask(): string
    {
        return $this->mask;
    }

    public function getSetBy(): string
    {
        return $this->setBy;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
