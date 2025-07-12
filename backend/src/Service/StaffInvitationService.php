<?php

namespace App\Service;

use App\DTO\StaffInvitationRequest;
use App\DTO\StaffOnboardingRequest;
use App\Entity\Restaurant;
use App\Entity\StaffInvitation;
use App\Entity\User;
use App\Repository\StaffInvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for managing staff invitations and onboarding
 */
class StaffInvitationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StaffInvitationRepository $invitationRepository,
        private UserRepository $userRepository,
        private TokenGeneratorService $tokenGenerator,
        private EmailService $emailService,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create and send staff invitation
     */
    public function createInvitation(
        StaffInvitationRequest $request,
        Restaurant $restaurant,
        User $invitedBy
    ): StaffInvitation {
        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($request->email);
        if ($existingUser) {
            throw new \InvalidArgumentException('A user with this email already exists');
        }

        // Check if there's already a pending invitation
        $existingInvitation = $this->invitationRepository->findByEmailAndRestaurant(
            $request->email,
            $restaurant->getId()
        );
        
        if ($existingInvitation) {
            throw new \InvalidArgumentException('A pending invitation already exists for this email');
        }

        // Create invitation
        $invitation = new StaffInvitation();
        $invitation->setEmail($request->email);
        $invitation->setFirstName($request->firstName);
        $invitation->setLastName($request->lastName);
        $invitation->setRole($request->role);
        $invitation->setRestaurant($restaurant);
        $invitation->setInvitedBy($invitedBy);
        $invitation->setToken($this->tokenGenerator->generateSecureToken());
        $invitation->setExpiresAt(new \DateTime("+{$request->expiryDays} days"));

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        // Send invitation email
        $this->sendInvitationEmail($invitation, $request->message);

        $this->logger->info('Staff invitation created and sent', [
            'invitation_id' => $invitation->getId(),
            'email' => $invitation->getEmail(),
            'restaurant_id' => $restaurant->getId(),
            'invited_by' => $invitedBy->getId()
        ]);

        return $invitation;
    }

    /**
     * Accept staff invitation and create user account
     */
    public function acceptInvitation(StaffOnboardingRequest $request): User
    {
        // Find valid invitation
        $invitation = $this->invitationRepository->findValidByToken($request->token);
        
        if (!$invitation) {
            throw new \InvalidArgumentException('Invalid or expired invitation token');
        }

        // Check if user already exists
        $existingUser = $this->userRepository->findByEmail($invitation->getEmail());
        if ($existingUser) {
            throw new \InvalidArgumentException('A user with this email already exists');
        }

        // Start transaction
        $this->entityManager->beginTransaction();

        try {
            // Create user account
            $user = new User();
            $user->setEmail($invitation->getEmail());
            $user->setFirstName($invitation->getFirstName());
            $user->setLastName($invitation->getLastName());
            $user->setRoles([$invitation->getRole()]);
            $user->setIsActive(true);
            $user->setEmailVerified(true); // Pre-verified through invitation
            $user->setPhoneNumber($request->phoneNumber);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $request->password);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);

            // Update invitation status
            $invitation->setStatus(StaffInvitation::STATUS_ACCEPTED);
            $invitation->setUser($user);
            $invitation->setAcceptedAt(new \DateTime());

            $this->entityManager->persist($invitation);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Staff invitation accepted and user created', [
                'invitation_id' => $invitation->getId(),
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'restaurant_id' => $invitation->getRestaurant()->getId()
            ]);

            return $user;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to accept staff invitation', [
                'token' => $request->token,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Cancel staff invitation
     */
    public function cancelInvitation(StaffInvitation $invitation, User $cancelledBy): void
    {
        if ($invitation->getStatus() !== StaffInvitation::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending invitations can be cancelled');
        }

        $invitation->setStatus(StaffInvitation::STATUS_CANCELLED);
        $invitation->setCancelledAt(new \DateTime());
        $invitation->setCancelledBy($cancelledBy);

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->logger->info('Staff invitation cancelled', [
            'invitation_id' => $invitation->getId(),
            'cancelled_by' => $cancelledBy->getId()
        ]);
    }

    /**
     * Remove staff invitation
     */
    public function removeInvitation(StaffInvitation $invitation, User $removedBy): void
    {
        $invitation->setStatus(StaffInvitation::STATUS_REMOVED);
        $invitation->setRemovedAt(new \DateTime());
        $invitation->setRemovedBy($removedBy);

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->logger->info('Staff invitation removed', [
            'invitation_id' => $invitation->getId(),
            'removed_by' => $removedBy->getId()
        ]);
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail(StaffInvitation $invitation, ?string $customMessage = null): void
    {
        $invitationUrl = sprintf(
            '%s/staff/onboard?token=%s',
            $_ENV['FRONTEND_URL'] ?? 'http://localhost:4040',
            $invitation->getToken()
        );

        $this->emailService->sendStaffInvitationEmail(
            $invitation,
            $invitationUrl,
            $customMessage
        );
    }

    /**
     * Get invitation statistics for restaurant
     */
    public function getInvitationStats(Restaurant $restaurant): array
    {
        return $this->invitationRepository->getRestaurantInvitationStats($restaurant->getId());
    }

    /**
     * Clean up expired invitations
     */
    public function cleanupExpiredInvitations(): int
    {
        return $this->invitationRepository->markExpiredInvitations();
    }
}
