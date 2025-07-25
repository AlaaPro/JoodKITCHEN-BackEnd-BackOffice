<?php

namespace App\Controller;

use App\Service\EmailService;
use App\Service\EmailVerificationService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class TestController extends AbstractController
{
    #[Route('/test/email-verification', name: 'test_email_verification', methods: ['GET'])]
    public function testEmailVerification(): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'message' => 'Email verification endpoints ready',
            'endpoints' => [
                'send_verification' => '/api/auth/send-verification (POST)',
                'verify_email' => '/api/auth/verify-email (POST)',
                'resend_verification' => '/api/auth/resend-verification (POST)'
            ],
            'usage' => [
                'send_verification' => ['email' => 'user@example.com'],
                'verify_email' => ['email' => 'user@example.com', 'code' => '123456'],
                'resend_verification' => ['email' => 'user@example.com']
            ]
        ]);
    }

    #[Route('/test/debug-verification', name: 'test_debug_verification', methods: ['GET'])]
    public function debugEmailVerification(
        EmailVerificationService $emailVerificationService,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            // Find the test user
            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'valaa4@gmail.com']);
            
            if (!$user) {
                return new JsonResponse(['error' => 'User valaa4@gmail.com not found'], 404);
            }

            // Get user info before
            $beforeCode = $user->getEmailVerificationCode();
            $beforeExpires = $user->getEmailVerificationExpiresAt();
            $beforeVerified = $user->getEmailVerifiedAt();

            // Generate a test code manually to check the service
            $testCode = $emailVerificationService->generateVerificationCode();

            // Try to send verification email
            $result = $emailVerificationService->sendVerificationEmail($user);

            // Refresh user from database to get updated values
            $entityManager->refresh($user);
            
            $afterCode = $user->getEmailVerificationCode();
            $afterExpires = $user->getEmailVerificationExpiresAt();
            $afterVerified = $user->getEmailVerifiedAt();

            return new JsonResponse([
                'success' => true,
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'test_code_generated' => $testCode,
                'send_email_result' => $result,
                'before' => [
                    'code' => $beforeCode,
                    'expires' => $beforeExpires?->format('Y-m-d H:i:s'),
                    'verified' => $beforeVerified?->format('Y-m-d H:i:s')
                ],
                'after' => [
                    'code' => $afterCode,
                    'expires' => $afterExpires?->format('Y-m-d H:i:s'),
                    'verified' => $afterVerified?->format('Y-m-d H:i:s')
                ],
                'code_was_set' => $afterCode !== null,
                'code_changed' => $beforeCode !== $afterCode
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    #[Route('/test/email', name: 'test_email', methods: ['GET'])]
    public function testEmail(EmailService $emailService): JsonResponse
    {
        $results = [];
        $errors = [];
        $testEmail = 'alaa.zerroud@gmail.com';
        
        try {
            // Test email verification
            try {
                $verificationResult = $emailService->sendEmailVerification(
                    $testEmail,
                    '123456',
                    'Test User'
                );
                $results['email_verification'] = $verificationResult;
            } catch (\Exception $e) {
                $errors['email_verification'] = $e->getMessage();
                $results['email_verification'] = false;
            }
            
            // Test order confirmation
            $orderData = [
                'id' => 123,
                'numero' => 'CMD-123',
                'date_commande' => new \DateTime(),
                'client_name' => 'Test User',
                'type_livraison' => 'livraison',
                'adresse_livraison' => '123 Test Street, Test City',
                'total' => 25.50,
                'total_avant_reduction' => 30.00,
                'statut' => 'en_attente',
                'commentaire' => 'Test order comment',
                'articles' => [
                    [
                        'nom' => 'Tajine Poulet',
                        'type' => 'menu',
                        'cuisine_type' => 'marocain',
                        'description' => 'Tajine traditionnel avec légumes',
                        'quantite' => 1,
                        'prix_unitaire' => 15.50,
                        'commentaire' => 'Bien épicé svp'
                    ],
                    [
                        'nom' => 'Jus d\'Orange',
                        'type' => 'boisson',
                        'quantite' => 2,
                        'prix_unitaire' => 5.00
                    ]
                ]
            ];
            
            try {
                $orderResult = $emailService->sendOrderConfirmation(
                    $testEmail,
                    $orderData,
                    'Test User'
                );
                $results['order_confirmation'] = $orderResult;
            } catch (\Exception $e) {
                $errors['order_confirmation'] = $e->getMessage();
                $results['order_confirmation'] = false;
            }
            
            return $this->json([
                'success' => true,
                'message' => 'Email service test completed',
                'smtp_config' => [
                    'from_email' => $_ENV['MAILER_FROM_EMAIL'] ?? 'Not set',
                    'from_name' => $_ENV['MAILER_FROM_NAME'] ?? 'Not set',
                    'dsn_configured' => isset($_ENV['MAILER_DSN']) ? 'Yes' : 'No'
                ],
                'test_email' => $testEmail,
                'results' => $results,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
                'smtp_config' => [
                    'from_email' => $_ENV['MAILER_FROM_EMAIL'] ?? 'Not set',
                    'from_name' => $_ENV['MAILER_FROM_NAME'] ?? 'Not set',
                    'dsn_configured' => isset($_ENV['MAILER_DSN']) ? 'Yes' : 'No'
                ]
            ], 500);
        }
    }

    #[Route('/test/smtp-debug', name: 'test_smtp_debug', methods: ['GET'])]
    public function testSmtpConnection(MailerInterface $mailer): JsonResponse
    {
        try {
            $email = (new Email())
                ->from($_ENV['MAILER_FROM_EMAIL'] ?? 'test@example.com')
                ->to('alaa.zerroud@gmail.com')
                ->subject('🧪 SMTP Test - JoodKitchen')
                ->html('<h2>SMTP Test Email</h2><p>If you receive this, SMTP is working correctly!</p><p><strong>From:</strong> JoodKitchen Email Service</p><p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>');

            $mailer->send($email);

            return new JsonResponse([
                'success' => true,
                'message' => 'Email sent successfully via SMTP',
                'config' => [
                    'from_email' => $_ENV['MAILER_FROM_EMAIL'] ?? 'Not set',
                    'from_name' => $_ENV['MAILER_FROM_NAME'] ?? 'Not set',
                    'dsn_configured' => isset($_ENV['MAILER_DSN']) ? 'Yes' : 'No'
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'smtp_config' => [
                    'from_email' => $_ENV['MAILER_FROM_EMAIL'] ?? 'Not set',
                    'from_name' => $_ENV['MAILER_FROM_NAME'] ?? 'Not set',
                    'dsn_configured' => isset($_ENV['MAILER_DSN']) ? 'Yes' : 'No'
                ]
            ], 500);
        }
    }
} 