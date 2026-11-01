<?php

declare(strict_types=1);

namespace App\Irc\Adapter\Protocol\UnrealUdb;

use App\Irc\Adapter\Protocol\UnrealUdb\Synchronization\UdbRecordWriterInterface;
use App\Irc\Application\Port\In\PasswordFloodSettingPort;

use function sprintf;

/** Publishes the password brute-force threshold as the UDB S::flood record. */
final readonly class UdbPasswordFloodSetting implements PasswordFloodSettingPort
{
    public function __construct(
        private UdbRecordWriterInterface $recordWriter,
    ) {}

    public function set(int $attempts, int $seconds): bool
    {
        return $this->recordWriter->insert('S', 'flood', sprintf('%d:%d', $attempts, $seconds));
    }
}
