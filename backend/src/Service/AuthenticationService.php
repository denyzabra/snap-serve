<?php

namespace App\Service;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Repository\RestaurantRepository;
use Psr\Log\LoggerInterface;

/**
 * Service for authentication-related business logic
 * Handles user-restaurant associations and permission management
 */
class AuthenticationService
{
    public function __construct(
        private RestaurantRepository $restaurantRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Find restaurant associated with admin user
     * Uses creation time proximity to link admin with restaurant
     */
    public function findRestaurantByAdmin(User $user): ?Restaurant
    {
        if (!$user->isAdmin()) {
            return null;
        }

        try {
            // Find restaurant created around the same time as the user
            // This is a simplified approach - in production you'd have a direct relationship
            $restaurants = $this->restaurantRepository->createQueryBuilder('r')
                ->where('r.createdAt >= :userCreated')
                ->andWhere('r.createdAt <= :userCreatedPlus')
                ->setParameter('userCreated', $user->getCreatedAt())
                ->setParameter('userCreatedPlus', $user->getCreatedAt()->modify('+2 hours'))
                ->orderBy('r.createdAt', 'ASC')
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();

            $restaurant = $restaurants[0] ?? null;

            if ($restaurant) {
                $this->logger->debug('Restaurant found for admin user', [
                    'user_id' => $user->getId(),
                    'restaurant_id' => $restaurant->getId(),
                    'restaurant_name' => $restaurant->getName()
                ]);
            } else {
                $this->logger->warning('No restaurant found for admin user', [
                    'user_id' => $user->getId(),
                    'user_email' => $user->getEmail(),
                    'user_created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s')
                ]);
            }

            return $restaurant;

        } catch (\Exception $e) {
            $this->logger->error('Error finding restaurant for admin user', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Check if user can access admin features
     */
    public function canAccessAdminFeatures(User $user): bool
    {
        return $user->isActive() && 
               $user->isAdmin();
    }

    /**
     * Get user permissions based on role hierarchy
     */
    public function getUserPermissions(User $user): array
    {
        $permissions = [
            'can_view_menu' => false,
            'can_manage_menu' => false,
            'can_manage_tables' => false,
            'can_manage_orders' => false,
            'can_manage_staff' => false,
            'can_manage_restaurant' => false,
            'can_view_analytics' => false,
            'can_manage_payments' => false,
            'can_view_reports' => false
        ];

        // Admin gets all permissions
        if ($user->isAdmin()) {
            return array_map(fn() => true, $permissions);
        }

        // Manager permissions
        if ($user->isManager()) {
            $permissions['can_view_menu'] = true;
            $permissions['can_manage_menu'] = true;
            $permissions['can_manage_tables'] = true;
            $permissions['can_manage_orders'] = true;
            $permissions['can_view_analytics'] = true;
            $permissions['can_view_reports'] = true;
        }

        // Staff permissions
        if ($user->isStaff()) {
            $permissions['can_view_menu'] = true;
            $permissions['can_manage_orders'] = true;
        }

        // Customer permissions
        if ($user->isCustomer()) {
            $permissions['can_view_menu'] = true;
        }

        return $permissions;
    }

    /**
     * Validate login attempt with comprehensive checks
     */
    public function validateLoginAttempt(User $user): array
    {
        if (!$user->isActive()) {
            return [
                'success' => false,
                'message' => 'Account has been deactivated. Please contact support.',
                'code' => 'ACCOUNT_INACTIVE'
            ];
        }

        if (!$user->isAdmin()) {
            return [
                'success' => false,
                'message' => 'Admin privileges required for this login endpoint.',
                'code' => 'INSUFFICIENT_PRIVILEGES'
            ];
        }

        return [
            'success' => true,
            'message' => 'Login validation passed'
        ];
    }

    /**
     * Get authentication context for user
     */
    public function getAuthenticationContext(User $user): array
    {
        $restaurant = $this->findRestaurantByAdmin($user);
        $permissions = $this->getUserPermissions($user);

        return [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isAdmin' => $user->isAdmin(),
                'isManager' => $user->isManager(),
                'isStaff' => $user->isStaff()
            ],
            'restaurant' => $restaurant ? [
                'id' => $restaurant->getId(),
                'name' => $restaurant->getName(),
                'isActive' => $restaurant->isActive()
            ] : null,
            'permissions' => $permissions,
            'can_access_admin' => $this->canAccessAdminFeatures($user)
        ];
    }

    /**
     * Generate user session data for frontend
     */
    public function generateSessionData(User $user): array
    {
        $restaurant = $this->findRestaurantByAdmin($user);
        
        return [
            'session_id' => uniqid('sess_', true),
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'role' => $user->isAdmin() ? 'admin' : 'user',
            'restaurant_id' => $restaurant?->getId(),
            'restaurant_name' => $restaurant?->getName(),
            'permissions' => $this->getUserPermissions($user),
            'created_at' => new \DateTime(),
            'last_activity' => new \DateTime()
        ];
    }

    /**
     * Check if user needs password change
     */
    public function requiresPasswordChange(User $user): bool
    {
        // Check if password is old (example: 90 days)
        $passwordAge = $user->getUpdatedAt()?->diff(new \DateTime())->days ?? 0;
        
        return $passwordAge > 90;
    }

    /**
     * Get user's restaurant staff count
     */
    public function getRestaurantStaffCount(User $user): int
    {
        $restaurant = $this->findRestaurantByAdmin($user);
        
        if (!$restaurant) {
            return 0;
        }

        // This would require a User-Restaurant relationship
        // For now, return a placeholder
        return 1; // Just the admin
    }

    /**
     * Check if restaurant setup is complete
     */
    public function isRestaurantSetupComplete(User $user): bool
    {
        $restaurant = $this->findRestaurantByAdmin($user);
        
        if (!$restaurant) {
            return false;
        }

        // Check if restaurant has basic information
        $hasName = !empty($restaurant->getName());
        $hasContact = !empty($restaurant->getEmail()) || !empty($restaurant->getPhoneNumber());
        $isActive = $restaurant->isActive();

        return $hasName && $hasContact && $isActive;
    }
}
