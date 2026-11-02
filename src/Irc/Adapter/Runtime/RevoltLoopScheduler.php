<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Runtime;

use App\Irc\Application\Port\In\DelayedTaskPort;
use Closure;
use Revolt\EventLoop;

final readonly class RevoltLoopScheduler implements LoopSchedulerInterface, DelayedTaskPort
{
    public function repeat(float $intervalSeconds, Closure $callback): string
    {
        return EventLoop::repeat($intervalSeconds, $callback);
    }

    public function delay(float $delaySeconds, Closure $callback): string
    {
        return EventLoop::delay($delaySeconds, $callback);
    }

    public function runLater(float $delaySeconds, Closure $task): void
    {
        EventLoop::delay($delaySeconds, static function () use ($task): void {
            $task();
        });
    }

    public function cancel(string $watcherId): void
    {
        EventLoop::cancel($watcherId);
    }
}
