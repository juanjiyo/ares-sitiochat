<?php

declare(strict_types=1);

namespace App\Tests\OperServ\Adapter\Out\Persistence\Doctrine;

use App\OperServ\Adapter\Out\Persistence\Doctrine\DoctrineElineRepository;
use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Domain\Entity\Eline;
use App\Tests\Shared\DoctrineIntegrationTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(DoctrineElineRepository::class)]
#[CoversClass(Eline::class)]
#[CoversClass(ElineEntry::class)]
final class DoctrineElineRepositoryTest extends DoctrineIntegrationTestCase
{
    #[Test]
    public function storesFindsListsAndRemovesElines(): void
    {
        $repository = new DoctrineElineRepository($this->entityManager);
        $now = new DateTimeImmutable('2026-10-30 12:00:00');
        $repository->save(new ElineEntry('*@203.0.113.10', 'Oper', 'ZNC', $now->modify('-2 hours'), null));
        $repository->save(new ElineEntry('znc@198.51.100.0/24', 'Root', 'Bouncers', $now->modify('-1 hour'), $now->modify('+1 day')));
        $repository->save(new ElineEntry('*@192.0.2.1', 'Oper', 'Old', $now->modify('-3 hours'), $now->modify('-1 minute')));
        $this->flushAndClear();

        $found = $repository->findByMask('znc@198.51.100.0/24');
        self::assertNotNull($found);
        self::assertSame(['Root', 'Bouncers'], [$found->setBy, $found->reason]);
        self::assertSame('2026-10-31 12:00:00', $found->expiresAt?->format('Y-m-d H:i:s'));
        self::assertNull($repository->findByMask('*@nadie'));
        self::assertGreaterThan(0, $this->entityManager->getRepository(Eline::class)->findOneBy(['mask' => 'znc@198.51.100.0/24'])?->getId());

        self::assertSame(['*@203.0.113.10', 'znc@198.51.100.0/24'], array_map(static fn (ElineEntry $entry): string => $entry->mask, $repository->findActiveAt($now)));

        $repository->removeExpiredAt($now);
        $repository->remove('*@203.0.113.10');
        $repository->remove('*@no-existe');
        $this->flushAndClear();

        self::assertNull($repository->findByMask('*@192.0.2.1'));
        self::assertNull($repository->findByMask('*@203.0.113.10'));
        self::assertSame(['znc@198.51.100.0/24'], array_map(static fn (ElineEntry $entry): string => $entry->mask, $repository->findActiveAt($now)));
    }
}
