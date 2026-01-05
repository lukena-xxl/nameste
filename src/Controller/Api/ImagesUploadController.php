<?php

namespace App\Controller\Api;

use App\Service\UploadImageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ImagesUploadController extends AbstractController
{
    public function __construct(
        private UploadImageService $uploadImageService
    ) {
    }
    
    #[Route('/api/images/upload', name: 'api_images_upload', methods: ['POST'])]
    public function imagesUpload(Request $request): JsonResponse
    {
        try {
            // Check if a file was uploaded
            $file = $request->files->get('file');
            
            if (!$file) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
            }
            
            // Get the type parameter (default: fish)
            $type = $request->request->get('type', 'fish');
            
            // Process the upload
            $result = $this->uploadImageService->processUpload($file, $type);
            
            // Return appropriate status code
            $statusCode = $result['success'] ? 200 : 400;
            
            return new JsonResponse($result, $statusCode);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error processing upload: ' . $e->getMessage()
            ], 500);
        }
    }
}
