<?php

namespace App\DataFixtures;

use App\Service\AdminUserCreationService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures for creating test admin users
 */
class AdminUserFixtures extends Fixture
{
    public function __construct(
        private AdminUserCreationService $adminUserCreationService
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create default admin user
        $this->adminUserCreationService->createAdminWithRestaurant([
            'email' => 'admin@snapserve.local',
            'password' => 'AdminPass123!',
            'firstName' => 'Default',
            'lastName' => 'Admin',
            'restaurantName' => 'SnapServe Demo Restaurant',
            'verified' => true,
            'active' => true
        ]);

        // Create sample admin with realistic data
        $this->adminUserCreationService->createSampleAdmin();
    }
}
