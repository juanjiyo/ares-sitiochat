<?php

declare(strict_types=1);

namespace App\NickServ\Application\PublishedEvent;

/** The automatic user modes of a registered nick changed (null: cleared). */
final readonly class NickAutoModesChangedEvent
{
    public function __construct(
        public int $nickId,
        public string $nickname,
        public ?string $modes,
    ) {}
}
