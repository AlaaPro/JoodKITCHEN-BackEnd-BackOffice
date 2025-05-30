<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function supports(Request $request): ?bool
    {
        // Check if this is an API request with an Authorization header
        return $request->headers->has('Authorization') && 
               str_starts_with($request->getPathInfo(), '/api/') &&
               !str_starts_with($request->getPathInfo(), '/api/auth') &&
               !str_starts_with($request->getPathInfo(), '/api/docs');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        // Support both "Bearer <token>" and "Token <token>" formats
        if (preg_match('/^(Bearer|Token)\s+(.+)$/', $authHeader, $matches)) {
            $token = $matches[2];
        } else {
            throw new CustomUserMessageAuthenticationException('Invalid token format');
        }

        // For now, we'll use a simple token validation
        // In a real app, you'd validate JWT tokens or API keys from database
        $user = $this->validateToken($token);
        
        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Invalid token');
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getEmail(), function() use ($user) {
                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function validateToken(string $token): ?object
    {
        // Simple token validation for demo
        // Format: "user_id:email:timestamp:hash"
        $parts = explode(':', $token);
        
        if (count($parts) !== 4) {
            return null;
        }

        [$userId, $email, $timestamp, $hash] = $parts;
        
        // Check if token is not expired (24 hours)
        if ((time() - (int)$timestamp) > 86400) {
            return null;
        }

        // Validate hash (simple validation for demo)
        $expectedHash = hash('sha256', $userId . $email . $timestamp . 'secret_key');
        if (!hash_equals($expectedHash, $hash)) {
            return null;
        }

        // Find user
        return $this->userRepository->find($userId);
    }
} 