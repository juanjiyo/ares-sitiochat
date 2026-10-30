<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ManageEline;

use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Port\Out\ElineNetworkActions;

final class RecordingElineNetwork implements ElineNetworkActions
{
    /** @var list<array{string, string}> */
    public array $calls = [];

    public bool $up = true;

    public function add(ElineEntry $entry): bool
    {
        if ($this->up) {
            $this->calls[] = ['add', $entry->mask];
        }

        return $this->up;
    }

    public function remove(string $mask, string $removedBy): void
    {
        $this->calls[] = ['remove', $mask . ' by ' . $removedBy];
    }
}
