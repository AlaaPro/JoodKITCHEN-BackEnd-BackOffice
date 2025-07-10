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
        // Use the centralized getJavaScriptConfig method from OrderStatus enum
        return new JsonResponse(OrderStatus::getJavaScriptConfig());
    }
} 