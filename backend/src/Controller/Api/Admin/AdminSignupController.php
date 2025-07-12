<?php

namespace App\Controller\Api\Admin;

use App\DTO\AdminSignupRequest;
use App\Entity\Restaurant;
use App\Entity\User;
use App\Entity\VerificationToken;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\TokenGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/admins', name: 'api_admin_')]
class AdminSignupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer,
        private UserRepository $userRepository,
        private TokenGeneratorService $tokenGenerator,
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Register a new restaurant admin
     * 
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/signup', name: 'signup', methods: ['POST'])]
    public function signup(Request $request): JsonResponse
    {
        try {
            // Log the signup attempt
            $this->logger->info('Admin signup attempt initiated', [
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            // Validate request content type
            if (!$request->headers->contains('Content-Type', 'application/json')) {
                return $this->json([
                    'error' => 'Invalid content type',
                    'message' => 'Content-Type must be application/json'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Deserialize and validate request
            $requestData = $request->getContent();
            if (empty($requestData)) {
                return $this->json([
                    'error' => 'Empty request body',
                    'message' => 'Request body cannot be empty'
                ], Response::HTTP_BAD_REQUEST);
            }

            $signupRequest = $this->serializer->deserialize(
                $requestData,
                AdminSignupRequest::class,
                'json'
            );

            // Validate the DTO
            $violations = $this->validator->validate($signupRequest);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $field = $violation->getPropertyPath();
                    $message = $violation->getMessage();
                    $errors[$field] = $message;
                }

                $this->logger->warning('Admin signup validation failed', [
                    'errors' => $errors,
                    'email' => $signupRequest->email ?? 'unknown'
                ]);

                return $this->json([
                    'error' => 'Validation failed',
                    'message' => 'Please check your input data',
                    'details' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if user with this email already exists
            $existingUser = $this->userRepository->findByEmail($signupRequest->email);
            if ($existingUser) {
                $this->logger->warning('Admin signup attempted with existing email', [
                    'email' => $signupRequest->email,
                    'existing_user_id' => $existingUser->getId()
                ]);

                return $this->json([
                    'error' => 'Email already registered',
                    'message' => 'An account with this email address already exists.'
                ], Response::HTTP_CONFLICT);
            }

            // Start database transaction for data consistency
            $this->entityManager->beginTransaction();

            try {
                // Create restaurant entity (inactive by default)
                $restaurant = new Restaurant();
                $restaurant->setName($signupRequest->restaurantName);
                
                // Handle optional restaurant email
                if ($signupRequest->restaurantEmail !== null && !empty(trim($signupRequest->restaurantEmail))) {
                    $restaurant->setEmail($signupRequest->restaurantEmail);
                }
                
                $restaurant->setIsActive(false); // Pending verification
                $restaurant->setCreatedAt(new \DateTime());
                $restaurant->setUpdatedAt(new \DateTime());

                $this->entityManager->persist($restaurant);

                // Create admin user (inactive and unverified)
                $user = new User();
                $user->setEmail($signupRequest->email);
                $user->setFirstName($signupRequest->firstName);
                $user->setLastName($signupRequest->lastName);
                $user->setRoles([User::ROLE_ADMIN]);
                $user->setIsActive(false); // Will be activated after email verification
                $user->setEmailVerified(false);
                $user->setCreatedAt(new \DateTime());
                $user->setUpdatedAt(new \DateTime());
                
                // Hash password securely
                $hashedPassword = $this->passwordHasher->hashPassword($user, $signupRequest->password);
                $user->setPassword($hashedPassword);

                $this->entityManager->persist($user);

                // Generate email verification token
                $token = $this->tokenGenerator->generateSecureToken();
                $verificationToken = new VerificationToken();
                $verificationToken->setToken($token);
                $verificationToken->setType(VerificationToken::TYPE_EMAIL_VERIFICATION);
                $verificationToken->setUser($user);
                $verificationToken->setExpiresAt(
                    new \DateTime('+24 hours') // Token expires in 24 hours
                );

                $this->entityManager->persist($verificationToken);

                // Flush to database to get IDs
                $this->entityManager->flush();

                // Generate verification URL
                $verificationUrl = $this->generateUrl(
                    'app_verify_email', 
                    ['token' => $token], 
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                // Send verification email
                $this->emailService->sendAdminVerificationEmail(
                    $user,
                    $restaurant,
                    $verificationUrl
                );

                // Commit transaction
                $this->entityManager->commit();

                // Log successful signup
                $this->logger->info('Admin signup successful', [
                    'user_id' => $user->getId(),
                    'restaurant_id' => $restaurant->getId(),
                    'email' => $user->getEmail(),
                    'restaurant_name' => $restaurant->getName()
                ]);

                return $this->json([
                    'message' => 'Signup successful. Please check your email to verify your account.',
                    'data' => [
                        'userId' => $user->getId(),
                        'restaurantId' => $restaurant->getId(),
                        'email' => $user->getEmail(),
                        'restaurantName' => $restaurant->getName(),
                        'verificationRequired' => true
                    ]
                ], Response::HTTP_ACCEPTED);

            } catch (\Exception $e) {
                // Rollback transaction on any error
                $this->entityManager->rollback();
                
                $this->logger->error('Database error during admin signup', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $signupRequest->email ?? 'unknown'
                ]);

                throw $e;
            }

        } catch (\Symfony\Component\Serializer\Exception\NotEncodableValueException $e) {
            $this->logger->warning('Invalid JSON in admin signup request', [
                'error' => $e->getMessage(),
                'content' => $request->getContent()
            ]);

            return $this->json([
                'error' => 'Invalid JSON',
                'message' => 'Request body must be valid JSON'
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during admin signup', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Registration failed',
                'message' => 'An unexpected error occurred during registration. Please try again.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Health check endpoint for admin signup service
     */
    #[Route('/signup/health', name: 'signup_health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'service' => 'admin_signup',
            'timestamp' => new \DateTime(),
            'version' => '1.0.0'
        ]);
    }

    /**
     * Get signup requirements and validation rules
     */
    #[Route('/signup/requirements', name: 'signup_requirements', methods: ['GET'])]
    public function getSignupRequirements(): JsonResponse
    {
        return $this->json([
            'requirements' => [
                'email' => [
                    'required' => true,
                    'type' => 'email',
                    'max_length' => 180,
                    'description' => 'Valid email address for the admin account'
                ],
                'password' => [
                    'required' => true,
                    'min_length' => 8,
                    'requirements' => [
                        'At least one uppercase letter',
                        'At least one lowercase letter',
                        'At least one number',
                        'At least one special character (@$!%*?&)'
                    ],
                    'description' => 'Strong password for account security'
                ],
                'firstName' => [
                    'required' => true,
                    'max_length' => 100,
                    'description' => 'Admin first name'
                ],
                'lastName' => [
                    'required' => true,
                    'max_length' => 100,
                    'description' => 'Admin last name'
                ],
                'restaurantName' => [
                    'required' => true,
                    'max_length' => 255,
                    'description' => 'Name of the restaurant'
                ],
                'restaurantEmail' => [
                    'required' => false,
                    'type' => 'email',
                    'max_length' => 180,
                    'description' => 'Optional restaurant contact email'
                ]
            ],
            'process' => [
                'steps' => [
                    '1. Submit signup form with valid data',
                    '2. Receive confirmation email',
                    '3. Click verification link in email',
                    '4. Account and restaurant activated',
                    '5. Login with credentials'
                ],
                'verification_expiry' => '24 hours',
                'note' => 'Restaurant remains inactive until email verification'
            ]
        ]);
    }
}
