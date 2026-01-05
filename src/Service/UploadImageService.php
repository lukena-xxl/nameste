<?php

namespace App\Service;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadImageService
{
    private ImageManager $imageManager;
    
    public function __construct(
        private ParameterBagInterface $params
    ) {
        $this->imageManager = new ImageManager(new Driver());
    }
    
    /**
     * Validates the uploaded file against configured constraints
     * 
     * @param UploadedFile $file
     * @return array|null Returns null if valid, or array with error message
     */
    public function validateUpload(UploadedFile $file): ?array
    {
        // Check if file is valid
        if (!$file->isValid()) {
            return ['success' => false, 'message' => 'Invalid file upload'];
        }
        
        // Get allowed MIME types
        $allowedMimeTypes = explode(',', $this->params->get('app.image.upload.allowed_mime_types'));
        $allowedMimeTypes = array_map('trim', $allowedMimeTypes);
        
        // Validate MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return [
                'success' => false, 
                'message' => sprintf(
                    'Invalid file type. Allowed types: %s',
                    implode(', ', $allowedMimeTypes)
                )
            ];
        }
        
        // Validate file size
        $maxSize = $this->params->get('app.image.upload.allowed_max_size');
        if ($file->getSize() > $maxSize) {
            return [
                'success' => false, 
                'message' => sprintf(
                    'File size exceeds maximum allowed size of %s MB',
                    round($maxSize / 1024 / 1024, 2)
                )
            ];
        }
        
        return null;
    }
    
    /**
     * Processes and saves the uploaded image with thumbnails
     * 
     * @param UploadedFile $file
     * @param string $type The image type (default: fish)
     * @return array Response data with success status and data/message
     */
    public function processUpload(UploadedFile $file, string $type = 'fish'): array
    {
        try {
            // Validate the upload
            $validationError = $this->validateUpload($file);
            if ($validationError) {
                return $validationError;
            }
            
            // Get type-specific configuration
            $aspectRatio = $this->getTypeParam($type, 'aspect_ratio', 1.5);
            $targetWidth = $this->getTypeParam($type, 'width', 800);
            $outputMimeType = $this->getTypeParam($type, 'mime_type', 'image/webp');
            $quality = $this->getTypeParam($type, 'quality', 85);
            
            // Load the original image
            $image = $this->imageManager->read($file->getPathname());
            
            // Process main image
            $mainImagePath = $this->processImage(
                $image,
                $targetWidth,
                $aspectRatio,
                $outputMimeType,
                $quality,
                $type,
                'main'
            );
            
            // Process thumbnails
            $thumbConfigs = $this->getThumbConfigs($type);
            foreach ($thumbConfigs as $thumbName => $thumbWidth) {
                $this->processImage(
                    clone $image,
                    $thumbWidth,
                    $aspectRatio,
                    $outputMimeType,
                    $quality,
                    $type,
                    $thumbName
                );
            }
            
            return [
                'success' => true,
                'data' => $mainImagePath
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing image: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process a single image with aspect ratio contain logic
     * 
     * @param mixed $image Intervention Image instance
     * @param int $targetWidth
     * @param float $aspectRatio
     * @param string $outputMimeType
     * @param int $quality
     * @param string $type
     * @param string $variant 'main' or thumbnail name
     * @return string URL path to the saved image
     */
    private function processImage(
        $image,
        int $targetWidth,
        float $aspectRatio,
        string $outputMimeType,
        int $quality,
        string $type,
        string $variant
    ): string {
        // Calculate target height based on aspect ratio
        $targetHeight = (int) round($targetWidth / $aspectRatio);
        
        // Create a white canvas
        $canvas = $this->imageManager->create($targetWidth, $targetHeight);
        $canvas->fill('ffffff');
        
        // Scale the image to fit within bounds (contain behavior)
        $image->scaleDown($targetWidth, $targetHeight);
        
        // Get dimensions of scaled image
        $scaledWidth = $image->width();
        $scaledHeight = $image->height();
        
        // Calculate position to center the image
        $x = (int) round(($targetWidth - $scaledWidth) / 2);
        $y = (int) round(($targetHeight - $scaledHeight) / 2);
        
        // Place the scaled image on the canvas
        $canvas->place($image, 'top-left', $x, $y);
        
        // Generate unique filename
        $uuid = Uuid::uuid4()->toString();
        $extension = $this->getExtensionFromMimeType($outputMimeType);
        $filename = $uuid . '.' . $extension;
        
        // Determine save path
        $tmpDir = $this->params->get('app.image.upload.tmp_dir');
        if ($variant === 'main') {
            $savePath = $tmpDir . '/' . $type;
        } else {
            $savePath = $tmpDir . '/' . $type . '/thumbs/' . $variant;
        }
        
        // Create directory if it doesn't exist
        if (!is_dir($savePath)) {
            mkdir($savePath, 0755, true);
        }
        
        // Save the image
        $fullPath = $savePath . '/' . $filename;
        
        // Encode and save based on mime type
        if ($outputMimeType === 'image/jpeg') {
            $canvas->toJpeg($quality)->save($fullPath);
        } elseif ($outputMimeType === 'image/png') {
            $canvas->toPng()->save($fullPath);
        } elseif ($outputMimeType === 'image/webp') {
            $canvas->toWebp($quality)->save($fullPath);
        } else {
            $canvas->save($fullPath);
        }
        
        // Return URL path (relative to web root)
        // Since tmpDir is configured as public/uploads/tmp, extract the path after 'public'
        $projectDir = $this->params->get('kernel.project_dir');
        $publicDir = $projectDir . '/public';
        
        if (strpos($fullPath, $publicDir) === 0) {
            // File is under public directory - return path relative to web root
            $urlPath = str_replace($publicDir, '', $fullPath);
        } else {
            // Fallback: assume the tmpDir parameter is correctly configured under public
            // Extract path after the tmpDir base
            $tmpDirBase = $this->params->get('app.image.upload.tmp_dir');
            $urlPath = str_replace($projectDir . '/public', '', $fullPath);
        }
        
        return $urlPath;
    }
    
    /**
     * Get type-specific parameter
     */
    private function getTypeParam(string $type, string $param, $default)
    {
        $key = sprintf('app.image.%s.upload.%s', $type, $param);
        return $this->params->has($key) ? $this->params->get($key) : $default;
    }
    
    /**
     * Get thumbnail configurations for a type
     * 
     * @return array Array of thumbnail name => width
     */
    private function getThumbConfigs(string $type): array
    {
        $thumbs = [];
        $prefix = sprintf('app.image.%s.upload.thumbs.', $type);
        
        // Get all parameters
        $allParams = $this->params->all();
        
        foreach ($allParams as $key => $value) {
            if (strpos($key, $prefix) === 0 && strpos($key, '.width') !== false) {
                // Extract thumb name
                $thumbName = str_replace([$prefix, '.width'], '', $key);
                $thumbs[$thumbName] = (int) $value;
            }
        }
        
        return $thumbs;
    }
    
    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        
        return $map[$mimeType] ?? 'jpg';
    }
}
