<?php

namespace App\Repository;

use App\Entity\VerificationToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VerificationToken>
 */
class VerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationToken::class);
    }

    public function findValidTokenByToken(string $token): ?VerificationToken
    {
        return $this->createQueryBuilder('vt')
            ->andWhere('vt.token = :token')
            ->andWhere('vt.isUsed = false')
            ->andWhere('vt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findValidTokenByUserAndType(int $userId, string $type): ?VerificationToken
    {
        return $this->createQueryBuilder('vt')
            ->andWhere('vt.user = :userId')
            ->andWhere('vt.type = :type')
            ->andWhere('vt.isUsed = false')
            ->andWhere('vt.expiresAt > :now')
            ->setParameter('userId', $userId)
            ->setParameter('type', $type)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function cleanupExpiredTokens(): int
    {
        return $this->createQueryBuilder('vt')
            ->delete()
            ->andWhere('vt.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
