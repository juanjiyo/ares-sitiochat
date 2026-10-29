<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\ChannelOverride;

use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;

/** Keeps every audit record so tests can assert the complete audit trail. */
final class RecordingCommandAuditRecorder implements CommandAuditRecorder
{
    /** @var list<CommandAuditRecord> */
    public array $records = [];

    public function record(CommandAuditRecord $record): void
    {
        $this->records[] = $record;
    }
}
