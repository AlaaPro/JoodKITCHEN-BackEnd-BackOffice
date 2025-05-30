<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\OpenApi;
use ApiPlatform\OpenApi\Model;

class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated
    ) {}

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Add Bearer Authentication
        $securitySchemes = $openApi->getComponents()->getSecuritySchemes() ?: [];
        $securitySchemes['bearerAuth'] = new Model\SecurityScheme(
            type: 'http',
            scheme: 'bearer',
            bearerFormat: 'token',
            description: 'Entrez votre token d\'authentification obtenu via POST /api/auth/login'
        );

        // Add global security requirement
        $openApi = $openApi->withSecurity([['bearerAuth' => []]]);

        // Update components with security schemes
        $components = new Model\Components(
            schemas: $openApi->getComponents()->getSchemas(),
            responses: $openApi->getComponents()->getResponses(),
            parameters: $openApi->getComponents()->getParameters(),
            examples: $openApi->getComponents()->getExamples(),
            requestBodies: $openApi->getComponents()->getRequestBodies(),
            headers: $openApi->getComponents()->getHeaders(),
            securitySchemes: $securitySchemes,
            links: $openApi->getComponents()->getLinks(),
            callbacks: $openApi->getComponents()->getCallbacks()
        );

        return $openApi->withComponents($components);
    }
} 