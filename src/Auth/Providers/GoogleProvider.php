<?php

namespace Vatts\Auth\Providers;

use Vatts\Auth\AuthProviderInterface;
use Vatts\Router\Request;
use Vatts\Router\Response;
use Exception;

class GoogleProvider implements AuthProviderInterface
{
    public string $id;
    public string $name;
    public string $type = 'google';

    private array $config;
    private array $defaultScope = [
        'openid',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ];

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->id = $config['id'] ?? 'google';
        $this->name = $config['name'] ?? 'Google';

        if (session_status() === PHP_SESSION_NONE) {
            $this->configureSession($config['session'] ?? []);
            session_start();
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function handleSignIn(array $credentials, Request $req)
    {
        if (!empty($credentials['code'])) {
            return $this->processOAuthCallback($credentials);
        }

        // Se não tem código, retorna a URL de autorização (Front-end vai redirecionar)
        return $this->getAuthorizationUrl($credentials['popup'] === 'true');
    }

    private function processOAuthCallback(array $credentials): ?array
    {
        try {
            $code = $credentials['code'];

            // Troca o código por um access token via cURL
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'client_id' => $this->config['clientId'],
                'client_secret' => $this->config['clientSecret'],
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->config['callbackUrl'] ?? '',
            ]));

            $tokenResult = curl_exec($ch);
            if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
                throw new Exception("Failed to exchange code for token: " . $tokenResult);
            }
            curl_close($ch);

            $tokens = json_decode($tokenResult, true);

            // Busca dados do usuário via cURL
            $chInfo = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
            curl_setopt($chInfo, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chInfo, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $tokens['access_token']
            ]);

            $userResult = curl_exec($chInfo);
            if (curl_getinfo($chInfo, CURLINFO_HTTP_CODE) !== 200) {
                throw new Exception("Failed to fetch user data");
            }
            curl_close($chInfo);

            $googleUser = json_decode($userResult, true);

            return [
                'id' => $googleUser['id'] ?? '',
                'name' => $googleUser['name'] ?? '',
                'email' => $googleUser['email'] ?? '',
                'image' => $googleUser['picture'] ?? null,
                'provider' => $this->id,
                'providerId' => $googleUser['id'] ?? '',
                'accessToken' => $tokens['access_token'],
                'refreshToken' => $tokens['refresh_token'] ?? null
            ];

        } catch (Exception $error) {
            error_log("[{$this->id} Provider] Error during OAuth callback: " . $error->getMessage());
            return null;
        }
    }

    protected function configureSession(array $sessionConfig): void
    {
        $lifetimeDays = (int) ($sessionConfig['lifetime_days'] ?? 30);
        $lifetime = max(0, $lifetimeDays * 86400);

        $secure = $sessionConfig['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $httpOnly = $sessionConfig['httponly'] ?? true;
        $sameSite = $sessionConfig['samesite'] ?? 'Lax';
        $path = $sessionConfig['path'] ?? '/';
        $domain = $sessionConfig['domain'] ?? '';

        ini_set('session.gc_maxlifetime', (string) $lifetime);

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ]);
        } else {
            session_set_cookie_params($lifetime, $path, $domain, $secure, $httpOnly);
        }
    }

    public function getAuthorizationUrl(bool $isPopup = false): string
    {
        $params = [
            'client_id' => $this->config['clientId'],
            'redirect_uri' => $this->config['callbackUrl'] ?? '',
            'response_type' => 'code',
            'scope' => implode(' ', $this->config['scope'] ?? $this->defaultScope)
        ];

        if ($isPopup) {
            $params['state'] = 'popup=true&timestamp=' . time();
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    public function getAdditionalRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'path' => '/api/auth/callback/google',
                'handler' => function (Request $req, Response $res) {
                    $query = $req->getQuery();
                    $code = $query['code'] ?? null;
                    $state = $query['state'] ?? '';
                    $isPopup = str_contains($state, 'popup=true');

                    if (!$code) {
                        if ($isPopup) {
                            return $res->redirect("/api/auth/popup-callback?success=false&error=Authorization+code+not+provided&provider={$this->id}");
                        }
                        return $res->status(400)->json(['error' => 'Authorization code not provided']);
                    }

                    // Processa o callback diretamente
                    $user = $this->processOAuthCallback(['code' => $code]);

                    if ($user) {
                        // Seta a sessão nativamente no PHP (Simulando o POST interno do JS)
                        $_SESSION['vatts_auth_user'] = $user;
                        session_regenerate_id(true);

                        if ($isPopup) {
                            $callbackUrl = $this->config['successUrl'] ?? '/';
                            return $res->redirect("/api/auth/popup-callback?success=true&provider={$this->id}&callbackUrl=" . urlencode($callbackUrl));
                        }

                        if (!empty($this->config['successUrl'])) {
                            return $res->redirect($this->config['successUrl']);
                        }
                        return $res->json(['success' => true]);
                    }

                    // Erro
                    if ($isPopup) {
                        return $res->redirect("/api/auth/popup-callback?success=false&error=Session+creation+failed&provider={$this->id}");
                    }
                    return $res->status(500)->json(['error' => 'Session creation failed']);
                }
            ]
        ];
    }

    public function getConfig(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'clientId' => $this->config['clientId'],
            'scope' => $this->config['scope'] ?? $this->defaultScope,
            'callbackUrl' => $this->config['callbackUrl'] ?? null
        ];
    }
}