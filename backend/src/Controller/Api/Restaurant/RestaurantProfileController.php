<?php

namespace App\Controller\Api\Restaurant;

use App\DTO\RestaurantProfileRequest;
use App\Entity\User;
use App\Service\RestaurantService;
use App\Service\FileUploadService;
use App\Service\AuthenticationService;
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

#[Route('/api/restaurants', name: 'api_restaurant_')]
#[IsGranted('ROLE_ADMIN')]
class RestaurantProfileController extends AbstractController
{
    public function __construct(
        private AuthenticationService $authService,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get restaurant profile information
     */
    #[Route('/{id}/profile', name: 'get_profile', methods: ['GET'])]
    public function getProfile(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            // Get restaurant for admin user
            $restaurant = $this->authService->findRestaurantByAdmin($user);
            
            if (!$restaurant || $restaurant->getId() !== $id) {
                return $this->json([
                    'error' => 'Restaurant not found',
                    'message' => 'Restaurant not found or access denied'
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'restaurant' => [
                    'id' => $restaurant->getId(),
                    'name' => $restaurant->getName(),
                    'description' => $restaurant->getDescription(),
                    'phoneNumber' => $restaurant->getPhoneNumber(),
                    'email' => $restaurant->getEmail(),
                    'address' => $restaurant->getAddress(),
                    'logoUrl' => $restaurant->getLogoUrl(),
                    'isActive' => $restaurant->isActive(),
                    'createdAt' => $restaurant->getCreatedAt()?->format('c'),
                    'updatedAt' => $restaurant->getUpdatedAt()?->format('c')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching restaurant profile', [
                'restaurant_id' => $id,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to fetch restaurant profile',
                'message' => 'An error occurred while fetching the restaurant profile'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update restaurant profile information
     */
    #[Route('/{id}/profile', name: 'update_profile', methods: ['PATCH'])]
    public function updateProfile(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            // Get restaurant for admin user
            $restaurant = $this->authService->findRestaurantByAdmin($user);
            
            if (!$restaurant || $restaurant->getId() !== $id) {
                return $this->json([
                    'error' => 'Restaurant not found',
                    'message' => 'Restaurant not found or access denied'
                ], Response::HTTP_NOT_FOUND);
            }

            // Validate request content type
            if (!$request->headers->contains('Content-Type', 'application/json')) {
                return $this->json([
                    'error' => 'Invalid content type',
                    'message' => 'Content-Type must be application/json'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get request data
            $requestData = json_decode($request->getContent(), true);
            
            if (!$requestData) {
                return $this->json([
                    'error' => 'Invalid JSON',
                    'message' => 'Request body must be valid JSON'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update restaurant properties
            if (isset($requestData['name'])) {
                $restaurant->setName($requestData['name']);
            }
            
            if (isset($requestData['description'])) {
                $restaurant->setDescription($requestData['description']);
            }
            
            if (isset($requestData['phoneNumber'])) {
                $restaurant->setPhoneNumber($requestData['phoneNumber']);
            }
            
            if (isset($requestData['email'])) {
                $restaurant->setEmail($requestData['email']);
            }
            
            if (isset($requestData['address'])) {
                $restaurant->setAddress($requestData['address']);
            }

            // Save changes
            $this->entityManager->persist($restaurant);
            $this->entityManager->flush();

            $this->logger->info('Restaurant profile updated', [
                'restaurant_id' => $restaurant->getId(),
                'user_id' => $user->getId(),
                'updated_by' => $user->getEmail()
            ]);

            return $this->json([
                'message' => 'Restaurant profile updated successfully',
                'restaurant' => [
                    'id' => $restaurant->getId(),
                    'name' => $restaurant->getName(),
                    'updatedAt' => $restaurant->getUpdatedAt()?->format('c')
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error updating restaurant profile', [
                'restaurant_id' => $id,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Update failed',
                'message' => 'An error occurred while updating the restaurant profile'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload restaurant logo
     */
    #[Route('/{id}/logo', name: 'upload_logo', methods: ['POST'])]
    public function uploadLogo(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        try {
            // Get restaurant for admin user
            $restaurant = $this->authService->findRestaurantByAdmin($user);
            
            if (!$restaurant || $restaurant->getId() !== $id) {
                return $this->json([
                    'error' => 'Restaurant not found',
                    'message' => 'Restaurant not found or access denied'
                ], Response::HTTP_NOT_FOUND);
            }

            $uploadedFile = $request->files->get('logo');
            
            if (!$uploadedFile) {
                return $this->json([
                    'error' => 'No file uploaded',
                    'message' => 'Please select a logo file to upload'
                ], Response::HTTP_BAD_REQUEST);
            }

            // For now, we'll simulate logo upload
            // In a full implementation, you'd use FileUploadService
            $logoUrl = '/uploads/restaurants/' . $restaurant->getId() . '/' . uniqid() . '.jpg';
            
            $restaurant->setLogoUrl($logoUrl);
            $this->entityManager->persist($restaurant);
            $this->entityManager->flush();

            $this->logger->info('Restaurant logo uploaded', [
                'restaurant_id' => $restaurant->getId(),
                'user_id' => $user->getId(),
                'logo_url' => $logoUrl
            ]);

            return $this->json([
                'message' => 'Logo uploaded successfully',
                'logoUrl' => $logoUrl
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error uploading restaurant logo', [
                'restaurant_id' => $id,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Upload failed',
                'message' => 'An error occurred while uploading the logo'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get restaurant setup status
     */
    #[Route('/{id}/setup-status', name: 'setup_status', methods: ['GET'])]
    public function getSetupStatus(int $id, #[CurrentUser] User $user): JsonResponse
    {
        try {
            // Get restaurant for admin user
            $restaurant = $this->authService->findRestaurantByAdmin($user);
            
            if (!$restaurant || $restaurant->getId() !== $id) {
                return $this->json([
                    'error' => 'Restaurant not found',
                    'message' => 'Restaurant not found or access denied'
                ], Response::HTTP_NOT_FOUND);
            }

            // Calculate setup completion
            $steps = [
                'basicInfo' => !empty($restaurant->getName()) && !empty($restaurant->getDescription()),
                'contactInfo' => !empty($restaurant->getAddress()) && !empty($restaurant->getPhoneNumber()),
                'branding' => !empty($restaurant->getLogoUrl())
            ];

            $completedSteps = array_filter($steps);
            $completionPercentage = count($completedSteps) > 0 ? 
                (int) round((count($completedSteps) / count($steps)) * 100) : 0;

            return $this->json([
                'setupStatus' => [
                    'isComplete' => count($completedSteps) === count($steps),
                    'completionPercentage' => $completionPercentage,
                    'steps' => [
                        'basicInfo' => [
                            'completed' => $steps['basicInfo'],
                            'required' => true,
                            'title' => 'Basic Information',
                            'description' => 'Restaurant name and description'
                        ],
                        'contactInfo' => [
                            'completed' => $steps['contactInfo'],
                            'required' => true,
                            'title' => 'Contact Information',
                            'description' => 'Address and phone number'
                        ],
                        'branding' => [
                            'completed' => $steps['branding'],
                            'required' => false,
                            'title' => 'Branding',
                            'description' => 'Logo and brand colors'
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching setup status', [
                'restaurant_id' => $id,
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to fetch setup status',
                'message' => 'An error occurred while fetching the setup status'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
