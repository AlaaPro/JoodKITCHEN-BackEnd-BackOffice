<?php

namespace App\Controller\Api;

use App\Enum\OrderStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class OrderStatusController extends AbstractController
{
    #[Route('/api/order-status-config', name: 'api_order_status_config', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        $config = [];
        foreach (OrderStatus::cases() as $status) {
            $config[$status->name] = [
                'value' => $status->value,
                'label' => $status->getLabel(),
                'badge_class' => $status->getBadgeClass()
            ];
        }
        return new JsonResponse($config);
    }
} 