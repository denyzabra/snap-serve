<?php

namespace App\Service;

use App\Entity\Restaurant;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service for handling file uploads
 */
class FileUploadService
{
    public function __construct(
        private SluggerInterface $slugger,  // This will be autowired automatically
        private LoggerInterface $logger,
        #[Autowire(param: 'app.upload_directory')] 
        private string $uploadDir,
        #[Autowire(param: 'app.max_upload_size')] 
        private int $maxUploadSize,
        #[Autowire(param: 'app.url')] 
        private string $appUrl
    ) {
    }

    /**
     * Upload restaurant logo
     */
    public function uploadRestaurantLogo(UploadedFile $file, Restaurant $restaurant): string
    {
        // Validate file
        $this->validateImageFile($file);

        // Generate safe filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = sprintf('%s-%s-%s.%s', 
            'logo',
            $restaurant->getId(),
            uniqid(),
            $file->guessExtension()
        );

        // Create restaurant upload directory
        $restaurantDir = $this->uploadDir . '/restaurants/' . $restaurant->getId();
        if (!is_dir($restaurantDir)) {
            mkdir($restaurantDir, 0755, true);
        }

        try {
            // Move file to upload directory
            $file->move($restaurantDir, $fileName);
            
            // Generate public URL
            $publicUrl = sprintf('%s/uploads/restaurants/%d/%s', 
                $this->appUrl,
                $restaurant->getId(),
                $fileName
            );

            $this->logger->info('Restaurant logo uploaded successfully', [
                'restaurant_id' => $restaurant->getId(),
                'filename' => $fileName,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'public_url' => $publicUrl
            ]);

            return $publicUrl;

        } catch (FileException $e) {
            $this->logger->error('Failed to upload restaurant logo', [
                'restaurant_id' => $restaurant->getId(),
                'error' => $e->getMessage(),
                'filename' => $fileName
            ]);

            throw new \RuntimeException('Failed to upload logo: ' . $e->getMessage());
        }
    }

    /**
     * Validate uploaded image file
     */
    private function validateImageFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxUploadSize) {
            throw new \InvalidArgumentException(sprintf(
                'File size (%d bytes) exceeds maximum allowed size (%d bytes)',
                $file->getSize(),
                $this->maxUploadSize
            ));
        }

        // Check file type
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid file type "%s". Allowed types: %s',
                $file->getMimeType(),
                implode(', ', $allowedMimeTypes)
            ));
        }
    }

    /**
     * Get allowed file types for frontend validation
     */
    public function getAllowedImageTypes(): array
    {
        return [
            'mimeTypes' => [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            ],
            'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'maxSize' => $this->maxUploadSize,
            'maxSizeFormatted' => $this->formatFileSize($this->maxUploadSize)
        ];
    }

    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
