<?php

namespace Vatts\Auth;

use Vatts\Router\Router;
use Vatts\Router\Request;
use Vatts\Router\Response;

class VattsAuth
{
    /** @var AuthProviderInterface[] */
    protected array $providers = [];
    protected array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($config['providers'])) {
            foreach ($config['providers'] as $provider) {
                $this->providers[$provider->getId()] = $provider;
            }
        }
    }

    /**
     * Retorna a sessão atual do usuário autenticado para uso interno no backend
     *
     * @return array|null Retorna os dados do usuário ou null se não estiver logado
     */
    public function getSession(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['vatts_auth_user'] ?? null;
    }

    /**
     * Encerra a sessão do usuário atual pelo backend
     *
     * @return bool
     */
    public function signOut(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['vatts_auth_user']);
        return true;
    }

    /**
     * Registra as rotas padrões (/api/auth/*) no Router do Vatts
     */
    public function registerRoutes(Router $router): void
    {
        // 1. Rotas Adicionais dos Providers (Callbacks, Passkeys Register, etc)
        foreach ($this->providers as $provider) {
            foreach ($provider->getAdditionalRoutes() as $route) {
                $method = strtolower($route['method']); // get, post
                $router->$method($route['path'], $route['handler']);
            }
        }

        // 2. Rotas Fixas do Sistema

        // GET /api/auth/session
        $router->get('/api/auth/session', function (Request $req, Response $res) {
            $session = $this->getSession();

            // Permite modificar os dados da sessão antes de enviar pro front
            if ($session && isset($this->config['callbacks']['session']) && is_callable($this->config['callbacks']['session'])) {
                $session = call_user_func($this->config['callbacks']['session'], $session);
            }

            return $res->json(['session' => $session ? ['user' => $session] : null]);
        });

        // GET /api/auth/csrf
        $router->get('/api/auth/csrf', function (Request $req, Response $res) {
            // Token criptograficamente seguro equivalente ao do Node.js
            $csrfToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            return $res->json(['csrfToken' => $csrfToken]);
        });

        // GET /api/auth/providers
        $router->get('/api/auth/providers', function (Request $req, Response $res) {
            $publicProviders = [];
            foreach ($this->providers as $id => $provider) {
                $publicProviders[$id] = $provider->getConfig();
            }
            return $res->json(['providers' => $publicProviders]);
        });

        // POST /api/auth/signin
        $router->post('/api/auth/signin', function (Request $req, Response $res) {
            $body = $req->getBody() ?? [];
            $providerId = $body['provider'] ?? 'credentials';

            if (!isset($this->providers[$providerId])) {
                return $res->status(400)->json(['error' => 'Provider not configured']);
            }

            $provider = $this->providers[$providerId];

            // Emula o map da property popup string/boolean do JS
            if (isset($body['popup'])) {
                $body['popup'] = (string) $body['popup'];
            }

            $result = $provider->handleSignIn($body, $req);

            if (!$result) {
                return $res->status(401)->json(['error' => 'Invalid credentials']);
            }

            // Se for uma string, é uma URL de OAuth, retorna para redirect
            if (is_string($result)) {
                return $res->json([
                    'success' => true,
                    'redirectUrl' => $result,
                    'type' => 'oauth'
                ]);
            }

            // Permite modificar os dados do usuário antes de salvar na sessão do PHP
            $userToSave = $result;
            if (isset($this->config['callbacks']['jwt']) && is_callable($this->config['callbacks']['jwt'])) {
                $userToSave = call_user_func($this->config['callbacks']['jwt'], $userToSave);
            }

            // Salva na sessão do PHP
            $_SESSION['vatts_auth_user'] = $userToSave;

            return $res->json([
                'success' => true,
                'user' => $userToSave,
                'type' => 'session'
            ]);
        });

        // POST /api/auth/signout
        $router->post('/api/auth/signout', function (Request $req, Response $res) {
            $this->signOut();
            return $res->json(['success' => true]);
        });

        // GET /api/auth/popup-callback
        $router->get('/api/auth/popup-callback', function (Request $req, Response $res) {
            $query = $req->getQuery();
            $success = ($query['success'] ?? '') === 'true';
            $error = $query['error'] ?? null;
            $provider = $query['provider'] ?? 'unknown';
            $callbackUrl = $query['callbackUrl'] ?? '/';

            $type = $success ? "'oauth-success'" : "'oauth-error'";
            $errorJs = $error ? "\"$error\"" : "'Authentication failed'";

            $html = <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Authenticating...</title>
                <style>
                    body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
                    .container { text-align: center; }
                    .spinner { border: 4px solid rgba(255,255,255,0.3); border-radius: 50%; border-top: 4px solid white; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
                    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                    h2 { margin: 0; font-size: 24px; }
                    p { margin: 10px 0 0; opacity: 0.9; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="spinner"></div>
                    <h2>" . ($success ? "✓ Autenticação bem-sucedida" : "✗ Erro na autenticação") . "</h2>
                    <p>" . ($success ? "Fechando janela..." : ($error ?: "Algo deu errado")) . "</p>
                </div>
                <script>
                    (function() {
                        try {
                            if (window.opener) {
                                window.opener.postMessage({
                                    type: {$type},
                                    provider: "{$provider}",
                                    " . ($success ? "callbackUrl: '{$callbackUrl}'" : "error: {$errorJs}") . "
                                }, window.location.origin);
                            }
                            setTimeout(() => window.close(), 1000);
                        } catch (e) { console.error(e); }
                    })();
                </script>
            </body>
            </html>
            HTML;

            return $res->html($html);
        });
    }
}