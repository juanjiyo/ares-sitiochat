<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ManageSpamfilter;

use DateTimeImmutable;

/**
 * SPAMFILTER ADD [-regex] [+duration] <targets|ALL> <action> <pattern> <reason>,
 * DEL <number|pattern> or LIST [filter].
 */
final readonly class ManageSpamfilter
{
    public function __construct(
        public SpamfilterAction $action,
        public string $actor,
        public DateTimeImmutable $occurredAt,
        public ?string $targets = null,
        public ?string $spamAction = null,
        public ?string $pattern = null,
        public ?string $reason = null,
        public bool $regex = false,
        public ?string $duration = null,
        public ?string $item = null,
        public ?string $listFilter = null,
    ) {}
}
