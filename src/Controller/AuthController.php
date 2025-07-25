<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\ClientProfile;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private JWTTokenManagerInterface $jwtManager,
        private EmailVerificationService $emailVerificationService
    ) {}

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        $requiredFields = ['email', 'password', 'nom', 'prenom', 'telephone'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return new JsonResponse(['error' => "Field '$field' is required"], Response::HTTP_BAD_REQUEST);
            }
        }

        // Check if user already exists
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return new JsonResponse(['error' => 'User already exists'], Response::HTTP_CONFLICT);
        }

        // Create new user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setNom($data['nom']);
        $user->setPrenom($data['prenom']);
        $user->setTelephone($data['telephone']);
        
        // Set optional fields
        if (isset($data['genre'])) {
            $user->setGenre($data['genre']);
        }
        if (isset($data['ville'])) {
            $user->setVille($data['ville']);
        }
        if (isset($data['adresse'])) {
            $user->setAdresse($data['adresse']);
        }
        if (isset($data['dateNaissance'])) {
            $user->setDateNaissance(new \DateTime($data['dateNaissance']));
        }

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Set default role and inactive status (requires email verification)
        $role = $data['role'] ?? 'ROLE_CLIENT';
        $user->setRoles([$role]);
        $user->setIsActive(false); // Account inactive until email verification

        // Validate user
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Save user
        $this->entityManager->persist($user);

        // Create profile based on role
        if ($role === 'ROLE_CLIENT') {
            $clientProfile = new ClientProfile();
            $clientProfile->setUser($user);
            if (isset($data['adresseLivraison'])) {
                $clientProfile->setAdresseLivraison($data['adresseLivraison']);
            }
            $this->entityManager->persist($clientProfile);
        }

        $this->entityManager->flush();

        // Account created but requires email verification - no JWT token yet
        return new JsonResponse([
            'success' => true,
            'message' => 'Compte créé avec succès. Veuillez vérifier votre email pour activer votre compte.',
            'requires_verification' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'is_active' => false,
                'email_verified' => false,
                'roles' => $user->getRoles()
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $data['email']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->getIsActive()) {
            // Check if it's because email is not verified
            if (!$user->isEmailVerified()) {
                return new JsonResponse([
                    'error' => 'Compte non activé. Veuillez vérifier votre email d\'abord.',
                    'requires_verification' => true,
                    'email' => $user->getEmail()
                ], Response::HTTP_FORBIDDEN);
            } else {
                return new JsonResponse(['error' => 'Compte désactivé'], Response::HTTP_FORBIDDEN);
            }
        }

        // Update last connection
        $user->setLastConnexion(new \DateTime());
        $this->entityManager->flush();

        // Generate JWT token
        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'is_active' => $user->getIsActive(),
                'email_verified' => $user->isEmailVerified(),
                'roles' => $user->getRoles(),
                'lastConnexion' => $user->getLastConnexion()?->format('Y-m-d H:i:s')
            ]
        ]);
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'telephone' => $user->getTelephone(),
                'genre' => $user->getGenre(),
                'ville' => $user->getVille(),
                'adresse' => $user->getAdresse(),
                'roles' => $user->getRoles(),
                'isActive' => $user->getIsActive(),
                'createdAt' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
                'lastConnexion' => $user->getLastConnexion()?->format('Y-m-d H:i:s')
            ]
        ]);
    }

    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        // With JWT, logout is handled client-side by removing the token
        // The server doesn't need to do anything since JWT is stateless
        
        return new JsonResponse([
            'message' => 'Logout successful. Please remove the token from your client.'
        ]);
    }

    #[Route('/refresh', name: 'api_refresh_token', methods: ['POST'])]
    public function refreshToken(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Generate new JWT token
        $token = $this->jwtManager->create($user);

        return new JsonResponse([
            'message' => 'Token refreshed successfully',
            'token' => $token
        ]);
    }

    #[Route('/send-verification', name: 'api_send_verification', methods: ['POST'])]
    public function sendVerificationEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'])) {
            return new JsonResponse(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        // Find user by email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if email is already verified
        if ($user->isEmailVerified()) {
            return new JsonResponse(['error' => 'Email is already verified'], Response::HTTP_BAD_REQUEST);
        }

        // Check rate limiting
        if (!$this->emailVerificationService->canRequestNewCode($user)) {
            $timeRemaining = $this->emailVerificationService->getTimeUntilNewCodeAllowed($user);
            return new JsonResponse([
                'error' => 'Too many requests. Please wait before requesting a new code.',
                'retry_after_seconds' => $timeRemaining
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Send verification email
        $success = $this->emailVerificationService->sendVerificationEmail($user);

        if ($success) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Code de vérification envoyé à votre email'
            ]);
        } else {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/verify-email', name: 'api_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email']) || !isset($data['code'])) {
            return new JsonResponse(['error' => 'Email and verification code are required'], Response::HTTP_BAD_REQUEST);
        }

        // Find user by email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Verify the code
        $result = $this->emailVerificationService->verifyEmailCode($user, $data['code']);

        if ($result['success']) {
            // Generate JWT token for the verified user
            $token = $this->jwtManager->create($user);

            return new JsonResponse([
                'success' => true,
                'message' => $result['message'],
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'nom' => $user->getNom(),
                    'prenom' => $user->getPrenom(),
                    'email_verified' => true,
                    'roles' => $user->getRoles()
                ]
            ]);
        } else {
            return new JsonResponse([
                'error' => $result['message']
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'])) {
            return new JsonResponse(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        // Find user by email
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if email is already verified
        if ($user->isEmailVerified()) {
            return new JsonResponse(['error' => 'Email is already verified'], Response::HTTP_BAD_REQUEST);
        }

        // Check rate limiting
        if (!$this->emailVerificationService->canRequestNewCode($user)) {
            $timeRemaining = $this->emailVerificationService->getTimeUntilNewCodeAllowed($user);
            return new JsonResponse([
                'error' => 'Too many requests. Please wait before requesting a new code.',
                'retry_after_seconds' => $timeRemaining
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Send verification email
        $success = $this->emailVerificationService->sendVerificationEmail($user);

        if ($success) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Nouveau code de vérification envoyé à votre email'
            ]);
        } else {
            return new JsonResponse([
                'error' => 'Erreur lors de l\'envoi de l\'email. Veuillez réessayer.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 