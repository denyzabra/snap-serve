<?php

namespace App\Controller\Api\Admin;

use App\DTO\StaffInvitationRequest;
use App\DTO\StaffOnboardingRequest;
use App\Entity\User;
use App\Repository\StaffInvitationRepository;
use App\Service\AuthenticationService;
use App\Service\StaffInvitationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/staff', name: 'api_admin_staff_')]
#[IsGranted('ROLE_ADMIN')]
class StaffController extends AbstractController
{
    public function __construct(
        private StaffInvitationService $staffInvitationService,
        private AuthenticationService $authService,
        private StaffInvitationRepository $invitationRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Send staff invitation
     */
    #[Route('/invite', name: 'invite', methods: ['POST'])]
    public function inviteStaff(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $restaurant = $this->authService->findRestaurantByAdmin($user);
            
            if (!$restaurant) {
                return $this->json([
                    'error' => 'Restaurant not found',
                    'message' => 'No restaurant associated with this admin account'
                ], Response::HTTP_NOT_FOUND);
            }

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

            $invitationRequest = $this->serializer->deserialize(
                $requestData,
                StaffInvitationRequest::class,
                'json'
            );

            // Validate the DTO
            $violations = $this->validator->validate($invitationRequest);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }

                return $this->json([
                    'error' => 'Validation failed',
                    'message' => 'Please check your input data',
                    'details' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create invitation
            $invitation = $this->staffInvitationService->createInvitation(
                $invitationRequest,
                $restaurant,
                $user
            );

            return $this->json([
                'message' => 'Staff invitation sent successfully',
                'invitation' => [
                    'id' => $invitation->getId(),
                    'email' => $invitation->getEmail(),
                    'fullName' => $invitation->getFullName(),
                    'role' => $invitation->getRole(),
                    'status' => $invitation->getStatus(),
                    'expiresAt' => $invitation->getExpiresAt()->format('c'),
                    'createdAt' => $invitation->getCreatedAt()->format('c')
                ]
            ], Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Invalid request',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send staff invitation', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Invitation failed',
                'message' => 'An error occurred while sending the invitation'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get all staff invitations for restaurant
     */
    #[Route('/invitations', name: 'invitations', methods: ['GET'])]
    public function getInvitations(#[CurrentUser] User $user): JsonResponse
    {
        try {
            $restaurant = $this->authService->findRestaurantByAdmin($user);
            
            if (!$restaurant) {
                return $this->json([
                    'error' => 'Restaurant not found',
                    'message' => 'No restaurant associated with this admin account'
                ], Response::HTTP_NOT_FOUND);
            }

            $invitations = $this->invitationRepository->findByRestaurant($restaurant->getId());
            $stats = $this->staffInvitationService->getInvitationStats($restaurant);

            $formattedInvitations = array_map(function ($invitation) {
                return [
                    'id' => $invitation->getId(),
                    'email' => $invitation->getEmail(),
                    'fullName' => $invitation->getFullName(),
                    'role' => $invitation->getRole(),
                    'status' => $invitation->getStatus(),
                    'invitedBy' => [
                        'id' => $invitation->getInvitedBy()->getId(),
                        'name' => $invitation->getInvitedBy()->getFullName()
                    ],
                    'createdAt' => $invitation->getCreatedAt()->format('c'),
                    'expiresAt' => $invitation->getExpiresAt()->format('c'),
                    'acceptedAt' => $invitation->getAcceptedAt()?->format('c'),
                    'cancelledAt' => $invitation->getCancelledAt()?->format('c'),
                    'isExpired' => $invitation->isExpired(),
                    'isPending' => $invitation->isPending(),
                    'canBeAccepted' => $invitation->canBeAccepted()
                ];
            }, $invitations);

            return $this->json([
                'invitations' => $formattedInvitations,
                'statistics' => $stats
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch staff invitations', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to fetch invitations',
                'message' => 'An error occurred while fetching invitations'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cancel staff invitation
     */
    #[Route('/invitations/{id}/cancel', name: 'cancel_invitation', methods: ['POST'])]
    public function cancelInvitation(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $restaurant = $this->authService->findRestaurantByAdmin($user);
            
            if (!$restaurant) {
                return $this->json([
                    'error' => 'Restaurant not found',
                    'message' => 'No restaurant associated with this admin account'
                ], Response::HTTP_NOT_FOUND);
            }

            $invitation = $this->invitationRepository->find($id);
            
            if (!$invitation || $invitation->getRestaurant()->getId() !== $restaurant->getId()) {
                return $this->json([
                    'error' => 'Invitation not found',
                    'message' => 'Invitation not found or access denied'
                ], Response::HTTP_NOT_FOUND);
            }

            $this->staffInvitationService->cancelInvitation($invitation, $user);

            return $this->json([
                'message' => 'Invitation cancelled successfully'
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'error' => 'Invalid request',
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel staff invitation', [
                'invitation_id' => $id,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Cancel failed',
                'message' => 'An error occurred while cancelling the invitation'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
