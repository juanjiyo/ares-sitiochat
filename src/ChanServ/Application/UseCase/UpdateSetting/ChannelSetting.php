<?php

declare(strict_types=1);

namespace App\ChanServ\Application\UseCase\UpdateSetting;

enum ChannelSetting
{
    case DefaultTopic;
    case Description;
    case Email;
    case EntryMessage;
    case KeepTopic;
    case NoExpire;
    case Private;
    case Topic;
    case Successor;
    case TopicLock;
    case Url;
}
