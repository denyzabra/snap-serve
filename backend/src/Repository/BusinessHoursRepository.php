<?php

namespace App\Repository;

use App\Entity\BusinessHours;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BusinessHours>
 * Repository for managing business hours data access
 */
class BusinessHoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BusinessHours::class);
    }

    /**
     * Find business hours for a restaurant on a specific day
     * 
     * @param int $restaurantId Restaurant ID
     * @param string $dayOfWeek Day of the week
     * @return BusinessHours|null Business hours or null if not found
     */
    public function findByRestaurantAndDay(int $restaurantId, string $dayOfWeek): ?BusinessHours
    {
        return $this->createQueryBuilder('bh')
            ->andWhere('bh.restaurant = :restaurantId')
            ->andWhere('bh.dayOfWeek = :dayOfWeek')
            ->setParameter('restaurantId', $restaurantId)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all business hours for a restaurant ordered by day
     * 
     * @param int $restaurantId Restaurant ID
     * @return array Business hours ordered by day of week
     */
    public function findByRestaurantOrderedByDay(int $restaurantId): array
    {
        $dayOrder = [
            BusinessHours::DAY_MONDAY => 1,
            BusinessHours::DAY_TUESDAY => 2,
            BusinessHours::DAY_WEDNESDAY => 3,
            BusinessHours::DAY_THURSDAY => 4,
            BusinessHours::DAY_FRIDAY => 5,
            BusinessHours::DAY_SATURDAY => 6,
            BusinessHours::DAY_SUNDAY => 7
        ];

        $results = $this->createQueryBuilder('bh')
            ->andWhere('bh.restaurant = :restaurantId')
            ->setParameter('restaurantId', $restaurantId)
            ->getQuery()
            ->getResult();

        // Sort by day of week
        usort($results, function(BusinessHours $a, BusinessHours $b) use ($dayOrder) {
            return $dayOrder[$a->getDayOfWeek()] <=> $dayOrder[$b->getDayOfWeek()];
        });

        return $results;
    }

    /**
     * Find restaurants that are currently open
     * 
     * @return array Currently open restaurants
     */
    public function findCurrentlyOpenRestaurants(): array
    {
        $currentDay = strtolower(date('l'));
        $currentTime = date('H:i');

        return $this->createQueryBuilder('bh')
            ->join('bh.restaurant', 'r')
            ->andWhere('bh.dayOfWeek = :currentDay')
            ->andWhere('bh.isOpen = true')
            ->andWhere('r.isActive = true')
            ->andWhere('r.isVerified = true')
            ->andWhere('
                bh.is24Hours = true OR 
                (bh.openTime <= :currentTime AND bh.closeTime >= :currentTime) OR
                (bh.closeTime < bh.openTime AND (bh.openTime <= :currentTime OR bh.closeTime >= :currentTime))
            ')
            ->setParameter('currentDay', $currentDay)
            ->setParameter('currentTime', $currentTime)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if restaurant is open at specific time
     * 
     * @param int $restaurantId Restaurant ID
     * @param string $dayOfWeek Day of the week
     * @param string $time Time in H:i format
     * @return bool True if restaurant is open
     */
    public function isRestaurantOpenAt(int $restaurantId, string $dayOfWeek, string $time): bool
    {
        $businessHours = $this->findByRestaurantAndDay($restaurantId, $dayOfWeek);
        
        if (!$businessHours || !$businessHours->isOpen()) {
            return false;
        }

        if ($businessHours->is24Hours()) {
            return true;
        }

        $openTime = $businessHours->getOpenTime();
        $closeTime = $businessHours->getCloseTime();

        if (!$openTime || !$closeTime) {
            return false;
        }

        $openTimeStr = $openTime->format('H:i');
        $closeTimeStr = $closeTime->format('H:i');

        // Handle overnight hours (e.g., 10 PM to 2 AM)
        if ($closeTimeStr < $openTimeStr) {
            return $time >= $openTimeStr || $time <= $closeTimeStr;
        }

        return $time >= $openTimeStr && $time <= $closeTimeStr;
    }

    /**
     * Get weekly schedule for a restaurant
     * 
     * @param int $restaurantId Restaurant ID
     * @return array Weekly schedule formatted for display
     */
    public function getWeeklySchedule(int $restaurantId): array
    {
        $businessHours = $this->findByRestaurantOrderedByDay($restaurantId);
        $schedule = [];

        $allDays = [
            BusinessHours::DAY_MONDAY,
            BusinessHours::DAY_TUESDAY,
            BusinessHours::DAY_WEDNESDAY,
            BusinessHours::DAY_THURSDAY,
            BusinessHours::DAY_FRIDAY,
            BusinessHours::DAY_SATURDAY,
            BusinessHours::DAY_SUNDAY
        ];

        foreach ($allDays as $day) {
            $dayHours = null;
            foreach ($businessHours as $hours) {
                if ($hours->getDayOfWeek() === $day) {
                    $dayHours = $hours;
                    break;
                }
            }

            $schedule[$day] = [
                'day' => ucfirst($day),
                'isOpen' => $dayHours ? $dayHours->isOpen() : false,
                'is24Hours' => $dayHours ? $dayHours->is24Hours() : false,
                'openTime' => $dayHours && $dayHours->getOpenTime() ? 
                    $dayHours->getOpenTime()->format('H:i') : null,
                'closeTime' => $dayHours && $dayHours->getCloseTime() ? 
                    $dayHours->getCloseTime()->format('H:i') : null,
                'formattedHours' => $dayHours ? $dayHours->getFormattedHours() : 'Closed',
                'isCurrentlyOpen' => $dayHours ? $dayHours->isCurrentlyOpen() : false
            ];
        }

        return $schedule;
    }
}
