<?php

declare(strict_types=1);

namespace App\OperServ\Application\UseCase\ChannelOverride;

use DateTimeImmutable;

/** Typed intention to change channel modes (MODE) or clear restrictive ones (LIMPIA) through OperServ. */
final readonly class SetChannelModes
{
    /** Parameterless restrictive modes removed by a cleanup; -k and -l would need their arguments. */
    public const string CLEANUP_MODES = '-imnpst';

    /** @param list<string> $params */
    private function __construct(
        public string $actorNickname,
        public string $channelName,
        public string $modes,
        public array $params,
        public bool $cleanup,
        public DateTimeImmutable $occurredAt,
    ) {}

    /** @param list<string> $params */
    public static function custom(string $actorNickname, string $channelName, string $modes, array $params, DateTimeImmutable $occurredAt): self
    {
        return new self($actorNickname, $channelName, $modes, $params, false, $occurredAt);
    }

    public static function cleanup(string $actorNickname, string $channelName, DateTimeImmutable $occurredAt): self
    {
        return new self($actorNickname, $channelName, self::CLEANUP_MODES, [], true, $occurredAt);
    }
}
