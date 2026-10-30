<?php

declare(strict_types=1);

namespace App\OperServ\Adapter\Out\Persistence\Doctrine;

use App\OperServ\Application\Port\Out\ElineEntry;
use App\OperServ\Application\Port\Out\ElineRepository;
use App\OperServ\Domain\Entity\Eline;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

use function array_map;
use function sprintf;

final readonly class DoctrineElineRepository implements ElineRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function findByMask(string $mask): ?ElineEntry
    {
        $eline = $this->entityManager->getRepository(Eline::class)->findOneBy(['mask' => $mask]);

        return null === $eline ? null : self::entry($eline);
    }

    public function findActiveAt(DateTimeImmutable $at): array
    {
        /** @var list<Eline> $elines */
        $elines = $this->entityManager
            ->createQuery(sprintf('SELECT e FROM %s e WHERE e.expiresAt IS NULL OR e.expiresAt > :at ORDER BY e.createdAt ASC, e.id ASC', Eline::class))
            ->setParameter('at', $at)
            ->getResult();

        return array_map(self::entry(...), $elines);
    }

    public function save(ElineEntry $entry): void
    {
        $this->entityManager->persist(Eline::create($entry->mask, $entry->setBy, $entry->reason, $entry->createdAt, $entry->expiresAt));
        $this->entityManager->flush();
    }

    public function remove(string $mask): void
    {
        $eline = $this->entityManager->getRepository(Eline::class)->findOneBy(['mask' => $mask]);
        if (null === $eline) {
            return;
        }

        $this->entityManager->remove($eline);
        $this->entityManager->flush();
    }

    public function removeExpiredAt(DateTimeImmutable $at): void
    {
        $this->entityManager
            ->createQuery(sprintf('DELETE FROM %s e WHERE e.expiresAt IS NOT NULL AND e.expiresAt <= :at', Eline::class))
            ->setParameter('at', $at)
            ->execute();
    }

    private static function entry(Eline $eline): ElineEntry
    {
        return new ElineEntry($eline->getMask(), $eline->getSetBy(), $eline->getReason(), $eline->getCreatedAt(), $eline->getExpiresAt());
    }
}
