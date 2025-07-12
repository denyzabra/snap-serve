<?php

namespace App\Controller\Api\Auth;

use App\Entity\User;
use App\Entity\Restaurant;
use App\Entity\VerificationToken;
use App\Repository\VerificationTokenRepository;
use App\Repository\UserRepository;
use App\Repository\RestaurantRepository;
use App\Service\EmailService;
use App\Service\TokenGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api/auth', name: 'api_auth_')]
class EmailVerificationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VerificationTokenRepository $verificationTokenRepository,
        private UserRepository $userRepository,
        private RestaurantRepository $restaurantRepository,
        private EmailService $emailService,
        private TokenGeneratorService $tokenGenerator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handle email verification for admin registration
     */
    #[Route('/verify', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $token = $request->query->get('token');
        
        if (!$token) {
            $this->logger->warning('Email verification attempted without token');
            return $this->render('auth/verification_error.html.twig', [
                'error' => 'missing_token',
                'message' => 'No verification token provided'
            ]);
        }

        try {
            // Find the verification token
            $verificationToken = $this->verificationTokenRepository->findValidTokenByToken($token);
            
            if (!$verificationToken) {
                $this->logger->warning('Invalid or expired verification token used', [
                    'token' => substr($token, 0, 10) . '...' // Log partial token for security
                ]);
                
                return $this->render('auth/verification_error.html.twig', [
                    'error' => 'invalid_token',
                    'message' => 'Invalid or expired verification token'
                ]);
            }

            // Check if token is already used
            if ($verificationToken->isUsed()) {
                $this->logger->info('Already used verification token accessed', [
                    'token_id' => $verificationToken->getId(),
                    'user_id' => $verificationToken->getUser()->getId()
                ]);
                
                return $this->render('auth/verification_error.html.twig', [
                    'error' => 'token_already_used',
                    'message' => 'This verification link has already been used'
                ]);
            }

            // Check if token is expired
            if ($verificationToken->isExpired()) {
                $this->logger->warning('Expired verification token used', [
                    'token_id' => $verificationToken->getId(),
                    'user_id' => $verificationToken->getUser()->getId(),
                    'expired_at' => $verificationToken->getExpiresAt()->format('Y-m-d H:i:s')
                ]);
                
                return $this->render('auth/verification_error.html.twig', [
                    'error' => 'token_expired',
                    'message' => 'This verification link has expired. Please request a new one.',
                    'show_resend' => true,
                    'user_email' => $verificationToken->getUser()->getEmail()
                ]);
            }

            // Verify the user and activate restaurant
            $user = $verificationToken->getUser();
            
            // Start transaction
            $this->entityManager->beginTransaction();
            
            try {
                // Mark user as verified and active
                $user->setEmailVerified(true);
                $user->setIsActive(true);
                $user->setUpdatedAt(new \DateTime());
                
                // Find and activate the restaurant for admin users
                if ($user->isAdmin()) {
                    $restaurant = $this->findRestaurantByAdmin($user);
                    
                    if ($restaurant) {
                        $restaurant->setIsActive(true);
                        $restaurant->setUpdatedAt(new \DateTime());
                        $this->entityManager->persist($restaurant);
                    }
                }
                
                // Mark token as used
                $verificationToken->markAsUsed();
                
                // Persist changes
                $this->entityManager->persist($user);
                $this->entityManager->persist($verificationToken);
                $this->entityManager->flush();
                
                // Send welcome email for admin users
                if ($user->isAdmin() && isset($restaurant)) {
                    $this->emailService->sendWelcomeEmail($user, $restaurant);
                }
                
                // Commit transaction
                $this->entityManager->commit();
                
                $this->logger->info('Email verification successful', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'user_type' => $user->isAdmin() ? 'admin' : 'user',
                    'restaurant_id' => isset($restaurant) ? $restaurant->getId() : null
                ]);
                
                return $this->render('auth/verification_success.html.twig', [
                    'user' => $user,
                    'restaurant' => $restaurant ?? null,
                    'is_admin' => $user->isAdmin(),
                    'dashboard_url' => $user->isAdmin() ? 
                        $this->generateUrl('api_admin_dashboard') : 
                        $this->generateUrl('api_user_dashboard')
                ]);
                
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Email verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'token' => substr($token, 0, 10) . '...'
            ]);
            
            return $this->render('auth/verification_error.html.twig', [
                'error' => 'system_error',
                'message' => 'An error occurred during verification. Please try again or contact support.'
            ]);
        }
    }

    /**
     * Resend verification email
     */
    #[Route('/verify/resend', name: 'resend_verification', methods: ['POST'])]
    public function resendVerificationEmail(Request $request): Response
    {
        $email = $request->request->get('email');
        
        if (!$email) {
            return $this->json([
                'error' => 'Email address is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userRepository->findByEmail($email);
            
            if (!$user) {
                // Don't reveal if email exists for security
                return $this->json([
                    'message' => 'If the email address is registered, a new verification email will be sent.'
                ]);
            }

            // Check if user is already verified
            if ($user->isEmailVerified()) {
                return $this->json([
                    'message' => 'This email address is already verified.'
                ]);
            }

            // Invalidate existing tokens
            $existingTokens = $this->verificationTokenRepository->findBy([
                'user' => $user,
                'type' => VerificationToken::TYPE_EMAIL_VERIFICATION,
                'isUsed' => false
            ]);
            
            foreach ($existingTokens as $token) {
                $token->setIsUsed(true);
                $this->entityManager->persist($token);
            }

            // Create new verification token
            $newToken = $this->tokenGenerator->generateSecureToken();
            
            $verificationToken = new VerificationToken();
            $verificationToken->setToken($newToken);
            $verificationToken->setType(VerificationToken::TYPE_EMAIL_VERIFICATION);
            $verificationToken->setUser($user);
            $verificationToken->setExpiresAt(new \DateTime('+24 hours'));
            
            $this->entityManager->persist($verificationToken);
            $this->entityManager->flush();

            // Send new verification email
            $verificationUrl = $this->generateUrl('api_auth_verify_email', 
                ['token' => $newToken], 
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            if ($user->isAdmin()) {
                $restaurant = $this->findRestaurantByAdmin($user);
                $this->emailService->sendAdminVerificationEmail($user, $restaurant, $verificationUrl);
            } else {
                // For future: send regular user verification email
                $this->emailService->sendUserVerificationEmail($user, $verificationUrl);
            }

            $this->logger->info('Verification email resent', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'user_type' => $user->isAdmin() ? 'admin' : 'user'
            ]);

            return $this->json([
                'message' => 'A new verification email has been sent to your email address.'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to resend verification email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to resend verification email. Please try again.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check verification status
     */
    #[Route('/verify/status', name: 'verification_status', methods: ['GET'])]
    public function checkVerificationStatus(Request $request): Response
    {
        $email = $request->query->get('email');
        
        if (!$email) {
            return $this->json([
                'error' => 'Email address is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $this->userRepository->findByEmail($email);
            
            if (!$user) {
                return $this->json([
                    'verified' => false,
                    'exists' => false
                ]);
            }

            return $this->json([
                'verified' => $user->isEmailVerified(),
                'active' => $user->isActive(),
                'exists' => true,
                'user_id' => $user->getId(),
                'user_type' => $user->isAdmin() ? 'admin' : 'user'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to check verification status', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to check verification status'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Find restaurant associated with admin user
     */
    private function findRestaurantByAdmin(User $user): ?Restaurant
    {
        // Find restaurant created around the same time as the user
        // This is a simplified approach - in production you'd have a direct relationship
        
        $restaurants = $this->restaurantRepository->createQueryBuilder('r')
            ->where('r.createdAt >= :userCreated')
            ->andWhere('r.createdAt <= :userCreatedPlus')
            ->andWhere('r.isActive = false')
            ->setParameter('userCreated', $user->getCreatedAt())
            ->setParameter('userCreatedPlus', $user->getCreatedAt()->modify('+1 hour'))
            ->orderBy('r.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $restaurants[0] ?? null;
    }
}
