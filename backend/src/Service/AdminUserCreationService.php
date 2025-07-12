<?php

namespace App\Service;

use App\Entity\Restaurant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for creating admin users with associated restaurants
 * Used for testing and administrative purposes
 */
class AdminUserCreationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create an admin user with associated restaurant
     * 
     * @param array $data User and restaurant data
     * @return array Created user and restaurant entities
     * @throws \Exception If creation fails
     */
    public function createAdminWithRestaurant(array $data): array
    {
        // Validate required fields
        $this->validateRequiredFields($data);

        // Start database transaction
        $this->entityManager->beginTransaction();

        try {
            // Create the restaurant first
            $restaurant = $this->createRestaurant($data);

            // Create the admin user
            $user = $this->createAdminUser($data);

            // Persist both entities
            $this->entityManager->persist($restaurant);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Commit transaction
            $this->entityManager->commit();

            $this->logger->info('Admin user and restaurant created successfully', [
                'user_id' => $user->getId(),
                'user_email' => $user->getEmail(),
                'restaurant_id' => $restaurant->getId(),
                'restaurant_name' => $restaurant->getName()
            ]);

            return [
                'user' => $user,
                'restaurant' => $restaurant
            ];

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to create admin user and restaurant', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? 'unknown'
            ]);

            throw $e;
        }
    }

    /**
     * Create restaurant entity
     */
    private function createRestaurant(array $data): Restaurant
    {
        $restaurant = new Restaurant();
        $restaurant->setName($data['restaurantName']);
        $restaurant->setEmail($data['email']);
        $restaurant->setDescription('Test restaurant created for development and testing purposes');
        $restaurant->setAddress('123 Test Street, Test City, TC 12345');
        $restaurant->setPhoneNumber('+1-555-TEST-123');
        $restaurant->setCuisineType(Restaurant::CUISINE_AMERICAN);
        $restaurant->setServiceTypes([
            Restaurant::SERVICE_DINE_IN,
            Restaurant::SERVICE_TAKEOUT
        ]);
        $restaurant->setIsActive($data['active'] ?? true);
        $restaurant->setIsVerified($data['verified'] ?? true);
        $restaurant->setCreatedAt(new \DateTime());
        $restaurant->setUpdatedAt(new \DateTime());

        return $restaurant;
    }

    /**
     * Create admin user entity
     */
    private function createAdminUser(array $data): User
    {
        $user = new User();
        $user->setEmail($data['email']);
        $user->setFirstName($data['firstName']);
        $user->setLastName($data['lastName']);
        $user->setRoles([User::ROLE_ADMIN]);
        $user->setIsActive($data['active'] ?? true);
        $user->setEmailVerified($data['verified'] ?? true);
        $user->setCreatedAt(new \DateTime());
        $user->setUpdatedAt(new \DateTime());

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        return $user;
    }

    /**
     * Validate required fields
     */
    private function validateRequiredFields(array $data): void
    {
        $required = ['email', 'password', 'firstName', 'lastName', 'restaurantName'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid email address");
        }

        if (strlen($data['password']) < 8) {
            throw new \InvalidArgumentException("Password must be at least 8 characters long");
        }
    }

    /**
     * Create multiple test admin users
     * 
     * @param int $count Number of users to create
     * @return array Created users and restaurants
     */
    public function createMultipleTestAdmins(int $count = 3): array
    {
        $results = [];
        
        for ($i = 1; $i <= $count; $i++) {
            $data = [
                'email' => "admin{$i}@snapserve.local",
                'password' => 'TestPass123!',
                'firstName' => "Admin{$i}",
                'lastName' => "User",
                'restaurantName' => "Test Restaurant {$i}",
                'verified' => true,
                'active' => true
            ];

            try {
                $results[] = $this->createAdminWithRestaurant($data);
            } catch (\Exception $e) {
                $this->logger->warning("Failed to create test admin {$i}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Create admin user with sample restaurant data
     */
    public function createSampleAdmin(): array
    {
        $restaurantTypes = [
            ['name' => 'Bella Vista Italian', 'cuisine' => Restaurant::CUISINE_ITALIAN],
            ['name' => 'Golden Dragon Chinese', 'cuisine' => Restaurant::CUISINE_CHINESE],
            ['name' => 'Spice Garden Indian', 'cuisine' => Restaurant::CUISINE_INDIAN],
            ['name' => 'Casa Miguel Mexican', 'cuisine' => Restaurant::CUISINE_MEXICAN],
            ['name' => 'The Local Cafe', 'cuisine' => Restaurant::CUISINE_CAFE]
        ];

        $sample = $restaurantTypes[array_rand($restaurantTypes)];

        $data = [
            'email' => 'sample@snapserve.local',
            'password' => 'SamplePass123!',
            'firstName' => 'Sample',
            'lastName' => 'Admin',
            'restaurantName' => $sample['name'],
            'verified' => true,
            'active' => true
        ];

        return $this->createAdminWithRestaurant($data);
    }
}
