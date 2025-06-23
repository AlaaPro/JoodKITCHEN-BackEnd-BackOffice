<?php

namespace App\Controller\Api;

use App\Entity\Plat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Uploader\VichUploaderBundle;
use Vich\UploaderBundle\Storage\StorageInterface;

#[Route('/api/admin/plats')]
#[IsGranted('ROLE_ADMIN')]
class PlatImageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private StorageInterface $storage
    ) {
    }

    #[Route('/{id}/image', name: 'api_admin_plat_image_upload', methods: ['POST'])]
    public function uploadImage(Request $request, Plat $plat): JsonResponse
    {
        $imageFile = $request->files->get('image');

        if (!$imageFile) {
            return new JsonResponse(['error' => 'No image file provided.'], 400);
        }

        $plat->setImageFile($imageFile);

        $errors = $this->validator->validate($plat);
        if (count($errors) > 0) {
            return new JsonResponse(['errors' => (string) $errors], 400);
        }
        
        $this->em->persist($plat);
        $this->em->flush();

        // The vich uploader bundle will have moved the file, 
        // and the entity now contains the filename.
        // We can get the public path via the storage.
        $imageUrl = $this->storage->resolveUri($plat, 'imageFile');

        return new JsonResponse([
            'success' => true, 
            'message' => 'Image uploaded successfully.',
            'imageUrl' => $imageUrl
        ]);
    }
} 