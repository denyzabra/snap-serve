<?php

namespace App\Command;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Service\AdminUserCreationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'snapserve:create-admin',
    description: 'Create an admin user with restaurant for testing purposes'
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AdminUserCreationService $adminUserCreationService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create an admin user with restaurant for testing purposes')
            ->setHelp('This command allows you to create an admin user and associated restaurant for testing the SnapServe application.')
            ->addArgument('email', InputArgument::OPTIONAL, 'Admin email address')
            ->addArgument('password', InputArgument::OPTIONAL, 'Admin password')
            ->addOption('restaurant-name', 'r', InputOption::VALUE_OPTIONAL, 'Restaurant name')
            ->addOption('first-name', 'f', InputOption::VALUE_OPTIONAL, 'Admin first name')
            ->addOption('last-name', 'l', InputOption::VALUE_OPTIONAL, 'Admin last name')
            ->addOption('verified', null, InputOption::VALUE_NONE, 'Mark user as email verified')
            ->addOption('active', null, InputOption::VALUE_NONE, 'Mark user and restaurant as active')
            ->addOption('interactive', 'i', InputOption::VALUE_NONE, 'Interactive mode');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('SnapServe Admin User Creator');
        $io->text('This command will create an admin user and associated restaurant for testing.');

        // Get input values
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $restaurantName = $input->getOption('restaurant-name');
        $firstName = $input->getOption('first-name');
        $lastName = $input->getOption('last-name');
        $isInteractive = $input->getOption('interactive');

        // Interactive mode or missing required arguments
        if ($isInteractive || !$email || !$password) {
            $email = $email ?: $io->ask('Admin email address', 'admin@snapserve.local');
            $password = $password ?: $io->askHidden('Admin password (min 8 characters)');
            $firstName = $firstName ?: $io->ask('Admin first name', 'John');
            $lastName = $lastName ?: $io->ask('Admin last name', 'Doe');
            $restaurantName = $restaurantName ?: $io->ask('Restaurant name', 'Test Restaurant');
        } else {
            // Use defaults for optional fields if not provided
            $firstName = $firstName ?: 'John';
            $lastName = $lastName ?: 'Doe';
            $restaurantName = $restaurantName ?: 'Test Restaurant';
        }

        // Validate input
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Invalid email address provided.');
            return Command::FAILURE;
        }

        if (strlen($password) < 8) {
            $io->error('Password must be at least 8 characters long.');
            return Command::FAILURE;
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error(sprintf('User with email "%s" already exists.', $email));
            return Command::FAILURE;
        }

        try {
            $io->section('Creating Admin User and Restaurant');

            // Create admin user and restaurant
            $result = $this->adminUserCreationService->createAdminWithRestaurant([
                'email' => $email,
                'password' => $password,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'restaurantName' => $restaurantName,
                'verified' => $input->getOption('verified') || !$isInteractive,
                'active' => $input->getOption('active') || !$isInteractive
            ]);

            $user = $result['user'];
            $restaurant = $result['restaurant'];

            // Display creation results
            $io->success('Admin user and restaurant created successfully!');

            $io->definitionList(
                ['Email' => $user->getEmail()],
                ['Name' => $user->getFullName()],
                ['User ID' => $user->getId()],
                ['Roles' => implode(', ', $user->getRoles())],
                ['Email Verified' => $user->isEmailVerified() ? 'Yes' : 'No'],
                ['Is Active' => $user->isActive() ? 'Yes' : 'No'],
                ['Created At' => $user->getCreatedAt()->format('Y-m-d H:i:s')]
            );

            $io->section('Restaurant Details');
            $io->definitionList(
                ['Restaurant Name' => $restaurant->getName()],
                ['Restaurant ID' => $restaurant->getId()],
                ['Is Active' => $restaurant->isActive() ? 'Yes' : 'No'],
                ['Is Verified' => $restaurant->isVerified() ? 'Yes' : 'No'],
                ['Created At' => $restaurant->getCreatedAt()->format('Y-m-d H:i:s')]
            );

            $io->section('Testing Information');
            $io->text([
                'You can now use these credentials to test the SnapServe API:',
                '',
                '📧 Email: ' . $user->getEmail(),
                '🔑 Password: ' . $password,
                '🏪 Restaurant ID: ' . $restaurant->getId(),
                '',
                'Login URL: POST /api/auth/login',
                'Restaurant Profile: GET /api/restaurants/' . $restaurant->getId() . '/profile'
            ]);

            $io->note('Save these credentials for testing purposes.');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to create admin user: ' . $e->getMessage());
            $io->text('Error details: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
