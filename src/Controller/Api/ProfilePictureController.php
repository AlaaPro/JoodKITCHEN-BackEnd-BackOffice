<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

#[Route('/api/profile-picture', name: 'api_profile_picture_')]
class ProfilePictureController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ValidatorInterface $validator,
        private StorageInterface $storage
    ) {}

    /**
     * Upload or update profile picture for current user
     */
    #[Route('/upload', name: 'upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            $uploadedFile = $request->files->get('profile_picture');
            
            if (!$uploadedFile) {
                return $this->json(['error' => 'Aucun fichier uploadé'], Response::HTTP_BAD_REQUEST);
            }

            // Set the file in the user entity
            $user->setPhotoProfilFile($uploadedFile);

            // Validate the file
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Validation échouée', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // Save the user (VichUploader will handle the file upload)
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Photo de profil uploadée avec succès',
                'photo_url' => $user->getPhotoProfilUrl(),
                'filename' => $user->getPhotoProfil()
            ], Response::HTTP_OK);

        } catch (FileException $e) {
            return $this->json(['error' => 'Erreur lors de l\'upload du fichier'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload profile picture for specific user (admin only)
     */
    #[Route('/upload/{userId}', name: 'upload_for_user', methods: ['POST'])]
    #[IsGranted('manage_users')]
    public function uploadProfilePictureForUser(int $userId, Request $request): JsonResponse
    {
        try {
            $user = $this->userRepository->find($userId);
            
            if (!$user) {
                return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
            }

            $uploadedFile = $request->files->get('profile_picture');
            
            if (!$uploadedFile) {
                return $this->json(['error' => 'Aucun fichier uploadé'], Response::HTTP_BAD_REQUEST);
            }

            // Set the file in the user entity
            $user->setPhotoProfilFile($uploadedFile);

            // Validate the file
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => 'Validation échouée', 'details' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            // Save the user (VichUploader will handle the file upload)
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Photo de profil uploadée avec succès',
                'photo_url' => $user->getPhotoProfilUrl(),
                'filename' => $user->getPhotoProfil(),
                'user' => [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'photo_url' => $user->getPhotoProfilUrl()
                ]
            ], Response::HTTP_OK);

        } catch (FileException $e) {
            return $this->json(['error' => 'Erreur lors de l\'upload du fichier'], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur serveur'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete profile picture for current user
     */
    #[Route('/delete', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteProfilePicture(): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            
            if (!$user) {
                return $this->json(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
            }

            if (!$user->getPhotoProfil()) {
                return $this->json(['error' => 'Aucune photo de profil à supprimer'], Response::HTTP_BAD_REQUEST);
            }

            // Remove the file
            $user->setPhotoProfilFile(null);
            $user->setPhotoProfil(null);

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Photo de profil supprimée avec succès'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete profile picture for specific user (admin only)
     */
    #[Route('/delete/{userId}', name: 'delete_for_user', methods: ['DELETE'])]
    #[IsGranted('manage_users')]
    public function deleteProfilePictureForUser(int $userId): JsonResponse
    {
        try {
            $user = $this->userRepository->find($userId);
            
            if (!$user) {
                return $this->json(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
            }

            if (!$user->getPhotoProfil()) {
                return $this->json(['error' => 'Aucune photo de profil à supprimer'], Response::HTTP_BAD_REQUEST);
            }

            // Remove the file
            $user->setPhotoProfilFile(null);
            $user->setPhotoProfil(null);

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Photo de profil supprimée avec succès',
                'user' => [
                    'id' => $user->getId(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'photo_url' => null
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la suppression'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get profile picture info for current user
     */
    #[Route('/info', name: 'info', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProfilePictureInfo(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'has_photo' => (bool) $user->getPhotoProfil(),
            'photo_url' => $user->getPhotoProfilUrl(),
            'filename' => $user->getPhotoProfil(),
            'user' => [
                'id' => $user->getId(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom()
            ]
        ]);
    }
} 