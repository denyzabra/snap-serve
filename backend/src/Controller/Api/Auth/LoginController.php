<?php

namespace App\Controller\Api\Auth;

use App\DTO\LoginRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_')]
class LoginController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer,
        private AuthenticationService $authService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Admin login endpoint - generates JWT token for verified admins
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
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

            $loginRequest = $this->serializer->deserialize(
                $requestData,
                LoginRequest::class,
                'json'
            );

            // Validate the DTO
            $violations = $this->validator->validate($loginRequest);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }

                return $this->json([
                    'error' => 'Validation failed',
                    'message' => 'Please check your credentials',
                    'details' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find user by email
            $user = $this->userRepository->findOneBy(['email' => $loginRequest->email]);
            
            if (!$user) {
                $this->logger->warning('Login attempt with non-existent email', [
                    'email' => $loginRequest->email,
                    'ip' => $request->getClientIp()
                ]);

                return $this->json([
                    'error' => 'Invalid credentials',
                    'message' => 'Email or password is incorrect'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Verify password
            if (!$this->passwordHasher->isPasswordValid($user, $loginRequest->password)) {
                $this->logger->warning('Login attempt with invalid password', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'ip' => $request->getClientIp()
                ]);

                return $this->json([
                    'error' => 'Invalid credentials',
                    'message' => 'Email or password is incorrect'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Validate login attempt using authentication service
            $validation = $this->authService->validateLoginAttempt($user);
            if (!$validation['success']) {
                $this->logger->warning('Login validation failed', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'validation_code' => $validation['code'] ?? 'UNKNOWN',
                    'message' => $validation['message']
                ]);

                $response = [
                    'error' => $validation['code'] ?? 'LOGIN_FAILED',
                    'message' => $validation['message']
                ];

                // Add specific handling for email not verified
                if (($validation['code'] ?? '') === 'EMAIL_NOT_VERIFIED') {
                    $response['verification_required'] = true;
                    $response['resend_verification_url'] = $this->generateUrl('api_auth_resend_verification');
                }

                return $this->json($response, Response::HTTP_FORBIDDEN);
            }

            // Generate JWT token using the correct service
            $token = $this->jwtManager->create($user);

            // Update last login timestamp
            $user->recordLogin();
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Get user's restaurant information
            $restaurant = $this->authService->findRestaurantByAdmin($user);

            // Get user permissions
            $permissions = $this->authService->getUserPermissions($user);

            $this->logger->info('Successful admin login', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'restaurant_id' => $restaurant?->getId(),
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]);

            // Return success response with token and user info
            return $this->json([
                'message' => 'Login successful',
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->getParameter('lexik_jwt_authentication.token_ttl'),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'firstName' => $user->getFirstName(),
                    'lastName' => $user->getLastName(),
                    'fullName' => $user->getFullName(),
                    'roles' => $user->getRoles(),
                    'isActive' => $user->isActive(),
                    'emailVerified' => true,
                    'lastLoginAt' => $user->getLastLoginAt()?->format('c')
                ],
                'restaurant' => $restaurant ? [
                    'id' => $restaurant->getId(),
                    'name' => $restaurant->getName(),
                    'email' => $restaurant->getEmail(),
                    'isActive' => $restaurant->isActive(),
                    'address' => $restaurant->getAddress(),
                    'phoneNumber' => $restaurant->getPhoneNumber(),
                    'logoUrl' => $restaurant->getLogoUrl()
                ] : null,
                'permissions' => $permissions,
                'dashboard_url' => $this->generateUrl('api_admin_dashboard')
            ], Response::HTTP_OK);

        } catch (\Symfony\Component\Serializer\Exception\NotEncodableValueException $e) {
            $this->logger->warning('Invalid JSON in login request', [
                'error' => $e->getMessage(),
                'content' => $request->getContent()
            ]);

            return $this->json([
                'error' => 'Invalid JSON',
                'message' => 'Request body must be valid JSON'
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during login', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Login failed',
                'message' => 'An unexpected error occurred. Please try again.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get current authenticated user information
     */
    #[Route('/me', name: 'current_user', methods: ['GET'])]
    public function getCurrentUser(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'error' => 'Not authenticated',
                'message' => 'Valid JWT token required'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $restaurant = $this->authService->findRestaurantByAdmin($user);
        $permissions = $this->authService->getUserPermissions($user);

        return $this->json([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'fullName' => $user->getFullName(),
                'roles' => $user->getRoles(),
                'isActive' => $user->isActive(),
                'emailVerified' => true,
                'lastLoginAt' => $user->getLastLoginAt()?->format('c'),
                'createdAt' => $user->getCreatedAt()?->format('c')
            ],
            'restaurant' => $restaurant ? [
                'id' => $restaurant->getId(),
                'name' => $restaurant->getName(),
                'email' => $restaurant->getEmail(),
                'isActive' => $restaurant->isActive(),
                'address' => $restaurant->getAddress(),
                'phoneNumber' => $restaurant->getPhoneNumber(),
                'logoUrl' => $restaurant->getLogoUrl(),
                'createdAt' => $restaurant->getCreatedAt()?->format('c')
            ] : null,
            'permissions' => $permissions
        ]);
    }

    /**
     * Logout endpoint - token blacklisting would be handled here
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(#[CurrentUser] ?User $user): JsonResponse
    {
        if ($user) {
            $this->logger->info('User logged out', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
        }

        // Note: JWT tokens are stateless, so logout is mainly client-side
        // In a production system, you might implement token blacklisting here
        
        return $this->json([
            'message' => 'Logout successful',
            'instructions' => 'Please remove the JWT token from client storage'
        ]);
    }

    /**
     * Refresh token endpoint
     */
    #[Route('/refresh', name: 'refresh_token', methods: ['POST'])]
    public function refreshToken(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json([
                'error' => 'Not authenticated',
                'message' => 'Valid JWT token required'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validate user is still active and verified
        $validation = $this->authService->validateLoginAttempt($user);
        if (!$validation['success']) {
            return $this->json([
                'error' => 'Token refresh denied',
                'message' => $validation['message']
            ], Response::HTTP_FORBIDDEN);
        }

        // Generate new token
        $newToken = $this->jwtManager->create($user);

        $this->logger->info('JWT token refreshed', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail()
        ]);

        return $this->json([
            'message' => 'Token refreshed successfully',
            'token' => $newToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->getParameter('lexik_jwt_authentication.token_ttl')
        ]);
    }

    /**
     * Check authentication status
     */
    #[Route('/status', name: 'auth_status', methods: ['GET'])]
    public function checkAuthStatus(#[CurrentUser] ?User $user): JsonResponse
    {
        return $this->json([
            'authenticated' => $user !== null,
            'user_id' => $user?->getId(),
            'email' => $user?->getEmail(),
            'roles' => $user?->getRoles() ?? [],
            'timestamp' => new \DateTime()
        ]);
    }
}
