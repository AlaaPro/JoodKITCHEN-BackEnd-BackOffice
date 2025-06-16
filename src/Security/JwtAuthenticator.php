<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
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

/**
 * Custom JWT Authenticator that bridges JavaScript/localStorage JWT auth with Symfony sessions
 * 
 * This authenticator:
 * 1. Extracts JWT tokens from Authorization header, cookies, or URL parameters
 * 2. Validates the token using Lexik JWT bundle
 * 3. Loads the user and creates a Symfony authentication session
 * 4. Enables is_granted() and app.user to work with JWT authentication
 */
class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository,
        private TokenExtractorInterface $tokenExtractor
    ) {}

    public function supports(Request $request): ?bool
    {
        // Support JWT authentication for admin routes and API routes
        $path = $request->getPathInfo();
        
        // EXCLUDE login and auth routes from JWT authentication requirement
        $excludedPaths = [
            '/admin/login',
            '/admin/auth',
            '/api/auth/login',
            '/api/auth/register',
            '/api/docs'
        ];
        
        foreach ($excludedPaths as $excludedPath) {
            if (str_starts_with($path, $excludedPath)) {
                return false;
            }
        }
        
        // Only support JWT auth if we're on admin/api routes AND a token is present
        $isAdminOrApi = str_starts_with($path, '/admin') || str_starts_with($path, '/api');
        $hasToken = $this->extractToken($request) !== null;
        
        return $isAdminOrApi && $hasToken;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            throw new CustomUserMessageAuthenticationException('JWT Token not found');
        }

        try {
            // Decode the JWT token
            $payload = $this->jwtManager->parse($token);
            
            if (!isset($payload['email'])) {
                throw new CustomUserMessageAuthenticationException('Invalid JWT token payload');
            }

            $email = $payload['email'];
            
            // Create passport with user badge
            return new SelfValidatingPassport(
                new UserBadge($email, function($userIdentifier) {
                    $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
                    
                    if (!$user) {
                        throw new CustomUserMessageAuthenticationException('User not found');
                    }
                    
                    return $user;
                })
            );
            
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT token: ' . $e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $path = $request->getPathInfo();
        
        // For admin routes, redirect to login
        if (str_starts_with($path, '/admin') && !str_starts_with($path, '/admin/login')) {
            // Store the intended URL
            $request->getSession()->set('_target_path', $request->getUri());
            
            // For AJAX requests, return JSON
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'error' => 'Authentication required',
                    'redirect' => '/admin/login'
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // For regular requests, redirect to login
            return new Response('', Response::HTTP_FOUND, [
                'Location' => '/admin/login'
            ]);
        }
        
        // For API routes, return JSON error
        if (str_starts_with($path, '/api')) {
            return new JsonResponse([
                'error' => 'Authentication required',
                'message' => $exception->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        return new JsonResponse([
            'error' => 'Authentication failed',
            'message' => $exception->getMessage()
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Extract JWT token from multiple sources
     */
    private function extractToken(Request $request): ?string
    {
        // 1. Try Authorization header (Bearer token)
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        
        // 2. Try cookies (for browser-based requests)
        $cookieToken = $request->cookies->get('admin_token');
        if ($cookieToken) {
            return $cookieToken;
        }
        
        // 3. Try query parameter (for special cases)
        $queryToken = $request->query->get('token');
        if ($queryToken) {
            return $queryToken;
        }
        
        // 4. Try to extract from any custom header
        $customToken = $request->headers->get('X-Auth-Token');
        if ($customToken) {
            return $customToken;
        }
        
        return null;
    }
} 