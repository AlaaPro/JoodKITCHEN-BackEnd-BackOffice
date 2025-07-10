<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'en_attente';
    case CONFIRMED = 'confirme';
    case PREPARING = 'en_preparation';
    case READY = 'pret';
    case DELIVERING = 'en_livraison';
    case DELIVERED = 'livre';
    case CANCELLED = 'annule';

    /**
     * Get human-readable label in French
     */
    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmé',
            self::PREPARING => 'En préparation',
            self::READY => 'Prêt',
            self::DELIVERING => 'En livraison',
            self::DELIVERED => 'Livré',
            self::CANCELLED => 'Annulé'
        };
    }

    /**
     * Get badge/UI class for styling
     */
    public function getBadgeClass(): string
    {
        return match($this) {
            self::PENDING => 'jood-warning-bg',
            self::CONFIRMED => 'bg-info',
            self::PREPARING => 'bg-primary',
            self::READY => 'jood-primary-bg',
            self::DELIVERING => 'bg-warning',
            self::DELIVERED => 'jood-success-bg',
            self::CANCELLED => 'bg-danger'
        };
    }

    /**
     * Get icon class for the status
     */
    public function getIconClass(): string
    {
        return match($this) {
            self::PENDING => 'fas fa-clock',
            self::CONFIRMED => 'fas fa-check',
            self::PREPARING => 'fas fa-fire',
            self::READY => 'fas fa-utensils',
            self::DELIVERING => 'fas fa-truck',
            self::DELIVERED => 'fas fa-check-circle',
            self::CANCELLED => 'fas fa-times-circle'
        };
    }

    /**
     * Get notification message for status change
     */
    public function getNotificationMessage(): string
    {
        return match($this) {
            self::PREPARING => '🍳 Votre commande est en préparation',
            self::READY => '✅ Votre commande est prête',
            self::DELIVERING => '🚚 Votre commande est en cours de livraison',
            self::DELIVERED => '🎉 Votre commande a été livrée',
            self::CANCELLED => '❌ Votre commande a été annulée',
            default => 'Statut de commande mis à jour'
        };
    }

    /**
     * Get notification type for status change
     */
    public function getNotificationType(): string
    {
        return match($this) {
            self::CANCELLED => 'warning',
            self::DELIVERED => 'success',
            default => 'info'
        };
    }

    /**
     * Get estimated delivery time in minutes
     */
    public function getEstimatedDeliveryMinutes(): ?int
    {
        return match($this) {
            self::PENDING => 30,
            self::PREPARING => 20,
            self::READY => 10,
            self::DELIVERING => 15,
            default => null
        };
    }

    /**
     * Check if this status can transition to the given status
     */
    public function canTransitionTo(self $newStatus): bool
    {
        // Final states cannot transition
        if ($this === self::DELIVERED || $this === self::CANCELLED) {
            return false;
        }

        return match($this) {
            // Kitchen workflow: PENDING orders can be confirmed, started directly, or cancelled
            self::PENDING => in_array($newStatus, [self::CONFIRMED, self::PREPARING, self::CANCELLED]),
            // Confirmed orders can be prepared or cancelled
            self::CONFIRMED => in_array($newStatus, [self::PREPARING, self::CANCELLED]),
            // Preparing orders can be completed or cancelled
            self::PREPARING => in_array($newStatus, [self::READY, self::CANCELLED]),
            // Ready orders can be dispatched, delivered directly, or cancelled
            self::READY => in_array($newStatus, [self::DELIVERING, self::DELIVERED, self::CANCELLED]),
            // Delivering orders can only be delivered or cancelled
            self::DELIVERING => in_array($newStatus, [self::DELIVERED, self::CANCELLED]),
            default => false
        };
    }

    /**
     * Get all available statuses
     */
    public static function getAll(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get all status choices for forms/validation
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }

    /**
     * Get complete status configuration for JavaScript
     */
    public static function getJavaScriptConfig(): array
    {
        $config = [];
        foreach (self::cases() as $case) {
            $config[$case->name] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
                'badge_class' => $case->getBadgeClass(),
                'icon_class' => $case->getIconClass(),
                'notification' => [
                    'message' => $case->getNotificationMessage(),
                    'type' => $case->getNotificationType()
                ],
                'estimated_minutes' => $case->getEstimatedDeliveryMinutes(),
                'next_possible_statuses' => array_map(fn($status) => $status->value, $case->getNextPossibleStatuses())
            ];
        }
        return $config;
    }

    /**
     * Get HTML for status badge with icon
     */
    public function getBadgeHtml(): string
    {
        return sprintf(
            '<span class="badge %s"><i class="%s me-1"></i>%s</span>',
            $this->getBadgeClass(),
            $this->getIconClass(),
            $this->getLabel()
        );
    }

    /**
     * Check if this is a final status (no more changes allowed)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::CANCELLED]);
    }

    /**
     * Get next possible statuses
     */
    public function getNextPossibleStatuses(): array
    {
        if ($this->isFinal()) {
            return [];
        }

        return match($this) {
            self::PENDING => [self::CONFIRMED, self::PREPARING, self::CANCELLED],
            self::CONFIRMED => [self::PREPARING, self::CANCELLED],
            self::PREPARING => [self::READY, self::CANCELLED],
            self::READY => [self::DELIVERING, self::DELIVERED, self::CANCELLED],
            self::DELIVERING => [self::DELIVERED, self::CANCELLED],
            default => []
        };
    }
} 