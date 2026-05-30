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

        $this->configureSession($config['session'] ?? []);

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
     * Valida segurança contra Hijacking, inatividade e integridade (via callback)
     *
     * @return array|null Retorna os dados do usuário ou null se não estiver logado
     */
    public function getSession(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            $this->configureSession($this->config['session'] ?? []);
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

        // 2. Valida se o IP mudou
        $bindIp = $this->config['session']['bind_ip'] ?? false;
        if ($bindIp && isset($_SESSION['vatts_auth_ip']) && $_SESSION['vatts_auth_ip'] !== $currentIp) {
            $this->signOut();
            return null;
        }

        // 3. Valida Inatividade (Idle Timeout)
        $idleTimeout = $this->config['session']['idle_timeout'] ?? 7200;
        if (isset($_SESSION['vatts_auth_last_activity']) && (time() - $_SESSION['vatts_auth_last_activity']) > $idleTimeout) {
            $this->signOut();
            return null;
        }

        $sessionUser = $_SESSION['vatts_auth_user'];

        // 4. Delega a validação de integridade para o Callback configurado (Ex: checar se a senha mudou ou usuário foi deletado)
        if (isset($this->config['callbacks']['session']) && is_callable($this->config['callbacks']['session'])) {
            $validatedUser = call_user_func($this->config['callbacks']['session'], $sessionUser);

            // Se a validação falhar (usuário banido, senha mudou, deletado), DESTRÓI a sessão do servidor instantaneamente.
            if (!$validatedUser) {
                $this->signOut();
                return null;
            }
            // Atualizamos a var com os dados frescos vindos do banco
            $sessionUser = $validatedUser;
        }

        // Atualiza a última atividade apenas se passou em todas as verificações
        $_SESSION['vatts_auth_last_activity'] = time();

        return $sessionUser;
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

        unset(
            $_SESSION['vatts_auth_user'],
            $_SESSION['vatts_auth_ua'],
            $_SESSION['vatts_auth_ip'],
            $_SESSION['vatts_auth_last_activity']
        );

        session_destroy();

        $sessionConfig = $this->config['session'] ?? [];
        $path = $sessionConfig['path'] ?? '/';
        $domain = $sessionConfig['domain'] ?? '';
        setcookie(session_name(), '', time() - 3600, $path, $domain);

        return true;
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

        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            ini_set('session.cookie_lifetime', (string) $lifetime);

            $sessionPath = sys_get_temp_dir() . '/vatts_sessions';
            if (!is_dir($sessionPath)) {
                @mkdir($sessionPath, 0777, true);
            }
            ini_set('session.save_path', $sessionPath);

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
        } else {
            $this->forceCookieExpiration();
        }
    }

    protected function forceCookieExpiration(): void
    {
        $sessionConfig = $this->config['session'] ?? [];
        $lifetimeDays = (int) ($sessionConfig['lifetime_days'] ?? 30);
        $lifetime = max(0, $lifetimeDays * 86400);

        $secure = $sessionConfig['secure'] ?? (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $httpOnly = $sessionConfig['httponly'] ?? true;
        $sameSite = $sessionConfig['samesite'] ?? 'Lax';
        $path = $sessionConfig['path'] ?? '/';
        $domain = $sessionConfig['domain'] ?? '';

        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + $lifetime,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ]);
        } else {
            setcookie(session_name(), session_id(), time() + $lifetime, $path, $domain, $secure, $httpOnly);
        }
    }

    public function registerRoutes(Router $router): void
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->getAdditionalRoutes() as $route) {
                $method = strtolower($route['method']);
                $router->$method($route['path'], $route['handler']);
            }
        }

        // GET /api/auth/session
        $router->get('/api/auth/session', function (Request $req, Response $res) {
            // A chamada getSession() agora já embute e processa o callback internamente
            $session = $this->getSession();
            return $res->json(['session' => $session ? ['user' => $session] : null]);
        });

        // GET /api/auth/csrf
        $router->get('/api/auth/csrf', function (Request $req, Response $res) {
            $csrfToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
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

            if (isset($body['popup'])) {
                $body['popup'] = (string) $body['popup'];
            }

            $result = $provider->handleSignIn($body, $req);

            if (!$result) {
                return $res->status(401)->json(['error' => 'Invalid credentials']);
            }

            if (is_string($result)) {
                return $res->json([
                    'success' => true,
                    'redirectUrl' => $result,
                    'type' => 'oauth'
                ]);
            }

            $userToSave = $result;
            if (isset($this->config['callbacks']['jwt']) && is_callable($this->config['callbacks']['jwt'])) {
                $userToSave = call_user_func($this->config['callbacks']['jwt'], $userToSave);
            }

            if (session_status() === PHP_SESSION_NONE) session_start();

            session_regenerate_id(true);
            $this->forceCookieExpiration();

            $_SESSION['vatts_auth_user'] = $userToSave;
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
        $router->get('/api/auth/popup-callback', function (Request $req, Response $res) {
            header('Cross-Origin-Opener-Policy: unsafe-none');
            header('Cross-Origin-Resource-Policy: cross-origin');
            header('Cross-Origin-Embedder-Policy: unsafe-none');

            $query = $req->getQuery();
            $successParam = $query['success'] ?? false;
            $success = $successParam === 'true' || $successParam === true || $successParam === '1' || $successParam === 1;

            $error = $query['error'] ?? null;
            $provider = $query['provider'] ?? 'unknown';
            $callbackUrl = $query['callbackUrl'] ?? '/';

            $payload = [
                'type' => $success ? 'oauth-success' : 'oauth-error',
                'provider' => $provider,
            ];

            if ($success) {
                $payload['callbackUrl'] = $callbackUrl;
            } else {
                $payload['error'] = $error ?: 'Authentication failed';
            }

            // [SEGURANÇA] JSON encoding rigoroso para evitar XSS dentro de tags <script>
            $jsonPayload = json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
            $headingText = $success ? "✓ Autenticação bem-sucedida" : "✗ Erro na autenticação";
            $messageText = $success ? "Fechando janela..." : htmlspecialchars($error ?: "Algo deu errado", ENT_QUOTES, 'UTF-8');
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

            // [SEGURANÇA] Evita Host Header Injection via sanitização.
            $safeHost = htmlspecialchars($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8');
            $safeOrigin = $protocol . $safeHost;

            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Authenticating...</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #000000; color: #ffffff; }
        .container { text-align: center; }
        .spinner { border: 3px solid rgba(255,255,255,0.05); border-radius: 50%; border-top: 3px solid #00e5ff; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
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
                const payload = {$jsonPayload};
                const targetOrigin = "{$safeOrigin}"; 
                
                if (window.opener) {
                    window.opener.postMessage(payload, targetOrigin);
                } else {
                    console.error("[Vatts.js OAuth] window.opener AINDA está nulo. O navegador bloqueou a referência.");
                }
                
                setTimeout(() => window.close(), 1000);
            } catch (e) { 
                console.error("[Vatts.js OAuth] Erro Crítico:", e); 
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