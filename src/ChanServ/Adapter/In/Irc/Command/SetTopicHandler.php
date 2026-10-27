<?php

declare(strict_types=1);

namespace App\ChanServ\Adapter\In\Irc\Command;

use App\ChanServ\Adapter\In\Irc\ChanServContext;
use App\ChanServ\Application\UseCase\ApplyStoredTopic\ApplyStoredChannelTopic;
use App\ChanServ\Application\UseCase\ApplyStoredTopic\ApplyStoredChannelTopicHandlerInterface;
use App\ChanServ\Application\UseCase\ApplyStoredTopic\StoredTopicApplicationTrigger;
use App\ChanServ\Application\UseCase\UpdateSetting\ChannelSetting;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSettingHandlerInterface;
use App\ChanServ\Application\UseCase\UpdateSetting\UpdateChannelSettingOutcome;
use App\ChanServ\Domain\Entity\RegisteredChannel;

/**
 * SET <#channel> TOPIC [text].
 *
 * Stores the topic as the channel's official topic and applies it on the network.
 * An empty value clears it, matching the ch.mrc behaviour.
 */
final readonly class SetTopicHandler implements SetOptionHandlerInterface
{
    use BuildsUpdateChannelSettingRequest;

    public function __construct(
        private UpdateChannelSettingHandlerInterface $handler,
        private ApplyStoredChannelTopicHandlerInterface $topicApplier,
    ) {}

    public function handle(ChanServContext $context, RegisteredChannel $channel, string $value): void
    {
        $request = $this->settingRequest($context, $channel, ChannelSetting::Topic, $value);
        if (null === $request) {
            return;
        }

        $result = $this->handler->handle($request);

        $this->topicApplier->handle(new ApplyStoredChannelTopic(
            trigger: StoredTopicApplicationTrigger::ChannelSynchronized,
            channelName: $channel->getName(),
        ));

        $context->reply(
            UpdateChannelSettingOutcome::Cleared === $result->outcome
                ? 'set.topic.cleared'
                : 'set.topic.changed',
        );
    }
}
