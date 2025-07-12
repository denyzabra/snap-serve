<?php

namespace App\Service;

use App\Entity\Restaurant;
use App\Entity\User;
use App\Entity\StaffInvitation;
use App\Repository\UserRepository;
use App\Repository\StaffInvitationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service for managing restaurant staff members
 * Handles staff invitations, user creation, and role management
 */
class StaffManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private StaffInvitationRepository $staffInvitationRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailService $emailService, // FIXED: Use EmailService instead of StaffInvitationService
        private AuthenticationService $authService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Invite a staff member to join a restaurant
     */
    public function inviteStaffMember(Restaurant $restaurant, array $invitationData, User $invitedBy): StaffInvitation
    {
        // Validate invitation data
        $this->validateInvitationData($invitationData);

        // Check if user already exists
        $existingUser = $this->userRepository->findOneBy(['email' => $invitationData['email']]);
        if ($existingUser) {
            throw new \InvalidArgumentException('A user with this email already exists');
        }

        // Check if invitation already exists
        $existingInvitation = $this->staffInvitationRepository->findActiveInvitationByEmail($invitationData['email']);
        if ($existingInvitation) {
            throw new \InvalidArgumentException('An active invitation for this email already exists');
        }

        try {
            $this->entityManager->beginTransaction();

            // Create staff invitation
            $invitation = new StaffInvitation();
            $invitation->setEmail($invitationData['email']);
            $invitation->setFirstName($invitationData['firstName']);
            $invitation->setLastName($invitationData['lastName']);
            $invitation->setRole($invitationData['role']);
            $invitation->setRestaurant($restaurant);
            $invitation->setInvitedBy($invitedBy);
            $invitation->setStatus(StaffInvitation::STATUS_PENDING);
            
            // Generate invitation token
            $token = $this->generateInvitationToken();
            $invitation->setToken($token);
            $invitation->setExpiresAt(new \DateTime('+7 days'));

            $this->entityManager->persist($invitation);
            $this->entityManager->flush();

            // FIXED: Generate invitation URL and use correct EmailService method
            $invitationUrl = $this->urlGenerator->generate('api_staff_accept_invitation', 
                ['token' => $token], 
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // FIXED: Use EmailService with correct method name and parameters
            $customMessage = $invitationData['customMessage'] ?? null;
            $this->emailService->sendStaffInvitationEmail($invitation, $invitationUrl, $customMessage);

            $this->entityManager->commit();

            $this->logger->info('Staff invitation created', [
                'invitation_id' => $invitation->getId(),
                'email' => $invitation->getEmail(),
                'restaurant_id' => $restaurant->getId(),
                'invited_by' => $invitedBy->getId()
            ]);

            return $invitation;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to create staff invitation', [
                'email' => $invitationData['email'],
                'restaurant_id' => $restaurant->getId(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Accept a staff invitation and create user account
     */
    public function acceptInvitation(string $token, array $userData): User
    {
        $invitation = $this->staffInvitationRepository->findValidInvitationByToken($token);
        
        if (!$invitation) {
            throw new \InvalidArgumentException('Invalid or expired invitation token');
        }

        if ($invitation->getStatus() !== StaffInvitation::STATUS_PENDING) {
            throw new \InvalidArgumentException('This invitation has already been processed');
        }

        try {
            $this->entityManager->beginTransaction();

            // Create user account
            $user = new User();
            $user->setEmail($invitation->getEmail());
            $user->setFirstName($invitation->getFirstName());
            $user->setLastName($invitation->getLastName());
            $user->setRoles([$invitation->getRole()]);
            $user->setIsActive(true);
            $user->setEmailVerified(true);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);

            // Update invitation status
            $invitation->setStatus(StaffInvitation::STATUS_ACCEPTED);
            $invitation->setAcceptedAt(new \DateTime());
            $invitation->setUser($user);

            $this->entityManager->persist($invitation);
            $this->entityManager->flush();

            // FIXED: Use EmailService with correct method name
            $this->emailService->sendStaffWelcomeEmail($user, $invitation->getRestaurant());

            $this->entityManager->commit();

            $this->logger->info('Staff invitation accepted', [
                'invitation_id' => $invitation->getId(),
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return $user;

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            $this->logger->error('Failed to accept staff invitation', [
                'token' => substr($token, 0, 10) . '...',
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Get all staff members for a restaurant
     */
    public function getRestaurantStaff(Restaurant $restaurant): array
    {
        // Get accepted invitations with users
        $acceptedInvitations = $this->staffInvitationRepository->findBy([
            'restaurant' => $restaurant,
            'status' => StaffInvitation::STATUS_ACCEPTED
        ]);

        $staff = [];
        foreach ($acceptedInvitations as $invitation) {
            if ($invitation->getUser()) {
                $staff[] = [
                    'user' => $invitation->getUser(),
                    'invitation' => $invitation,
                    'joinedAt' => $invitation->getAcceptedAt()
                ];
            }
        }

        return $staff;
    }

    /**
     * Get pending invitations for a restaurant
     */
    public function getPendingInvitations(Restaurant $restaurant): array
    {
        return $this->staffInvitationRepository->findBy([
            'restaurant' => $restaurant,
            'status' => StaffInvitation::STATUS_PENDING
        ]);
    }

    /**
     * Cancel a pending invitation
     */
    public function cancelInvitation(int $invitationId, User $cancelledBy): void
    {
        $invitation = $this->staffInvitationRepository->find($invitationId);
        
        if (!$invitation) {
            throw new \InvalidArgumentException('Invitation not found');
        }

        if ($invitation->getStatus() !== StaffInvitation::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending invitations can be cancelled');
        }

        // Check if user has permission to cancel
        $restaurant = $this->authService->findRestaurantByAdmin($cancelledBy);
        if (!$restaurant || $restaurant->getId() !== $invitation->getRestaurant()->getId()) {
            throw new \InvalidArgumentException('Access denied');
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
     * Remove a staff member from restaurant
     */
    public function removeStaffMember(int $userId, User $removedBy): void
    {
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        // Check if user has permission to remove staff
        $restaurant = $this->authService->findRestaurantByAdmin($removedBy);
        if (!$restaurant) {
            throw new \InvalidArgumentException('Access denied');
        }

        // Find the staff invitation
        $invitation = $this->staffInvitationRepository->findOneBy([
            'user' => $user,
            'restaurant' => $restaurant,
            'status' => StaffInvitation::STATUS_ACCEPTED
        ]);

        if (!$invitation) {
            throw new \InvalidArgumentException('Staff member not found in this restaurant');
        }

        // Deactivate user instead of deleting
        $user->setIsActive(false);
        $invitation->setStatus(StaffInvitation::STATUS_REMOVED);
        $invitation->setRemovedAt(new \DateTime());
        $invitation->setRemovedBy($removedBy);

        $this->entityManager->persist($user);
        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        $this->logger->info('Staff member removed', [
            'user_id' => $user->getId(),
            'restaurant_id' => $restaurant->getId(),
            'removed_by' => $removedBy->getId()
        ]);
    }

    /**
     * Update staff member role
     */
    public function updateStaffRole(int $userId, string $newRole, User $updatedBy): void
    {
        $user = $this->userRepository->find($userId);
        
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }

        // Validate role
        $validRoles = [User::ROLE_STAFF, User::ROLE_MANAGER];
        if (!in_array($newRole, $validRoles)) {
            throw new \InvalidArgumentException('Invalid role');
        }

        // Check if user has permission
        $restaurant = $this->authService->findRestaurantByAdmin($updatedBy);
        if (!$restaurant) {
            throw new \InvalidArgumentException('Access denied');
        }

        // Get old role for email notification
        $oldRole = $user->getRoles()[0] ?? User::ROLE_USER;

        // Update user roles
        $currentRoles = $user->getRoles();
        $filteredRoles = array_filter($currentRoles, function($role) {
            return !in_array($role, [User::ROLE_STAFF, User::ROLE_MANAGER]);
        });
        $filteredRoles[] = $newRole;

        $user->setRoles(array_values($filteredRoles));

        // Update invitation record
        $invitation = $this->staffInvitationRepository->findOneBy([
            'user' => $user,
            'restaurant' => $restaurant,
            'status' => StaffInvitation::STATUS_ACCEPTED
        ]);

        if ($invitation) {
            $invitation->setRole($newRole);
            $this->entityManager->persist($invitation);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // FIXED: Send role update notification email using EmailService
        $this->emailService->sendStaffRoleUpdateEmail($user, $oldRole, $newRole, $restaurant);

        $this->logger->info('Staff role updated', [
            'user_id' => $user->getId(),
            'old_role' => $oldRole,
            'new_role' => $newRole,
            'updated_by' => $updatedBy->getId()
        ]);
    }

    /**
     * Generate invitation token
     */
    private function generateInvitationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate invitation data
     */
    private function validateInvitationData(array $data): void
    {
        $required = ['email', 'firstName', 'lastName', 'role'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }

        $validRoles = [User::ROLE_STAFF, User::ROLE_MANAGER];
        if (!in_array($data['role'], $validRoles)) {
            throw new \InvalidArgumentException('Invalid role specified');
        }
    }

    /**
     * Get staff statistics for a restaurant
     */
    public function getStaffStatistics(Restaurant $restaurant): array
    {
        $totalStaff = $this->staffInvitationRepository->count([
            'restaurant' => $restaurant,
            'status' => StaffInvitation::STATUS_ACCEPTED
        ]);

        $pendingInvitations = $this->staffInvitationRepository->count([
            'restaurant' => $restaurant,
            'status' => StaffInvitation::STATUS_PENDING
        ]);

        $staffByRole = $this->staffInvitationRepository->getStaffCountByRole($restaurant);

        return [
            'totalStaff' => $totalStaff,
            'pendingInvitations' => $pendingInvitations,
            'staffByRole' => $staffByRole,
            'activeStaff' => $totalStaff, // Assuming all accepted are active
        ];
    }

    /**
     * Resend staff invitation email
     */
    public function resendInvitation(int $invitationId, User $resendBy): void
    {
        $invitation = $this->staffInvitationRepository->find($invitationId);
        
        if (!$invitation) {
            throw new \InvalidArgumentException('Invitation not found');
        }

        if ($invitation->getStatus() !== StaffInvitation::STATUS_PENDING) {
            throw new \InvalidArgumentException('Only pending invitations can be resent');
        }

        // Check if user has permission
        $restaurant = $this->authService->findRestaurantByAdmin($resendBy);
        if (!$restaurant || $restaurant->getId() !== $invitation->getRestaurant()->getId()) {
            throw new \InvalidArgumentException('Access denied');
        }

        // Generate new invitation URL
        $invitationUrl = $this->urlGenerator->generate('api_staff_accept_invitation', 
            ['token' => $invitation->getToken()], 
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Resend email
        $this->emailService->sendStaffInvitationEmail($invitation, $invitationUrl);

        $this->logger->info('Staff invitation resent', [
            'invitation_id' => $invitation->getId(),
            'resent_by' => $resendBy->getId()
        ]);
    }
}
