<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    #[Route('/test-browser-auth', name: 'test_browser_auth')]
    public function testBrowserAuth(): Response
    {
        $htmlContent = file_get_contents(__DIR__ . '/../../test_browser_auth.html');
        
        return new Response($htmlContent, 200, [
            'Content-Type' => 'text/html'
        ]);
    }

    #[Route('/token-generator', name: 'token_generator')]
    public function tokenGenerator(Request $request): Response
    {
        $baseUrl = $request->getSchemeAndHttpHost();
        $userType = $request->query->get('user', 'all');

        // Available test users
        $users = [
            'admin' => [
                'email' => 'admin@joodkitchen.com',
                'password' => 'admin123',
                'name' => 'Super Admin',
                'description' => 'Full access to all endpoints',
                'color' => '#dc2626'
            ],
            'kitchen' => [
                'email' => 'chef@joodkitchen.com',
                'password' => 'chef123',
                'name' => 'Kitchen Staff',
                'description' => 'Access to dishes, menus, and orders',
                'color' => '#ea580c'
            ],
            'client' => [
                'email' => 'client@joodkitchen.com',
                'password' => 'client123',
                'name' => 'Client',
                'description' => 'Access to profile, orders, and subscriptions',
                'color' => '#059669'
            ]
        ];

        $html = $this->generateTokenPage($users, $userType, $baseUrl);

        return new Response($html, 200, [
            'Content-Type' => 'text/html'
        ]);
    }

    private function getToken($email, $password, $baseUrl): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $baseUrl . '/api/auth/login',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'email' => $email,
                'password' => $password
            ])
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }

    private function generateTokenPage($users, $userType, $baseUrl): string
    {
        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔑 JoodKitchen Token Generator</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 20px; background: #f8fafc; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 2.5rem; color: #1f2937; margin: 0; }
        .subtitle { color: #6b7280; font-size: 1.1rem; margin: 10px 0; }
        .nav { display: flex; gap: 10px; justify-content: center; margin: 20px 0; flex-wrap: wrap; }
        .nav-btn { padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 8px; font-weight: 500; transition: all 0.2s; }
        .nav-btn:hover { background: #2563eb; transform: translateY(-1px); }
        .nav-btn.active { background: #1d4ed8; }
        .user-card { background: white; border-radius: 12px; padding: 24px; margin: 20px 0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-left: 4px solid; }
        .user-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .user-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.2rem; }
        .user-info h3 { margin: 0; color: #1f2937; font-size: 1.3rem; }
        .user-info p { margin: 4px 0 0 0; color: #6b7280; }
        .token-section { margin-top: 20px; }
        .token-box { background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 8px; padding: 16px; margin: 12px 0; }
        .token-text { font-family: "Monaco", "Menlo", monospace; font-size: 0.9rem; word-break: break-all; color: #374151; background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; }
        .copy-btn { background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.9rem; margin-top: 8px; }
        .copy-btn:hover { background: #059669; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin: 16px 0; }
        .info-item { background: #f3f4f6; padding: 12px; border-radius: 8px; }
        .info-label { font-weight: 600; color: #374151; font-size: 0.9rem; }
        .info-value { color: #6b7280; font-size: 0.9rem; }
        .api-docs-link { display: inline-block; background: #8b5cf6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; margin: 16px 0; }
        .api-docs-link:hover { background: #7c3aed; }
        .instructions { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .instructions h4 { margin: 0 0 12px 0; color: #1e40af; }
        .instructions ol { margin: 8px 0; padding-left: 20px; }
        .instructions li { margin: 4px 0; color: #374151; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 16px; border-radius: 8px; margin: 16px 0; }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 16px; border-radius: 8px; margin: 16px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">🔑 JoodKitchen Token Generator</h1>
            <p class="subtitle">Generate authentication tokens for API Platform docs testing</p>
        </div>

        <div class="nav">
            <a href="?user=all" class="nav-btn ' . ($userType === 'all' ? 'active' : '') . '">All Users</a>
            <a href="?user=admin" class="nav-btn ' . ($userType === 'admin' ? 'active' : '') . '">Admin Only</a>
            <a href="?user=kitchen" class="nav-btn ' . ($userType === 'kitchen' ? 'active' : '') . '">Kitchen Only</a>
            <a href="?user=client" class="nav-btn ' . ($userType === 'client' ? 'active' : '') . '">Client Only</a>
        </div>';

        if ($userType === 'all') {
            foreach ($users as $key => $user) {
                $html .= $this->generateUserCard($user, $key, $baseUrl);
            }
        } elseif (isset($users[$userType])) {
            $html .= $this->generateUserCard($users[$userType], $userType, $baseUrl);
        }

        $html .= '
        <div class="instructions">
            <h4>📖 How to Use with API Platform Docs:</h4>
            <ol>
                <li>Copy any token above using the "Copy Token" button</li>
                <li>Go to <a href="' . $baseUrl . '/api/docs/" target="_blank">' . $baseUrl . '/api/docs/</a></li>
                <li>Click the "Authorize" button (🔒)</li>
                <li>Paste the token in the "bearerAuth" field</li>
                <li>Click "Authorize" and test the endpoints!</li>
            </ol>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $baseUrl . '/api/docs/" target="_blank" class="api-docs-link">
                🚀 Open API Platform Docs
            </a>
        </div>
    </div>

    <script>
        function copyToken(token) {
            navigator.clipboard.writeText(token).then(function() {
                // Show success feedback
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = "✅ Copied!";
                btn.style.background = "#059669";
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.style.background = "#10b981";
                }, 2000);
            }).catch(function(err) {
                console.error("Could not copy text: ", err);
                alert("Could not copy token. Please select and copy manually.");
            });
        }
    </script>
</body>
</html>';

        return $html;
    }

    private function generateUserCard($user, $key, $baseUrl): string
    {
        $result = $this->getToken($user['email'], $user['password'], $baseUrl);
        
        if ($result) {
            $token = $result['token'];
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);
            
            return '
            <div class="user-card" style="border-left-color: ' . $user['color'] . ';">
                <div class="user-header">
                    <div class="user-icon" style="background: ' . $user['color'] . ';">
                        ' . strtoupper(substr($user['name'], 0, 1)) . '
                    </div>
                    <div class="user-info">
                        <h3>' . $user['name'] . '</h3>
                        <p>' . $user['description'] . '</p>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">📧 Email</div>
                        <div class="info-value">' . $user['email'] . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">👤 User</div>
                        <div class="info-value">' . $result['user']['nom'] . ' ' . $result['user']['prenom'] . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">🔐 Roles</div>
                        <div class="info-value">' . implode(', ', $result['user']['roles']) . '</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">⏰ Expires</div>
                        <div class="info-value">' . $expiresAt . '</div>
                    </div>
                </div>
                
                <div class="token-section">
                    <div class="token-box">
                        <div class="token-text">' . $token . '</div>
                        <button class="copy-btn" onclick="copyToken(\'' . $token . '\')">📋 Copy Token</button>
                    </div>
                </div>
            </div>';
        } else {
            return '
            <div class="user-card" style="border-left-color: ' . $user['color'] . ';">
                <div class="user-header">
                    <div class="user-icon" style="background: ' . $user['color'] . ';">
                        ' . strtoupper(substr($user['name'], 0, 1)) . '
                    </div>
                    <div class="user-info">
                        <h3>' . $user['name'] . '</h3>
                        <p>' . $user['description'] . '</p>
                    </div>
                </div>
                <div class="error">❌ Login failed for ' . $user['name'] . '</div>
            </div>';
        }
    }
} 