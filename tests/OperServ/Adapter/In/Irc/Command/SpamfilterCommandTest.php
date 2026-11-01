<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\In\Irc\Command;

use App\OperServ\Adapter\In\Irc\Command\SpamfilterCommand;
use App\OperServ\Application\Port\In\Audit\CommandAuditRecord;
use App\OperServ\Application\Port\In\CommandAuditRecorder;
use App\OperServ\Application\Port\In\OperatorAuthorizationQuery;
use App\OperServ\Application\Port\Out\NetworkSpamfilters;
use App\OperServ\Application\Port\Out\SpamfilterEntry;
use App\OperServ\Application\UseCase\ManageSpamfilter\ManageSpamfilterHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_column;
use function count;

#[CoversClass(SpamfilterCommand::class)]
final class SpamfilterCommandTest extends TestCase
{
    #[Test]
    public function exposesHelpMetadataAndThePermission(): void
    {
        $command = $this->command(new SpamfilterCommandNetwork());

        self::assertSame('SPAMFILTER', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('spamfilter.syntax', $command->getSyntaxKey());
        self::assertSame('spamfilter.help', $command->getHelpKey());
        self::assertSame('spamfilter.example', $command->getExampleKey());
        self::assertSame(42, $command->getOrder());
        self::assertSame('spamfilter.short', $command->getShortDescKey());
        self::assertSame(['ADD', 'DEL', 'LIST'], array_column($command->getSubCommandHelp(), 'name'));
        self::assertSame('spamfilter.add.syntax', $command->getSubCommandHelp()[0]['syntax_key']);
        self::assertTrue($command->isOperOnly());
        self::assertSame('operserv.spamfilter', $command->getRequiredPermission());
        self::assertSame([], $command->getHelpParams());
    }

    #[Test]
    public function addParsesFlagsInAnyOrderAndTheMultiWordReason(): void
    {
        $network = new SpamfilterCommandNetwork();
        $harness = new ChannelOverrideCommandHarness();

        $this->command($network)->execute($harness->context('SPAMFILTER', ['add', '+2h', '-REGEX', 'cp', 'gline', 'casino[0-9]+', 'Casino', 'spam'], $this->auth()));

        self::assertCount(1, $network->added);
        $added = $network->added[0];
        self::assertSame(['cp', 'g', 'regex', 'casino[0-9]+', 7200, 'Casino spam', 'Oper'], [$added->targets, $added->action, $added->matchType, $added->match, $added->banDuration, $added->reason, $added->setBy]);
        self::assertSame(['spamfilter.add.done'], array_column($harness->replies, 0));
        self::assertSame('casino[0-9]+', $harness->replies[0][1]['%pattern%']);
        self::assertSame('gline', $harness->replies[0][1]['%action%']);
    }

    #[Test]
    public function addWithoutFlagsUsesSimpleMatching(): void
    {
        $network = new SpamfilterCommandNetwork();

        $this->command($network)->execute(new ChannelOverrideCommandHarness()->context('SPAMFILTER', ['ADD', 'ALL', 'warn', '*casino*', 'r'], $this->auth()));

        self::assertSame('simple', $network->added[0]->matchType);
        self::assertSame(86400, $network->added[0]->banDuration);
    }

    /** @return iterable<string, array{list<string>, string}> */
    public static function addReplies(): iterable
    {
        yield 'missing reason' => [['ADD', 'c', 'warn', '*x*'], 'error.syntax'];
        yield 'bad targets' => [['ADD', 'cx', 'warn', '*x*', 'r'], 'spamfilter.add.invalid_targets'];
        yield 'bad action' => [['ADD', 'c', 'nuke', '*x*', 'r'], 'spamfilter.add.invalid_action'];
        yield 'bad regex' => [['ADD', '-regex', 'c', 'warn', 'x(', 'r'], 'spamfilter.add.invalid_regex'];
        yield 'bad duration' => [['ADD', '+soon', 'c', 'gline', '*x*', 'r'], 'spamfilter.add.invalid_duration'];
        yield 'duplicate' => [['ADD', 'c', 'warn', '*dup*', 'r'], 'spamfilter.add.exists'];
        yield 'unknown sub' => [['NUKE'], 'spamfilter.unknown_sub'];
        yield 'missing del item' => [['DEL'], 'error.syntax'];
        yield 'unknown del item' => [['DEL', '9'], 'spamfilter.del.not_found'];
    }

    /** @param list<string> $args */
    #[Test]
    #[DataProvider('addReplies')]
    public function repliesWithTheOutcomeMessage(array $args, string $key): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command(new SpamfilterCommandNetwork([new SpamfilterEntry('c', 'w', 'simple', '*dup*', 0, 'r', 'A')]))
            ->execute($harness->context('SPAMFILTER', $args, $this->auth()));

        self::assertSame($key, $harness->replies[count($harness->replies) - 1][0]);
    }

    #[Test]
    public function syntaxErrorsShowTheSubcommandSyntax(): void
    {
        $add = new ChannelOverrideCommandHarness();
        $del = new ChannelOverrideCommandHarness();
        $command = $this->command(new SpamfilterCommandNetwork());

        $command->execute($add->context('SPAMFILTER', ['ADD'], $this->auth()));
        $command->execute($del->context('SPAMFILTER', ['DEL'], $this->auth()));

        self::assertSame(['spamfilter.add.syntax', 'error.syntax'], array_column($add->replies, 0));
        self::assertSame(['spamfilter.del.syntax', 'error.syntax'], array_column($del->replies, 0));
    }

    #[Test]
    public function invalidTargetsAndActionsListTheValidOnes(): void
    {
        $targets = new ChannelOverrideCommandHarness();
        $action = new ChannelOverrideCommandHarness();
        $command = $this->command(new SpamfilterCommandNetwork());

        $command->execute($targets->context('SPAMFILTER', ['ADD', 'x', 'warn', '*x*', 'r'], $this->auth()));
        $command->execute($action->context('SPAMFILTER', ['ADD', 'c', 'nuke', '*x*', 'r'], $this->auth()));

        self::assertSame('cpnNPqdat', $targets->replies[0][1]['%valid%']);
        self::assertSame('warn, block, kill, tempshun, shun, kline, gline, zline, gzline, dccblock, viruschan', $action->replies[0][1]['%valid%']);
    }

    #[Test]
    public function deletesAndConfirms(): void
    {
        $network = new SpamfilterCommandNetwork([new SpamfilterEntry('c', 'w', 'simple', '*casino*', 0, 'r', 'A')]);
        $harness = new ChannelOverrideCommandHarness();

        $this->command($network)->execute($harness->context('SPAMFILTER', ['DEL', '1'], $this->auth()));

        self::assertSame(['Oper'], array_column($network->removed, 1));
        self::assertSame(['spamfilter.del.done'], array_column($harness->replies, 0));
        self::assertSame('*casino*', $harness->replies[0][1]['%pattern%']);
    }

    #[Test]
    public function notFoundEchoesTheItem(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command(new SpamfilterCommandNetwork())->execute($harness->context('SPAMFILTER', ['DEL', '*nope*'], $this->auth()));

        self::assertSame('*nope*', $harness->replies[0][1]['%item%']);
    }

    #[Test]
    public function listsWithRealNumbersAndReadableFields(): void
    {
        $network = new SpamfilterCommandNetwork([
            new SpamfilterEntry('c', 'w', 'simple', '*free*', 86400, 'Free stuff', 'A'),
            new SpamfilterEntry('cpN', 'g', 'regex', 'casino[0-9]+', 7200, 'Casino', 'JuanJo'),
            new SpamfilterEntry('p', 'k', 'simple', '*casino2*', 1800, 'K', 'B'),
            new SpamfilterEntry('p', 's', 'simple', '*casino3*', 172800, 'S', 'B'),
        ]);
        $harness = new ChannelOverrideCommandHarness();

        $this->command($network)->execute($harness->context('SPAMFILTER', ['LIST', 'casino'], $this->auth()));

        self::assertSame(['spamfilter.list.header', 'spamfilter.list.entry', 'spamfilter.list.entry', 'spamfilter.list.entry'], array_column($harness->replies, 0));
        self::assertSame('3', $harness->replies[0][1]['%count%']);
        $entry = $harness->replies[1][1];
        self::assertSame(['2', 'casino[0-9]+', 'regex', 'cpN', 'gline', '2h', 'Casino', 'JuanJo'], [
            $entry['%number%'], $entry['%pattern%'], $entry['%type%'], $entry['%targets%'], $entry['%action%'], $entry['%duration%'], $entry['%reason%'], $entry['%by%'],
        ]);
        self::assertSame('30m', $harness->replies[2][1]['%duration%']);
        self::assertSame('2d', $harness->replies[3][1]['%duration%']);
    }

    #[Test]
    public function listShowsNoDurationForActionsWithoutBan(): void
    {
        $harness = new ChannelOverrideCommandHarness();

        $this->command(new SpamfilterCommandNetwork([new SpamfilterEntry('c', 'w', 'simple', '*free*', 86400, 'r', 'A')]))
            ->execute($harness->context('SPAMFILTER', ['LIST'], $this->auth()));

        self::assertSame('-', $harness->replies[1][1]['%duration%']);
    }

    #[Test]
    public function listReportsEmptyAndUnlinkedReportsUnavailable(): void
    {
        $empty = new ChannelOverrideCommandHarness();
        $unlinked = new ChannelOverrideCommandHarness();

        $this->command(new SpamfilterCommandNetwork())->execute($empty->context('SPAMFILTER', ['LIST'], $this->auth()));
        $this->command(new SpamfilterCommandNetwork([], false))->execute($unlinked->context('SPAMFILTER', ['ADD', 'c', 'warn', '*x*', 'r'], $this->auth()));

        self::assertSame(['spamfilter.list.empty'], array_column($empty->replies, 0));
        self::assertSame(['spamfilter.unavailable'], array_column($unlinked->replies, 0));
    }

    #[Test]
    public function ignoresCallsWithoutSender(): void
    {
        $network = new SpamfilterCommandNetwork();
        $harness = new ChannelOverrideCommandHarness();

        $this->command($network)->execute($harness->context('SPAMFILTER', ['ADD', 'c', 'warn', '*x*', 'r'], $this->auth(), false));

        self::assertSame([], $network->added);
        self::assertSame([], $harness->replies);
    }

    private function command(SpamfilterCommandNetwork $network): SpamfilterCommand
    {
        $audit = new class implements CommandAuditRecorder {
            public function record(CommandAuditRecord $record): void {}
        };

        return new SpamfilterCommand(new ManageSpamfilterHandler($network, $audit));
    }

    private function auth(): OperatorAuthorizationQuery
    {
        return $this->createStub(OperatorAuthorizationQuery::class);
    }
}

final class SpamfilterCommandNetwork implements NetworkSpamfilters
{
    /** @var list<SpamfilterEntry> */
    public array $added = [];

    /** @var list<array{SpamfilterEntry, string}> */
    public array $removed = [];

    /** @param list<SpamfilterEntry> $entries */
    public function __construct(private readonly array $entries = [], private readonly bool $linked = true) {}

    public function all(): array
    {
        return $this->entries;
    }

    public function add(SpamfilterEntry $entry): bool
    {
        $this->added[] = $entry;

        return $this->linked;
    }

    public function remove(SpamfilterEntry $entry, string $removedBy): bool
    {
        $this->removed[] = [$entry, $removedBy];

        return $this->linked;
    }
}
