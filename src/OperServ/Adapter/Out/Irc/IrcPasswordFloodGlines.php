<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\PasswordFloodGlinePort;
use App\OperServ\Application\Port\Out\IrcdGlineEntry;
use App\OperServ\Application\Port\Out\IrcdGlines;

use function strcasecmp;

final readonly class IrcPasswordFloodGlines implements IrcdGlines
{
    public function __construct(
        private PasswordFloodGlinePort $glines,
    ) {}

    public function all(): array
    {
        $entries = [];
        foreach ($this->glines->list() as $gline) {
            $entries[] = new IrcdGlineEntry($gline->mask(), $gline->reason, $gline->expiresAt);
        }

        return $entries;
    }

    public function remove(string $mask): bool
    {
        foreach ($this->glines->list() as $gline) {
            if (0 === strcasecmp($gline->mask(), $mask)) {
                $this->glines->remove($gline);

                return true;
            }
        }

        return false;
    }
}
