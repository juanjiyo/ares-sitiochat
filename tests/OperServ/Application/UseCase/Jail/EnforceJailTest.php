<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Application\UseCase\Jail;

use App\OperServ\Application\UseCase\Jail\EnforceJail;
use App\OperServ\Application\UseCase\Jail\JailRoster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnforceJail::class)]
final class EnforceJailTest extends TestCase
{
    private JailRoster $roster;

    private RecordingJailNetworkActions $network;

    private EnforceJail $jail;

    protected function setUp(): void
    {
        $this->roster = new JailRoster();
        $this->roster->jail('PRESO');
        $this->network = new RecordingJailNetworkActions(['PRESO' => ['#Sumidero', '#nuevo'], 'LIBRE' => ['#nuevo']]);
        $this->jail = new EnforceJail($this->network, $this->roster, '#Sumidero');
    }

    #[Test]
    public function partsAJailedUserFromAnyOtherChannel(): void
    {
        $this->jail->userJoined('PRESO', '#SitioChat');
        $this->jail->userJoined('PRESO', '#sumidero');
        $this->jail->userJoined('LIBRE', '#SitioChat');

        self::assertSame([['part', 'PRESO', '#SitioChat']], $this->network->calls);
    }

    #[Test]
    public function putsAJailedUserBackWhenTheyLeaveTheJail(): void
    {
        $this->jail->userDeparted('PRESO', '#SUMIDERO');
        $this->jail->userDeparted('PRESO', '#otro');
        $this->jail->userDeparted('LIBRE', '#Sumidero');

        self::assertSame([['join', 'PRESO', '#Sumidero']], $this->network->calls);
    }

    #[Test]
    public function partsJailedMembersOfANewlyCreatedChannel(): void
    {
        $this->jail->channelSynchronized('#Nuevo');
        $this->jail->channelSynchronized('#Sumidero');
        $this->jail->channelSynchronized('#vacio');

        self::assertSame([['part', 'PRESO', '#Nuevo']], $this->network->calls);
    }

    #[Test]
    public function freesUsersWhoLeaveTheNetwork(): void
    {
        $this->jail->userLeftNetwork('PRESO');

        self::assertFalse($this->roster->contains('PRESO'));
    }
}
