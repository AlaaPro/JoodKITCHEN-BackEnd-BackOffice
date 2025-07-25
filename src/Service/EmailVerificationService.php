<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class EmailVerificationService
{
    private EntityManagerInterface $entityManager;
    private EmailService $emailService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->logger = $logger;
    }

    /**
     * Generate a 6-digit verification code
     */
    public function generateVerificationCode(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Send verification email and save code to user
     */
    public function sendVerificationEmail(User $user): bool
    {
        try {
            // Generate verification code
            $verificationCode = $this->generateVerificationCode();
            
            // Set verification code and expiration (15 minutes)
            $user->setEmailVerificationCode($verificationCode);
            $user->setEmailVerificationExpiresAt(new \DateTime('+15 minutes'));
            
            // Save to database
            $this->entityManager->persist($user);
            $this->entityManager->flush();
            
            // Send email
            $emailSent = $this->emailService->sendEmailVerification(
                $user->getEmail(),
                $verificationCode,
                $user->getPrenom() . ' ' . $user->getNom()
            );
            
            if ($emailSent) {
                $this->logger->info('Verification email sent successfully', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);
                return true;
            } else {
                $this->logger->error('Failed to send verification email', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error sending verification email', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Verify the email verification code
     */
    public function verifyEmailCode(User $user, string $code): array
    {
        try {
            // Check if user has a verification code
            if (!$user->getEmailVerificationCode()) {
                return [
                    'success' => false,
                    'message' => 'Aucun code de vérification trouvé. Demandez un nouveau code.'
                ];
            }

            // Check if code has expired
            if ($user->isEmailVerificationExpired()) {
                return [
                    'success' => false,
                    'message' => 'Le code de vérification a expiré. Demandez un nouveau code.'
                ];
            }

            // Check if code matches
            if ($user->getEmailVerificationCode() !== $code) {
                return [
                    'success' => false,
                    'message' => 'Code de vérification incorrect.'
                ];
            }

            // Verification successful - mark email as verified and activate account
            $user->setEmailVerifiedAt(new \DateTime());
            $user->setEmailVerificationCode(null);
            $user->setEmailVerificationExpiresAt(null);
            $user->setIsActive(true); // ✅ Activate account after email verification

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->logger->info('Email verified successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return [
                'success' => true,
                'message' => 'Email vérifié avec succès!'
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error verifying email code', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification. Veuillez réessayer.'
            ];
        }
    }

    /**
     * Check if user can request a new verification code (rate limiting)
     */
    public function canRequestNewCode(User $user): bool
    {
        // Allow new code if no code exists or if current code is older than 1 minute
        if (!$user->getEmailVerificationExpiresAt()) {
            return true;
        }

        $now = new \DateTime();
        $expiresAt = $user->getEmailVerificationExpiresAt();
        
        // Code was created 15 minutes before expiration, so calculate when it was created
        $codeCreatedTimestamp = $expiresAt->getTimestamp() - (15 * 60); // 15 minutes ago
        $timeDiff = $now->getTimestamp() - $codeCreatedTimestamp;
        
        // Allow new code if more than 60 seconds have passed
        return $timeDiff >= 60;
    }

    /**
     * Get time remaining before user can request new code
     */
    public function getTimeUntilNewCodeAllowed(User $user): int
    {
        if (!$user->getEmailVerificationExpiresAt()) {
            return 0;
        }

        $now = new \DateTime();
        $expiresAt = $user->getEmailVerificationExpiresAt();
        
        // Code was created 15 minutes before expiration
        $codeCreatedTimestamp = $expiresAt->getTimestamp() - (15 * 60); // 15 minutes ago
        $timeDiff = $now->getTimestamp() - $codeCreatedTimestamp;
        $remainingTime = 60 - $timeDiff;

        return max(0, $remainingTime);
    }
} 