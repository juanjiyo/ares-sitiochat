<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\Clones;

final readonly class ListClones
{
    public function __construct(public string $targetNickname) {}
}
