<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\ElineCommand;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Security\OperServPermission;
use App\OperServ\Application\UseCase\ManageEline\ManageElineHandler;
use App\Tests\OperServ\Application\UseCase\ChannelOverride\RecordingCommandAuditRecorder;
use App\Tests\OperServ\Application\UseCase\ManageEline\InMemoryElineRepository;
use App\Tests\OperServ\Application\UseCase\ManageEline\RecordingElineNetwork;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_diff_key;
use function array_map;

#[CoversClass(ElineCommand::class)]
final class ElineCommandTest extends TestCase
{
    private InMemoryElineRepository $elines;

    private RecordingElineNetwork $network;

    protected function setUp(): void
    {
        $this->elines = new InMemoryElineRepository();
        $this->network = new RecordingElineNetwork();
    }

    /**
     * @param list<string> $args
     *
     * @return list<array{string, array<string, mixed>}>
     */
    private function run_(array $args, bool $withSender = true): array
    {
        $harness = new ChannelOverrideCommandHarness();
        new ElineCommand(new ManageElineHandler($this->elines, $this->network, new RecordingCommandAuditRecorder()))
            ->execute($harness->context('ELINE', $args, $this->createStub(OperatorAuthorizationQuery::class), $withSender));

        return array_map(static fn (array $reply): array => [$reply[0], array_diff_key($reply[1], ['%bot%' => true])], $harness->replies);
    }

    #[Test]
    public function exposesOperOnlyPermissionAndHelpMetadata(): void
    {
        $command = new ElineCommand(new ManageElineHandler($this->elines, $this->network, new RecordingCommandAuditRecorder()));

        self::assertSame('ELINE', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame(24, $command->getOrder());
        self::assertSame(['eline.syntax', 'eline.help', 'eline.example', 'eline.short'], [$command->getSyntaxKey(), $command->getHelpKey(), $command->getExampleKey(), $command->getShortDescKey()]);
        self::assertSame(['ADD', 'DEL', 'LIST', 'VIEW', 'COUNT', 'CLEAR'], array_column($command->getSubCommandHelp(), 'name'));
        self::assertSame('eline.add.syntax', $command->getSubCommandHelp()[0]['syntax_key']);
        self::assertTrue($command->isOperOnly());
        self::assertSame(OperServPermission::ELINE, $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function addsWithAndWithoutExpiry(): void
    {
        $permanent = $this->run_(['add', '203.0.113.10', 'ZNC', 'IRCops']);
        $temporary = $this->run_(['ADD', '+30d', 'znc@198.51.100.1', 'Bouncer']);

        self::assertSame('eline.added', $permanent[1][0]);
        self::assertSame(['%mask%' => '*@203.0.113.10', '%expiration%' => 'eline.never_expires'], $permanent[1][1]);
        self::assertSame('ZNC IRCops', $this->elines->entries['*@203.0.113.10']->reason);
        self::assertSame('eline.added', $temporary[0][0]);
        self::assertSame('znc@198.51.100.1', $temporary[0][1]['%mask%']);
        self::assertNotNull($this->elines->entries['znc@198.51.100.1']->expiresAt);
    }

    #[Test]
    public function reportsEveryRefusal(): void
    {
        $this->run_(['ADD', '1.2.3.4', 'r']);

        self::assertSame([['eline.already_exists', ['%mask%' => '*@1.2.3.4']]], $this->run_(['ADD', '1.2.3.4', 'r']));
        self::assertSame([['eline.invalid_mask', ['%mask%' => 'host.example']]], $this->run_(['ADD', 'host.example', 'r']));
        self::assertSame([['eline.invalid_expiry', ['%expiry%' => '+ya']]], $this->run_(['ADD', '+ya', '5.6.7.8', 'r']));
        self::assertSame([['eline.not_found', ['%item%' => '9']]], $this->run_(['DEL', '9']));

        $this->network->up = false;
        self::assertSame([], $this->run_(['ADD', '9.9.9.9', 'r']));
    }

    #[Test]
    public function rejectsMalformedRequestsWithTheSyntax(): void
    {
        foreach ([['ADD', '1.2.3.4'], ['ADD', '+1d', '1.2.3.4'], ['DEL'], ['FOO']] as $args) {
            self::assertSame(['eline.syntax', 'error.syntax'], array_column($this->run_($args), 0));
        }
    }

    #[Test]
    public function deletesListsViewsAndCounts(): void
    {
        $this->elines->save(new ElineEntry('*@1.1.1.1', 'Oper', 'ZNC', new DateTimeImmutable('-1 hour'), null));
        $this->elines->save(new ElineEntry('*@2.2.2.2', 'Root', 'Bouncer', new DateTimeImmutable(), new DateTimeImmutable('+1 day')));

        $list = $this->run_(['LIST']);
        self::assertSame(['eline.list.header', 'eline.never_expires', 'eline.list.entry', 'eline.list.entry'], array_column($list, 0));
        self::assertSame(['%count%' => '2'], $list[0][1]);
        self::assertSame(['%number%' => '1', '%mask%' => '*@1.1.1.1', '%expiration%' => 'eline.never_expires'], $list[2][1]);

        $view = $this->run_(['VIEW']);
        self::assertSame('eline.view.entry', $view[3][0]);
        self::assertSame(['Root', 'Bouncer'], [$view[3][1]['%setby%'], $view[3][1]['%reason%']]);

        self::assertSame([['eline.count.result', ['%count%' => '2']]], $this->run_(['COUNT']));
        self::assertSame([['eline.deleted', ['%masks%' => '*@1.1.1.1, *@2.2.2.2']]], $this->run_(['DEL', '1-2']));
        self::assertSame([['eline.list.empty', []]], $this->run_(['LIST']));

        $this->elines->save(new ElineEntry('*@3.3.3.3', 'Oper', 'r', new DateTimeImmutable(), null));
        self::assertSame([['eline.clear.done', ['%count%' => '1']]], $this->run_(['clear']));
        self::assertSame([], $this->elines->entries);
    }

    #[Test]
    public function doesNothingWithoutASender(): void
    {
        self::assertSame([], $this->run_(['LIST'], false));
    }
}
