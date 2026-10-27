<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\Command\ChanCommand;
use App\GlobalServ\Adapter\In\Irc\Command\GlobalCommand;
use App\GlobalServ\Adapter\In\Irc\Command\HelpCommand;
use App\GlobalServ\Adapter\In\Irc\Command\JoinCommand;
use App\GlobalServ\Adapter\In\Irc\Command\NickCommand;
use App\GlobalServ\Adapter\In\Irc\Command\RankBroadcastCommand;
use App\GlobalServ\Adapter\In\Irc\GlobalServAuthorizationCheckerInterface;
use App\GlobalServ\Adapter\In\Irc\Help\UnifiedHelpFormatter;
use App\GlobalServ\Application\Port\Out\ChannelMemberNoticePort;
use App\GlobalServ\Application\Port\Out\MassBroadcastPort;
use App\GlobalServ\Application\Port\Out\MassChannelJoinPort;
use App\GlobalServ\Application\Port\Out\OperatorRankLookupPort;
use App\GlobalServ\Application\Port\Out\PseudoClientBroadcastPort;
use App\GlobalServ\Application\Port\Out\RankFilteredBroadcastPort;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\GlobalServ\Application\UseCase\Global\SendPublicGlobalMessageHandlerInterface;
use App\GlobalServ\Application\UseCase\MassJoin\MassJoinChannelHandler;
use App\GlobalServ\Application\UseCase\SendChannel\SendChannelMessageHandler;
use App\GlobalServ\Application\UseCase\SendNick\SendNickBroadcastHandler;
use App\GlobalServ\Application\UseCase\SendRank\SendRankBroadcastHandler;
use App\GlobalServ\Domain\ValueObject\GlobalServRank;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GlobalCommand::class)]
#[CoversClass(NickCommand::class)]
#[CoversClass(ChanCommand::class)]
#[CoversClass(JoinCommand::class)]
#[CoversClass(RankBroadcastCommand::class)]
#[CoversClass(HelpCommand::class)]
#[CoversClass(GlobalServPermission::class)]
final class CommandMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string, GlobalServRank, string, string, int}>
     */
    public static function rankCommands(): iterable
    {
        yield 'PRE-OPER' => ['PRE-OPER', GlobalServRank::PREOPER, GlobalServPermission::PREOPER, 'pre-oper.done', 5];
        yield 'OPER' => ['OPER', GlobalServRank::OPER, GlobalServPermission::OPER, 'oper.done', 6];
        yield 'CO-ADMIN' => ['CO-ADMIN', GlobalServRank::COADMIN, GlobalServPermission::COADMIN, 'co-admin.done', 7];
        yield 'ADMIN' => ['ADMIN', GlobalServRank::ADMIN, GlobalServPermission::ADMIN, 'admin.done', 8];
    }

    #[Test]
    public function globalCommandExposesItsMetadata(): void
    {
        $command = new GlobalCommand($this->createStub(SendPublicGlobalMessageHandlerInterface::class));

        self::assertSame('GLOBAL', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getOrder());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame('global.syntax', $command->getSyntaxKey());
        self::assertSame('global.help', $command->getHelpKey());
        self::assertSame('global.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(GlobalServPermission::GLOBAL, $command->getRequiredPermission());
    }

    #[Test]
    public function nickCommandExposesItsMetadata(): void
    {
        $command = new NickCommand(new SendNickBroadcastHandler($this->createStub(PseudoClientBroadcastPort::class)));

        self::assertSame('NICK', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(2, $command->getOrder());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('nick.syntax', $command->getSyntaxKey());
        self::assertSame('nick.help', $command->getHelpKey());
        self::assertSame('nick.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(GlobalServPermission::NICK, $command->getRequiredPermission());
    }

    #[Test]
    public function chanCommandExposesItsMetadata(): void
    {
        $command = new ChanCommand(new SendChannelMessageHandler(
            $this->createStub(ChannelLookupPort::class),
            $this->createStub(ChannelMemberNoticePort::class),
        ));

        self::assertSame('CHAN', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(3, $command->getOrder());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('chan.syntax', $command->getSyntaxKey());
        self::assertSame('chan.help', $command->getHelpKey());
        self::assertSame('chan.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(GlobalServPermission::CHAN, $command->getRequiredPermission());
    }

    #[Test]
    public function joinCommandExposesItsMetadata(): void
    {
        $command = new JoinCommand(new MassJoinChannelHandler(
            $this->createStub(MassChannelJoinPort::class),
            $this->createStub(MassBroadcastPort::class),
        ));

        self::assertSame('JOIN', $command->getName());
        self::assertSame([], $command->getAliases());
        self::assertSame(4, $command->getOrder());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame('join.syntax', $command->getSyntaxKey());
        self::assertSame('join.help', $command->getHelpKey());
        self::assertSame('join.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame(GlobalServPermission::JOIN, $command->getRequiredPermission());
    }

    #[Test]
    #[DataProvider('rankCommands')]
    public function rankCommandExposesItsMetadata(string $name, GlobalServRank $rank, string $permission, string $replyKey, int $order): void
    {
        $command = new RankBroadcastCommand(
            new SendRankBroadcastHandler(
                $this->createStub(NetworkUserLookupPort::class),
                $this->createStub(OperatorRankLookupPort::class),
                $this->createStub(RankFilteredBroadcastPort::class),
            ),
            $name,
            $rank,
            $permission,
            $replyKey,
            $order,
        );

        self::assertSame($name, $command->getName());
        self::assertSame($order, $command->getOrder());

        self::assertSame([], $command->getAliases());
        self::assertSame(1, $command->getMinArgs());
        self::assertSame(strtolower($name) . '.syntax', $command->getSyntaxKey());
        self::assertSame(strtolower($name) . '.help', $command->getHelpKey());
        self::assertSame(strtolower($name) . '.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertTrue($command->isOperOnly());
        self::assertSame($permission, $command->getRequiredPermission());
    }

    #[Test]
    public function helpCommandExposesItsMetadata(): void
    {
        $command = new HelpCommand(new UnifiedHelpFormatter(), $this->createStub(GlobalServAuthorizationCheckerInterface::class));

        self::assertSame('HELP', $command->getName());
        self::assertSame(['AYUDA'], $command->getAliases());
        self::assertSame(99, $command->getOrder());
        self::assertSame(0, $command->getMinArgs());
        self::assertSame('help.syntax', $command->getSyntaxKey());
        self::assertSame('help.help', $command->getHelpKey());
        self::assertSame('help.short', $command->getShortDescKey());
        self::assertSame([], $command->getSubCommandHelp());
        self::assertFalse($command->isOperOnly());
        self::assertNull($command->getRequiredPermission());
    }

    #[Test]
    public function permissionCatalogListsEveryCommandPermission(): void
    {
        self::assertSame([
            'globalserv.global',
            'globalserv.nick',
            'globalserv.chan',
            'globalserv.join',
            'globalserv.preoper',
            'globalserv.oper',
            'globalserv.coadmin',
            'globalserv.admin',
        ], GlobalServPermission::allIrcop());
    }
}
