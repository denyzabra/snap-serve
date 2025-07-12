<?php

namespace App\Service;

use App\DTO\RestaurantProfileRequest;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Entity\BusinessHours;
use App\Repository\RestaurantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for restaurant-related business logic
 */
class RestaurantService
{
    public function __construct(
        private RestaurantRepository $restaurantRepository,
        private AuthenticationService $authService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get restaurant for admin user with access control
     */
    public function getRestaurantForAdmin(int $restaurantId, User $admin): ?Restaurant
    {
        // Get restaurant by ID
        $restaurant = $this->restaurantRepository->find($restaurantId);
        
        if (!$restaurant) {
            return null;
        }

        // Check if admin has access to this restaurant
        if (!$this->hasRestaurantAccess($admin, $restaurant)) {
            return null;
        }

        return $restaurant;
    }

    /**
     * Update restaurant profile with validated data
     */
    public function updateRestaurantProfile(Restaurant $restaurant, RestaurantProfileRequest $request): Restaurant
    {
        // Update basic information
        if (!empty($request->name)) {
            $restaurant->setName($request->name);
        }

        if ($request->description !== null) {
            $restaurant->setDescription($request->description);
        }

        if ($request->phoneNumber !== null) {
            $restaurant->setPhoneNumber($request->phoneNumber);
        }

        if ($request->email !== null) {
            $restaurant->setEmail($request->email);
        }

        // Update address information
        if ($request->address !== null) {
            $restaurant->setAddress($request->address);
        }

        if ($request->city !== null) {
            $restaurant->setCity($request->city);
        }

        if ($request->state !== null) {
            $restaurant->setState($request->state);
        }

        if ($request->postalCode !== null) {
            $restaurant->setPostalCode($request->postalCode);
        }

        if ($request->country !== null) {
            $restaurant->setCountry($request->country);
        }

        // Update cuisine and service information
        if ($request->cuisineType !== null) {
            $restaurant->setCuisineType($request->cuisineType);
        }

        if ($request->serviceTypes !== null) {
            $restaurant->setServiceTypes($request->serviceTypes);
        }

        // Update branding
        if ($request->primaryColor !== null) {
            $restaurant->setPrimaryColor($request->primaryColor);
        }

        if ($request->secondaryColor !== null) {
            $restaurant->setSecondaryColor($request->secondaryColor);
        }

        // Update service options
        if ($request->acceptsReservations !== null) {
            $restaurant->setAcceptsReservations($request->acceptsReservations);
        }

        if ($request->hasDelivery !== null) {
            $restaurant->setHasDelivery($request->hasDelivery);
        }

        if ($request->hasTakeout !== null) {
            $restaurant->setHasTakeout($request->hasTakeout);
        }

        // Update delivery settings
        if ($request->minimumOrderAmount !== null) {
            $restaurant->setMinimumOrderAmount($request->minimumOrderAmount);
        }

        if ($request->deliveryFee !== null) {
            $restaurant->setDeliveryFee($request->deliveryFee);
        }

        if ($request->estimatedDeliveryTime !== null) {
            $restaurant->setEstimatedDeliveryTime($request->estimatedDeliveryTime);
        }

        if ($request->specialInstructions !== null) {
            $restaurant->setSpecialInstructions($request->specialInstructions);
        }

        // Save to database
        $this->entityManager->persist($restaurant);
        $this->entityManager->flush();

        $this->logger->info('Restaurant profile updated', [
            'restaurant_id' => $restaurant->getId(),
            'restaurant_name' => $restaurant->getName()
        ]);

        return $restaurant;
    }

    /**
     * Update restaurant business hours
     */
    public function updateBusinessHours(Restaurant $restaurant, array $businessHoursData): void
    {
        // Remove existing business hours
        foreach ($restaurant->getBusinessHours() as $existingHours) {
            $this->entityManager->remove($existingHours);
        }

        // Add new business hours
        foreach ($businessHoursData as $dayData) {
            if (!isset($dayData['dayOfWeek'])) {
                continue;
            }

            $businessHour = new BusinessHours();
            $businessHour->setRestaurant($restaurant);
            $businessHour->setDayOfWeek($dayData['dayOfWeek']);
            $businessHour->setIsOpen($dayData['isOpen'] ?? true);
            $businessHour->setIs24Hours($dayData['is24Hours'] ?? false);

            if (isset($dayData['openTime']) && !empty($dayData['openTime'])) {
                $openTime = \DateTime::createFromFormat('H:i', $dayData['openTime']);
                $businessHour->setOpenTime($openTime);
            }

            if (isset($dayData['closeTime']) && !empty($dayData['closeTime'])) {
                $closeTime = \DateTime::createFromFormat('H:i', $dayData['closeTime']);
                $businessHour->setCloseTime($closeTime);
            }

            $this->entityManager->persist($businessHour);
        }

        $this->entityManager->flush();

        $this->logger->info('Restaurant business hours updated', [
            'restaurant_id' => $restaurant->getId(),
            'hours_count' => count($businessHoursData)
        ]);
    }

    /**
     * Check if user has access to restaurant
     */
    private function hasRestaurantAccess(User $user, Restaurant $restaurant): bool
    {
        // Admin users can access restaurants they created
        if ($user->isAdmin()) {
            $userRestaurant = $this->authService->findRestaurantByAdmin($user);
            return $userRestaurant && $userRestaurant->getId() === $restaurant->getId();
        }

        return false;
    }

    /**
     * Get restaurant statistics
     */
    public function getRestaurantStatistics(Restaurant $restaurant): array
    {
        return [
            'totalTables' => $restaurant->getTables()->count(),
            'totalCategories' => $restaurant->getCategories()->count(),
            'totalMenuItems' => $restaurant->getMenuItems()->count(),
            'totalOrders' => $restaurant->getOrders()->count(),
            'profileCompletionPercentage' => $restaurant->getProfileCompletionPercentage(),
            'isProfileComplete' => $restaurant->isProfileComplete()
        ];
    }
}
