<?php

declare(strict_types=1);

namespace App\Tests\GlobalServ\Adapter\Out;

use App\GlobalServ\Adapter\Out\Irc\ActiveConnectionMassChannelJoin;
use App\GlobalServ\Adapter\Out\Irc\GlobalServPseudoClientUidGenerator;
use App\GlobalServ\Adapter\Out\Irc\ServiceRegistryGlobalServIdentity;
use App\GlobalServ\Adapter\Out\NickServ\GlobalServNickAccountAdapter;
use App\GlobalServ\Adapter\Out\Security\GlobalServAuthorizationContextAdapter;
use App\GlobalServ\Adapter\Out\Security\SymfonyGlobalServAuthorizationCheckerAdapter;
use App\GlobalServ\Application\Port\Out\GlobalServNetworkIdentity;
use App\GlobalServ\Application\Security\GlobalServIrcopPermission;
use App\GlobalServ\Application\Security\GlobalServPermission;
use App\Irc\Application\Port\In\ActiveConnectionHolderInterface;
use App\Irc\Application\Port\In\ActiveProtocolModuleHolderInterface;
use App\Irc\Application\Port\In\NetworkUserLookupPort;
use App\Irc\Application\Port\In\ProtocolModuleInterface;
use App\Irc\Application\Port\In\ProtocolServiceActionsInterface;
use App\Irc\Application\Port\In\ServiceUidRegistry;
use App\NickServ\Application\Port\In\IrcAuthorizationContext;
use App\NickServ\Application\Port\In\NickAccountQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(GlobalServPseudoClientUidGenerator::class)]
#[CoversClass(ServiceRegistryGlobalServIdentity::class)]
#[CoversClass(GlobalServNickAccountAdapter::class)]
#[CoversClass(GlobalServAuthorizationContextAdapter::class)]
#[CoversClass(SymfonyGlobalServAuthorizationCheckerAdapter::class)]
#[CoversClass(ActiveConnectionMassChannelJoin::class)]
#[CoversClass(GlobalServIrcopPermission::class)]
final class SmallAdaptersTest extends TestCase
{
    #[Test]
    public function uidGeneratorProducesSequentialYBlockUids(): void
    {
        $holder = $this->createStub(ActiveConnectionHolderInterface::class);
        $holder->method('getServerSid')->willReturn('003');

        $generator = new GlobalServPseudoClientUidGenerator($holder);

        self::assertSame('003Y00001', $generator->generate());
        self::assertSame('003Y00002', $generator->generate());
    }

    #[Test]
    public function uidGeneratorReturnsNullWithoutAServerSid(): void
    {
        $generator = new GlobalServPseudoClientUidGenerator($this->createStub(ActiveConnectionHolderInterface::class));

        self::assertNull($generator->generate());
    }

    #[Test]
    public function identityResolvesTheGlobalServUid(): void
    {
        $registry = $this->createStub(ServiceUidRegistry::class);
        $registry->method('getUid')->willReturnMap([['globalserv', '003Z00001'], ['other', '']]);

        self::assertSame('003Z00001', new ServiceRegistryGlobalServIdentity($registry)->serviceUid());
    }

    #[Test]
    public function identityReturnsNullWhenTheServiceHasNoUid(): void
    {
        $registry = $this->createStub(ServiceUidRegistry::class);
        $registry->method('getUid')->willReturn('');

        self::assertNull(new ServiceRegistryGlobalServIdentity($registry)->serviceUid());
    }

    #[Test]
    public function nickAccountAdapterForwardsTheLookup(): void
    {
        $query = $this->createStub(NickAccountQuery::class);
        $query->method('findIdByNick')->willReturn(42);

        self::assertSame(42, new GlobalServNickAccountAdapter($query)->findAccountIdByNick('Alice'));
    }

    #[Test]
    public function authorizationAdaptersForwardToTheirCollaborators(): void
    {
        $context = $this->createMock(IrcAuthorizationContext::class);
        $context->expects(self::once())->method('setCurrentUser')->with('001ABC', true, true);
        $context->expects(self::once())->method('clear');

        $adapter = new GlobalServAuthorizationContextAdapter($context);
        $adapter->setCurrentUser('001ABC', true, true);
        $adapter->clear();

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects(self::once())->method('isGranted')->with('globalserv.global', null)->willReturn(true);

        self::assertTrue(new SymfonyGlobalServAuthorizationCheckerAdapter($checker)->isGranted('globalserv.global'));
    }

    #[Test]
    public function massChannelJoinForcesEveryConnectedUserIntoTheChannel(): void
    {
        $actions = $this->createMock(ProtocolServiceActionsInterface::class);
        $actions->expects(self::exactly(2))
            ->method('forceJoin')
            ->with('003', self::anything(), '#room', 'YAA');

        $module = $this->createStub(ProtocolModuleInterface::class);
        $module->method('getServiceActions')->willReturn($actions);

        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('getServerSid')->willReturn('003');
        $connection->method('getProtocolModule')->willReturn($module);

        $identity = $this->createStub(GlobalServNetworkIdentity::class);
        $identity->method('serviceUid')->willReturn('YAA');

        $users = $this->createStub(NetworkUserLookupPort::class);
        $users->method('listConnectedUids')->willReturn(['001', '002']);

        $result = new ActiveConnectionMassChannelJoin($connection, $identity, $users)
            ->joinAllUsersToChannel('#room', 'reason');

        self::assertTrue($result->supported);
        self::assertSame(2, $result->joinedCount);
    }

    #[Test]
    public function massChannelJoinReportsUnsupportedWhenTheProtocolModuleIsMissing(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('isConnected')->willReturn(true);
        $connection->method('getServerSid')->willReturn('003');
        $connection->method('getProtocolModule')->willReturn(null);
        $identity = $this->createStub(GlobalServNetworkIdentity::class);
        $users = $this->createStub(NetworkUserLookupPort::class);

        $result = new ActiveConnectionMassChannelJoin($connection, $identity, $users)
            ->joinAllUsersToChannel('#room', 'reason');

        self::assertFalse($result->supported);
    }

    #[Test]
    public function massChannelJoinReportsUnsupportedWhenTheNetworkIsDown(): void
    {
        $connection = $this->createStub(ActiveProtocolModuleHolderInterface::class);
        $connection->method('isConnected')->willReturn(false);
        $identity = $this->createStub(GlobalServNetworkIdentity::class);
        $users = $this->createStub(NetworkUserLookupPort::class);

        $result = new ActiveConnectionMassChannelJoin($connection, $identity, $users)
            ->joinAllUsersToChannel('#room', 'reason');

        self::assertFalse($result->supported);
        self::assertNull($result->joinedCount);
    }

    #[Test]
    public function ircopPermissionProviderExposesTheServiceNameAndPermissions(): void
    {
        $provider = new GlobalServIrcopPermission();

        self::assertSame('GlobalServ', $provider->getServiceName());
        self::assertSame(GlobalServPermission::allIrcop(), $provider->getPermissions());
    }
}
