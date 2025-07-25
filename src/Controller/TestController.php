<?php

namespace App\Controller;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TestController extends AbstractController
{
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
} 