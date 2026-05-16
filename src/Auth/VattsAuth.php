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
            $this->configureSession($config['session'] ?? []);
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
     * Valida segurança contra Hijacking e inatividade
     *
     * @return array|null Retorna os dados do usuário ou null se não estiver logado
     */
    public function getSession(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['vatts_auth_user'])) {
            return null;
        }

        // --- VERIFICAÇÕES DE SEGURANÇA (ANTI-HIJACKING) ---
        $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 1. Valida se o navegador/dispositivo mudou repentinamente
        if (isset($_SESSION['vatts_auth_ua']) && $_SESSION['vatts_auth_ua'] !== $currentUa) {
            $this->signOut();
            return null;
        }

        // 2. Valida se o IP mudou (Opcional, pois redes móveis mudam muito de IP)
        $bindIp = $this->config['session']['bind_ip'] ?? false;
        if ($bindIp && isset($_SESSION['vatts_auth_ip']) && $_SESSION['vatts_auth_ip'] !== $currentIp) {
            $this->signOut();
            return null;
        }

        // 3. Valida Inatividade (Idle Timeout)
        $idleTimeout = $this->config['session']['idle_timeout'] ?? 7200; // Padrão: 2 horas (7200s)
        if (isset($_SESSION['vatts_auth_last_activity']) && (time() - $_SESSION['vatts_auth_last_activity']) > $idleTimeout) {
            $this->signOut();
            return null;
        }

        // Atualiza a última atividade
        $_SESSION['vatts_auth_last_activity'] = time();

        return $_SESSION['vatts_auth_user'];
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

        // Limpa todos os dados de segurança e o usuário
        unset(
            $_SESSION['vatts_auth_user'],
            $_SESSION['vatts_auth_ua'],
            $_SESSION['vatts_auth_ip'],
            $_SESSION['vatts_auth_last_activity']
        );

        // Destrói a sessão completamente e apaga o cookie
        session_destroy();

        return true;
    }

    /**
     * Configura as opções da sessão, incluindo tempo de vida, segurança e cookies
     */
    protected function configureSession(array $sessionConfig): void
    {
        $lifetimeDays = (int) ($sessionConfig['lifetime_days'] ?? 30);
        $lifetime = max(0, $lifetimeDays * 86400);

        $secure = $sessionConfig['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $httpOnly = $sessionConfig['httponly'] ?? true; // Crucial contra XSS
        $sameSite = $sessionConfig['samesite'] ?? 'Lax'; // 'Strict' é ainda mais seguro, se o app permitir
        $path = $sessionConfig['path'] ?? '/';
        $domain = $sessionConfig['domain'] ?? '';

        // --- HARDENING DO PHP SESSIONS ---
        ini_set('session.use_strict_mode', '1'); // Impede Session Fixation (rejeita IDs criados pelo atacante)
        ini_set('session.use_only_cookies', '1'); // Força uso exclusivo de cookies (nada de ID na URL)
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

            // Salva na sessão para validação posterior (Recomendado)
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['csrf_token'] = $csrfToken;

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

            // Garante que a sessão tá iniciada
            if (session_status() === PHP_SESSION_NONE) session_start();

            // Salva na sessão do PHP e gera um novo ID de sessão (Mitiga Session Fixation na hora do login)
            session_regenerate_id(true);

            $_SESSION['vatts_auth_user'] = $userToSave;

            // --- REGISTRA OS DADOS PARA SEGURANÇA (FINGERPRINTING) ---
            $_SESSION['vatts_auth_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $_SESSION['vatts_auth_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $_SESSION['vatts_auth_last_activity'] = time();

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
// GET /api/auth/popup-callback
        $router->get('/api/auth/popup-callback', function (Request $req, Response $res) {
            $query = $req->getQuery();

            // Frameworks podem passar query params como booleanos REAIS ou strings.
            // Isso garante que ele pegue "true", "1" ou true booleano.
            $successParam = $query['success'] ?? false;
            $success = $successParam === 'true' || $successParam === true || $successParam === '1' || $successParam === 1;

            $error = $query['error'] ?? null;
            $provider = $query['provider'] ?? 'unknown';
            $callbackUrl = $query['callbackUrl'] ?? '/';

            // Usar json_encode BLINDA o código contra erros de sintaxe no Javascript
            $payload = [
                'type' => $success ? 'oauth-success' : 'oauth-error',
                'provider' => $provider,
            ];

            if ($success) {
                $payload['callbackUrl'] = $callbackUrl;
            } else {
                $payload['error'] = $error ?: 'Authentication failed';
            }

            $jsonPayload = json_encode($payload);

            $headingText = $success ? "✓ Autenticação bem-sucedida" : "✗ Erro na autenticação";
            $messageText = $success ? "Fechando janela..." : ($error ?: "Algo deu errado");

            $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Authenticating...</title>
        <style>
            body { 
                font-family: sans-serif; 
                display: flex; 
                justify-content: center; 
                align-items: center; 
                height: 100vh; 
                margin: 0; 
                background: #000000; 
                color: #ffffff; 
            }
            .container { text-align: center; }
            .spinner { 
                border: 3px solid rgba(255,255,255,0.05); 
                border-radius: 50%; 
                border-top: 3px solid #00e5ff; 
                width: 40px; 
                height: 40px; 
                animation: spin 1s linear infinite; 
                margin: 0 auto 20px; 
            }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            h2 { margin: 0; font-size: 24px; font-weight: 500; }
            p { margin: 10px 0 0; opacity: 0.7; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="spinner"></div>
            <h2>{$headingText}</h2>
            <p>{$messageText}</p>
        </div>
        <script>
            (function() {
                try {
                    // O json_encode do PHP já cospe o objeto Javascript certinho
                    const payload = {$jsonPayload};
                    console.log("[Vatts.js OAuth Popup] Preparando para enviar payload:", payload);
                    
                    if (window.opener && !window.opener.closed) {
                        window.opener.postMessage(payload, "*");
                        console.log("[Vatts.js OAuth Popup] Mensagem enviada com sucesso para o opener!");
                    } else {
                        console.error("[Vatts.js OAuth Popup] window.opener não foi encontrado ou a aba principal foi fechada.");
                    }
                    
                    // Aumentei pra 1.5s só pro React ter tempo de respirar e dar o fetchSession antes da janela sumir
                    setTimeout(() => window.close(), 1500);
                } catch (e) { 
                    console.error("[Vatts.js OAuth Popup] Erro Crítico:", e); 
                }
            })();
        </script>
    </body>
    </html>
    HTML;

            return $res->html($html);
        });
    }
}