<?php

declare(strict_types=1);

namespace App\Tests\ChanServ\Adapter\Out\Network;

use App\ChanServ\Adapter\Out\Network\IrcChanNetworkUserLookup;
use App\ChanServ\Application\Model\ChanNetworkUserView;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\SenderView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IrcChanNetworkUserLookup::class)]
#[CoversClass(ChanNetworkUserView::class)]
final class IrcChanNetworkUserLookupTest extends TestCase
{
    #[Test]
    public function forwardsBothLookups(): void
    {
        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('findByNick')->willReturn(new SenderView('UID1', 'Nick', 'i', 'h', 'c', 'ip'));
        $users->method('findByUid')->willReturn(null);
        $lookup = new IrcChanNetworkUserLookup($users);
        self::assertSame('Nick', $lookup->findByNick('Nick')?->nick);
        self::assertNull($lookup->findByUid('UID2'));
    }
}
