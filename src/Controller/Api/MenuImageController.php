<?php

namespace App\Controller\Api;

use App\Entity\Menu;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/admin/menu', name: 'api_admin_menu_')]
#[IsGranted('ROLE_ADMIN')]
class MenuImageController extends AbstractController
{
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/webp'
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MenuRepository $menuRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('/{id}/image', name: 'upload_image', methods: ['POST'])]
    public function uploadImage(int $id, Request $request): JsonResponse
    {
        try {
            // Find the menu
            $menu = $this->menuRepository->find($id);
            if (!$menu) {
                return $this->json([
                    'success' => false,
                    'message' => 'Menu non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            // Get uploaded file
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $request->files->get('image');
            
            if (!$uploadedFile) {
                return $this->json([
                    'success' => false,
                    'message' => 'Aucun fichier image fourni'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate file
            $validationResult = $this->validateUploadedFile($uploadedFile);
            if (!$validationResult['valid']) {
                return $this->json([
                    'success' => false,
                    'message' => $validationResult['message']
                ], Response::HTTP_BAD_REQUEST);
            }

            // Set the image file (VichUploader will handle the upload)
            $menu->setImageFile($uploadedFile);

            // Validate entity
            $violations = $this->validator->validate($menu);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errors
                ], Response::HTTP_BAD_REQUEST);
            }

            // Save to database
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Image uploadée avec succès',
                'data' => [
                    'id' => $menu->getId(),
                    'imageUrl' => $menu->getImageUrl(),
                    'imageName' => $menu->getImageName(),
                    'imageSize' => $menu->getImageSize(),
                    'hasImage' => $menu->hasImage()
                ]
            ]);

        } catch (FileException $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur interne du serveur'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/image', name: 'delete_image', methods: ['DELETE'])]
    public function deleteImage(int $id): JsonResponse
    {
        try {
            $menu = $this->menuRepository->find($id);
            if (!$menu) {
                return $this->json([
                    'success' => false,
                    'message' => 'Menu non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$menu->hasImage()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Aucune image à supprimer'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Remove the image (VichUploader will handle file deletion)
            $menu->setImageFile(null);
            $menu->setImageName(null);
            $menu->setImageSize(null);

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Image supprimée avec succès',
                'data' => [
                    'id' => $menu->getId(),
                    'hasImage' => false,
                    'imageUrl' => null
                ]
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'image'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate uploaded file with comprehensive checks
     */
    private function validateUploadedFile(UploadedFile $file): array
    {
        // Check if upload was successful
        if (!$file->isValid()) {
            return [
                'valid' => false,
                'message' => 'Erreur lors de l\'upload du fichier: ' . $file->getErrorMessage()
            ];
        }

        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $maxSizeMB = self::MAX_FILE_SIZE / (1024 * 1024);
            return [
                'valid' => false,
                'message' => "Le fichier est trop volumineux. Taille maximum: {$maxSizeMB}MB"
            ];
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            return [
                'valid' => false,
                'message' => 'Type de fichier non autorisé. Formats acceptés: JPEG, PNG, WebP'
            ];
        }

        // Check if it's actually an image
        $imageInfo = @getimagesize($file->getPathname());
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'message' => 'Le fichier n\'est pas une image valide'
            ];
        }

        // Check image dimensions (optional - can be customized)
        [$width, $height] = $imageInfo;
        if ($width < 200 || $height < 150) {
            return [
                'valid' => false,
                'message' => 'Image trop petite. Dimensions minimales: 200x150 pixels'
            ];
        }

        if ($width > 4000 || $height > 4000) {
            return [
                'valid' => false,
                'message' => 'Image trop grande. Dimensions maximales: 4000x4000 pixels'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Fichier valide'
        ];
    }
} 