<?php

namespace App\Service;

use App\Enum\OrderStatus;

class OrderStatusConfigService
{
    private ?array $config = null;

    public function __construct(
        private string $projectDir
    ) {}

    private function getConfig(): array
    {
        if ($this->config === null) {
            $configFile = $this->projectDir . '/config/app/order_status.json';
            $jsonContent = file_get_contents($configFile);
            $this->config = json_decode($jsonContent, true);
        }
        return $this->config;
    }

    public function getStatusConfig(OrderStatus $status): array
    {
        $config = $this->getConfig();
        foreach ($config['statuses'] as $key => $statusConfig) {
            if ($statusConfig['value'] === $status->value) {
                return $statusConfig;
            }
        }
        throw new \RuntimeException("Configuration not found for status: {$status->value}");
    }

    public function getLabel(OrderStatus $status): string
    {
        return $this->getStatusConfig($status)['label'];
    }

    public function getBadgeClass(OrderStatus $status): string
    {
        return $this->getStatusConfig($status)['badge_class'];
    }

    public function getIconClass(OrderStatus $status): string
    {
        return $this->getStatusConfig($status)['icon_class'];
    }

    public function getNotificationMessage(OrderStatus $status): string
    {
        return $this->getStatusConfig($status)['notification']['message'];
    }

    public function getNotificationType(OrderStatus $status): string
    {
        return $this->getStatusConfig($status)['notification']['type'];
    }

    public function getEstimatedDeliveryMinutes(OrderStatus $status): ?int
    {
        return $this->getStatusConfig($status)['estimated_minutes'] ?? null;
    }

    public function canTransitionTo(OrderStatus $currentStatus, OrderStatus $newStatus): bool
    {
        $config = $this->getConfig();
        $allowedTransitions = $config['transitions'][$currentStatus->value] ?? [];
        return in_array($newStatus->value, $allowedTransitions);
    }

    public function getJsonConfig(): string
    {
        return json_encode($this->getConfig());
    }
} 