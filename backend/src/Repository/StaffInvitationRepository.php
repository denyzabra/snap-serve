<?php

namespace App\Repository;

use App\Entity\StaffInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StaffInvitation>
 */
class StaffInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StaffInvitation::class);
    }

    /**
     * Find invitation by token
     */
    public function findByToken(string $token): ?StaffInvitation
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Find valid invitation by token (not expired, pending status)
     */
    public function findValidByToken(string $token): ?StaffInvitation
    {
        return $this->createQueryBuilder('si')
            ->andWhere('si.token = :token')
            ->andWhere('si.status = :status')
            ->andWhere('si.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('status', StaffInvitation::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find invitations by restaurant
     */
    public function findByRestaurant(int $restaurantId): array
    {
        return $this->createQueryBuilder('si')
            ->andWhere('si.restaurant = :restaurantId')
            ->setParameter('restaurantId', $restaurantId)
            ->orderBy('si.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending invitations by restaurant
     */
    public function findPendingByRestaurant(int $restaurantId): array
    {
        return $this->createQueryBuilder('si')
            ->andWhere('si.restaurant = :restaurantId')
            ->andWhere('si.status = :status')
            ->andWhere('si.expiresAt > :now')
            ->setParameter('restaurantId', $restaurantId)
            ->setParameter('status', StaffInvitation::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->orderBy('si.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find invitation by email and restaurant
     */
    public function findByEmailAndRestaurant(string $email, int $restaurantId): ?StaffInvitation
    {
        return $this->createQueryBuilder('si')
            ->andWhere('si.email = :email')
            ->andWhere('si.restaurant = :restaurantId')
            ->andWhere('si.status = :status')
            ->setParameter('email', $email)
            ->setParameter('restaurantId', $restaurantId)
            ->setParameter('status', StaffInvitation::STATUS_PENDING)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clean up expired invitations
     */
    public function markExpiredInvitations(): int
    {
        return $this->createQueryBuilder('si')
            ->update()
            ->set('si.status', ':expiredStatus')
            ->where('si.status = :pendingStatus')
            ->andWhere('si.expiresAt <= :now')
            ->setParameter('expiredStatus', StaffInvitation::STATUS_EXPIRED)
            ->setParameter('pendingStatus', StaffInvitation::STATUS_PENDING)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Get invitation statistics for a restaurant
     */
    public function getRestaurantInvitationStats(int $restaurantId): array
    {
        $qb = $this->createQueryBuilder('si')
            ->select('si.status, COUNT(si.id) as count')
            ->andWhere('si.restaurant = :restaurantId')
            ->setParameter('restaurantId', $restaurantId)
            ->groupBy('si.status');

        $results = $qb->getQuery()->getResult();
        
        $stats = [
            'pending' => 0,
            'accepted' => 0,
            'cancelled' => 0,
            'expired' => 0,
            'removed' => 0,
            'total' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
            $stats['total'] += (int) $result['count'];
        }

        return $stats;
    }
}
