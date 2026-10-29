<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Irc;

use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use App\OperServ\Adapter\Out\Irc\IrcNetworkUserLookup;
use App\OperServ\Adapter\Out\Irc\NetworkUserMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IrcNetworkUserLookup::class)]
#[CoversClass(NetworkUserMapper::class)]
final class IrcNetworkUserLookupTest extends TestCase
{
    #[Test]
    public function delegatesNicknameLookupToIrcQuery(): void
    {
        $view = new SenderView('U1', 'Nick', 'ident', 'host', '', '');
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByNick')->willReturn($view);

        $user = new IrcNetworkUserLookup($users)->findByNickname('Nick');

        self::assertNotNull($user);
        self::assertSame('U1', $user->uid);
        self::assertSame('Nick', $user->nickname);
        self::assertSame('ident', $user->ident);
        self::assertSame('host', $user->hostname);
    }

    #[Test]
    public function exposesTheDecodedRealIpAddressBesideTheEncodedOne(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByNick')->willReturn(new SenderView('U1', 'Nick', 'ident', 'host', '', 'xjNkBw==', true, true));

        $user = new IrcNetworkUserLookup($users)->findByNickname('Nick');

        self::assertNotNull($user);
        self::assertSame('xjNkBw==', $user->ipBase64);
        self::assertSame('198.51.100.7', $user->ipAddress);
        self::assertTrue($user->identified);
        self::assertTrue($user->ircOperator);
    }

    #[Test]
    public function returnsNullForAnOfflineNickname(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByNick')->willReturn(null);

        self::assertNull(new IrcNetworkUserLookup($users)->findByNickname('Ghost'));
    }
}
