<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\In\Irc\Command;

use App\GlobalServ\Adapter\In\Irc\Command\ChanCommand;
use App\GlobalServ\Adapter\In\Irc\Command\JoinCommand;
use App\GlobalServ\Adapter\In\Irc\Command\NickCommand;
use App\GlobalServ\Adapter\In\Irc\Command\RankBroadcastCommand;
use App\GlobalServ\Adapter\In\Irc\GlobalServCommandRegistry;
use App\GlobalServ\Adapter\In\Irc\GlobalServContext;
use App\GlobalServ\Adapter\In\Irc\GlobalServNotifierInterface;
use App\GlobalServ\Application\Model\MassChannelJoinResult;
use App\GlobalServ\Application\Port\Out\ChannelMemberNoticePort;
use App\GlobalServ\Application\Port\Out\MassBroadcastPort;
use App\GlobalServ\Application\Port\Out\MassChannelJoinPort;
use App\GlobalServ\Application\Port\Out\OperatorRankLookupPort;
use App\GlobalServ\Application\Port\Out\PseudoClientBroadcastPort;
use App\GlobalServ\Application\Port\Out\RankFilteredBroadcastPort;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\GlobalServ\Application\UseCase\MassJoin\MassJoinChannelHandler;
use App\GlobalServ\Application\UseCase\SendChannel\SendChannelMessageHandler;
use App\GlobalServ\Application\UseCase\SendNick\SendNickBroadcastHandler;
use App\GlobalServ\Application\UseCase\SendRank\SendRankBroadcastHandler;
use App\GlobalServ\Domain\ValueObject\GlobalServRank;
use App\Irc\Application\Port\In\ChannelLookupPort;
use App\Irc\Application\Port\In\ChannelView;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\Irc\Application\Port\In\ServiceNicknameProviderInterface;
use App\Irc\Application\Port\In\ServiceNicknameRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

use function is_string;

#[CoversClass(NickCommand::class)]
#[CoversClass(ChanCommand::class)]
#[CoversClass(JoinCommand::class)]
#[CoversClass(RankBroadcastCommand::class)]
final class BroadcastCommandsTest extends TestCase
{
    private const string SENDER_UID = '001ABC';

    /**
     * @param array<string, list<string>> $replies
     * @param list<string>                $args
     */
    private function createContext(array &$replies, array $args): GlobalServContext
    {
        $notifier = $this->createStub(GlobalServNotifierInterface::class);
        $notifier->method('getNick')->willReturn('GlobalServ');
        $notifier->method('sendMessage')->willReturnCallback(
            static function (string $target, string $message) use (&$replies): void {
                $replies[$target][] = $message;
            },
        );

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $params = []): string {
                if ('global.prefix' === $id) {
                    return '[ Global Message ]';
                }

                $count = $params['%count%'] ?? null;

                return is_string($count) ? $id . ' count=' . $count : $id;
            },
        );

        return new GlobalServContext(
            sender: new SenderView(self::SENDER_UID, 'Alice', 'i', 'h', 'c', 'ip', isIdentified: true, isOper: true),
            senderAccountId: 7,
            command: 'CMD',
            args: $args,
            notifier: $notifier,
            translator: $translator,
            language: 'en',
            registry: new GlobalServCommandRegistry([]),
            serviceNicks: new ServiceNicknameRegistry([$this->createServiceNickProvider()]),
        );
    }

    private function createServiceNickProvider(): ServiceNicknameProviderInterface
    {
        return new class implements ServiceNicknameProviderInterface {
            public function getServiceKey(): string
            {
                return 'globalserv';
            }

            public function getNickname(): string
            {
                return 'GlobalServ';
            }
        };
    }

    #[Test]
    public function nickBroadcastsAsAPseudoClientAndReportsTheImpactCount(): void
    {
        $broadcast = $this->createMock(PseudoClientBroadcastPort::class);
        $broadcast->expects(self::once())->method('broadcastFromPseudoClient')
            ->with('Notice', "[ Global Message ]\nmaintenance now")
            ->willReturn(5);

        $command = new NickCommand(new SendNickBroadcastHandler($broadcast));
        $replies = [];
        $command->execute($this->createContext($replies, ['Notice', 'maintenance', 'now']));

        self::assertSame('NICK', $command->getName());
        self::assertSame(2, $command->getMinArgs());
        self::assertSame(GlobalServPermission::NICK, $command->getRequiredPermission());
        self::assertSame([self::SENDER_UID => ['nick.done count=5']], $replies);
    }

    #[Test]
    public function nickReportsAnUnavailableNetwork(): void
    {
        $broadcast = $this->createStub(PseudoClientBroadcastPort::class);
        $broadcast->method('broadcastFromPseudoClient')->willReturn(null);

        $command = new NickCommand(new SendNickBroadcastHandler($broadcast));
        $replies = [];
        $command->execute($this->createContext($replies, ['Notice', 'hi']));

        self::assertSame([self::SENDER_UID => ['error.network_unavailable']], $replies);
    }

    #[Test]
    public function chanReportsAnUnknownChannel(): void
    {
        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(null);

        $command = new ChanCommand(new SendChannelMessageHandler($channels, $this->createStub(ChannelMemberNoticePort::class)));
        $replies = [];
        $command->execute($this->createContext($replies, ['#missing', 'hello']));

        self::assertSame(GlobalServPermission::CHAN, $command->getRequiredPermission());
        self::assertSame([self::SENDER_UID => ['chan.not_found']], $replies);
    }

    #[Test]
    public function chanSendsToEveryChannelMember(): void
    {
        $channels = $this->createStub(ChannelLookupPort::class);
        $channels->method('findByChannelName')->willReturn(
            new ChannelView('#room', '+nt', null, 2, [['uid' => '001AAA', 'roleLetter' => ''], ['uid' => '001BBB', 'roleLetter' => '']]),
        );

        $notice = $this->createStub(ChannelMemberNoticePort::class);
        $notice->method('sendToChannelMembers')->willReturn(2);

        $command = new ChanCommand(new SendChannelMessageHandler($channels, $notice));
        $replies = [];
        $command->execute($this->createContext($replies, ['#room', 'hello']));

        self::assertSame([self::SENDER_UID => ['chan.done count=2']], $replies);
    }

    #[Test]
    public function joinReportsTheUnsupportedCapability(): void
    {
        $join = $this->createStub(MassChannelJoinPort::class);
        $join->method('joinAllUsersToChannel')->willReturn(MassChannelJoinResult::unsupported());

        $command = new JoinCommand(new MassJoinChannelHandler($join, $this->createStub(MassBroadcastPort::class)));
        $replies = [];
        $command->execute($this->createContext($replies, ['#room', 'please', 'join']));

        self::assertSame(GlobalServPermission::JOIN, $command->getRequiredPermission());
        self::assertSame([self::SENDER_UID => ['join.unsupported']], $replies);
    }

    #[Test]
    public function rankBroadcastReachesOnlyTheSelectedRank(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn(['001AAA', '001BBB']);

        $ranks = $this->createStub(OperatorRankLookupPort::class);
        $ranks->method('hasAtLeastRank')->willReturnCallback(
            static fn (string $uid, GlobalServRank $rank): bool => '001AAA' === $uid,
        );

        $broadcast = $this->createMock(RankFilteredBroadcastPort::class);
        $broadcast->expects(self::once())->method('broadcastToUids')
            ->with(['001AAA'], "[ Global Message ]\nstaff news")
            ->willReturn(1);

        $command = new RankBroadcastCommand(
            new SendRankBroadcastHandler($users, $ranks, $broadcast),
            'PRE-OPER',
            GlobalServRank::PREOPER,
            GlobalServPermission::PREOPER,
            'pre-oper.done',
            5,
        );
        $replies = [];
        $command->execute($this->createContext($replies, ['staff', 'news']));

        self::assertSame('PRE-OPER', $command->getName());
        self::assertSame('pre-oper.syntax', $command->getSyntaxKey());
        self::assertSame([self::SENDER_UID => ['pre-oper.done count=1']], $replies);
    }

    #[Test]
    public function rankBroadcastReportsAnUndeterminableRank(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn(['001AAA']);

        $ranks = $this->createStub(OperatorRankLookupPort::class);
        $ranks->method('hasAtLeastRank')->willReturn(null);

        $command = new RankBroadcastCommand(
            new SendRankBroadcastHandler($users, $ranks, $this->createStub(RankFilteredBroadcastPort::class)),
            'ADMIN',
            GlobalServRank::ADMIN,
            GlobalServPermission::ADMIN,
            'admin.done',
            8,
        );
        $replies = [];
        $command->execute($this->createContext($replies, ['staff']));

        self::assertSame([self::SENDER_UID => ['rank.unsupported']], $replies);
    }
}
