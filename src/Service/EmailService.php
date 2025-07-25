<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class EmailService
{
    private MailerInterface $mailer;
    private Environment $twig;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private string $fromEmail;
    private string $fromName;

    public function __construct(
        MailerInterface $mailer,
        Environment $twig,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->params = $params;
        $this->logger = $logger;
        
        // Get email configuration from .env
        $this->fromEmail = $_ENV['MAILER_FROM_EMAIL'] ?? 'noreply@joodkitchen.com';
        $this->fromName = $_ENV['MAILER_FROM_NAME'] ?? 'JoodKitchen';
    }

    /**
     * Send a simple email with dynamic content
     */
    public function sendEmail(
        string $to,
        string $subject,
        string $templateName,
        array $data = [],
        ?string $toName = null
    ): bool {
        try {
            // Render the email template with dynamic data
            $htmlContent = $this->twig->render("emails/{$templateName}.html.twig", $data);
            
            // Create the email
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($to)
                ->subject($subject)
                ->html($htmlContent);

            // Send the email
            $this->mailer->send($email);
            
            $this->logger->info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
                'template' => $templateName
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw the exception so we can see what's wrong
            throw $e;
        }
    }

    /**
     * Send order confirmation email
     */
    public function sendOrderConfirmation(string $customerEmail, array $orderData, ?string $customerName = null): bool
    {
        return $this->sendEmail(
            $customerEmail,
            'Confirmation de votre commande JoodKitchen',
            'order_confirmation',
            ['order' => $orderData],
            $customerName
        );
    }

    /**
     * Send order status update email
     */
    public function sendOrderStatusUpdate(string $customerEmail, array $orderData, ?string $customerName = null): bool
    {
        return $this->sendEmail(
            $customerEmail,
            'Mise à jour de votre commande JoodKitchen',
            'order_status',
            ['order' => $orderData],
            $customerName
        );
    }

    /**
     * Send email verification email
     */
    public function sendEmailVerification(string $customerEmail, string $verificationCode, ?string $customerName = null): bool
    {
        return $this->sendEmail(
            $customerEmail,
            'Vérifiez votre email JoodKitchen',
            'email_verification',
            ['verification_code' => $verificationCode],
            $customerName
        );
    }

    /**
     * Send subscription confirmation email
     */
    public function sendSubscriptionConfirmation(string $customerEmail, array $subscriptionData, ?string $customerName = null): bool
    {
        return $this->sendEmail(
            $customerEmail,
            'Confirmation de votre abonnement JoodKitchen',
            'subscription_confirmation',
            ['subscription' => $subscriptionData],
            $customerName
        );
    }

    /**
     * Send weekly meal reminder email
     */
    public function sendWeeklyMealReminder(string $customerEmail, array $mealData, ?string $customerName = null): bool
    {
        return $this->sendEmail(
            $customerEmail,
            'Rappel: Sélectionnez vos repas de la semaine',
            'weekly_meal_reminder',
            ['meals' => $mealData],
            $customerName
        );
    }
} 