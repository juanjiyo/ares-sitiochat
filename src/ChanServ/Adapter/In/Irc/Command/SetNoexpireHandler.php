<?php

declare(strict_types=1);

namespace App\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Application\UseCase\UpdateSetting\ChannelSetting;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSettingHandlerInterface;
use App\ChanServ\Domain\Entity\RegisteredChannel;

use function strtoupper;
use function trim;

/**
 * SET <#channel> NOEXPIRE ON|OFF.
 *
 * Mirrors the ch.mrc option and the standalone NOEXPIRE command: the stored flag
 * keeps the channel out of the inactivity sweep.
 */
final readonly class SetNoexpireHandler implements SetOptionHandlerInterface
{
    use BuildsUpdateChannelSettingRequest;

    public function __construct(private UpdateChannelSettingHandlerInterface $handler) {}

    public function handle(ChanServContext $context, RegisteredChannel $channel, string $value): void
    {
        $normalized = strtoupper(trim($value));
        if ('ON' !== $normalized && 'OFF' !== $normalized) {
            $context->reply('error.syntax', ['syntax' => $context->trans('set.noexpire.syntax')]);

            return;
        }

        $request = $this->settingRequest($context, $channel, ChannelSetting::NoExpire, $normalized);
        if (null === $request) {
            return;
        }

        $this->handler->handle($request);
        $context->reply('ON' === $normalized ? 'set.noexpire.on' : 'set.noexpire.off');
    }
}
