<?php

declare(strict_types=1);

namespace App\Irc\Application\Port\In;

use Closure;

/** Runs a task on the event loop after a delay, without blocking it. */
interface DelayedTaskPort
{
    /** @param Closure(): void $task */
    public function runLater(float $delaySeconds, Closure $task): void;
}
