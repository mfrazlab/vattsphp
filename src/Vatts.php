<?php

namespace Vatts;

use Vatts\Database\DB;
use Vatts\Controllers\ControllerResolver;
use Vatts\Router\Router;
use Vatts\Router\Request;
use Vatts\Router\Response;
use Vatts\Rpc\RpcController;
use Vatts\Handlers\FrontendHandler;
use Vatts\Middleware\SecurityHeadersMiddleware;
use Vatts\Middleware\RateLimitMiddleware;

class Vatts
{
    protected Router $router;
    protected ControllerResolver $resolver;
    protected string $projectPath;

    /**
     * Inicia o Vatts e retorna a instância do Router.
     * Config keys:
     * - project_path: caminho do projeto cliente (onde estão app/, public/, models/)
     * - db: array com configuração do banco de dados (PDO)
     * - autoload_helpers: bool (default: true) - registra helpers globais
     * - security: array com headers de segurança e CSP
     * - rate_limit: array com configurações de rate limit
     */
    public static function init(array $config = []): Router
    {
        $self = new self($config['project_path'] ?? getcwd());

        // Registra instância globalmente para helpers
        $GLOBALS['vatts_instance'] = $self;

        // bootstrap DB (Nosso ORM Customizado) se houver
        if (!empty($config['db']) && is_array($config['db'])) {
            $self->bootDatabase($config['db']);
        }

        $self->bootRouter();

        $securityDefaults = [
            'enabled' => true,
            'csp' => "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';",
            'csp_report_only' => false,
            'referrer_policy' => 'no-referrer',
            'x_content_type_options' => 'nosniff',
            'x_frame_options' => 'DENY',
            'x_xss_protection' => '0',
            'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
            'cross_origin_opener_policy' => 'same-origin',
            'cross_origin_resource_policy' => 'same-origin',
            'cross_origin_embedder_policy' => null,
            'hsts' => [
                'enabled' => true,
                'max_age' => 31536000,
                'include_subdomains' => true,
                'preload' => false,
                'require_https' => true
            ]
        ];

        $rateLimitDefaults = [
            'enabled' => true,
            'window' => 60,
            'max' => 120,
            'trust_proxy' => false,
            'proxy_header' => 'X-Forwarded-For',
            'allowlist' => [],
            'exclude' => [],
            'rules' => [
                ['pattern' => '/api/auth/*', 'window' => 60, 'max' => 30],
                ['pattern' => '/api/*', 'window' => 60, 'max' => 120]
            ]
        ];

        $securityConfig = array_replace_recursive($securityDefaults, $config['security'] ?? []);
        $rateLimitConfig = array_replace_recursive($rateLimitDefaults, $config['rate_limit'] ?? []);

        $self->router->addMiddleware(new SecurityHeadersMiddleware($securityConfig));
        $self->router->addMiddleware(new RateLimitMiddleware($rateLimitConfig));

        // auto-require models e controllers se configurado
        if ($config['autoload_models'] ?? true) {
            $self->autoloadModels();
        }
        if ($config['autoload_controllers'] ?? true) {
            $self->autoloadControllers();
        }

        // Carrega as classes de middleware na memória (sem registrar globalmente)
        $self->autoloadMiddlewares();

        // registra rota RPC automaticamente
        $self->registerRpcRoute();

        // registra fallback para frontend (prod/dev) automaticamente
        $self->registerFrontendFallback();

        // carrega as rotas do projeto cliente, se existir
        $routesFile = $self->projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'routes.php';
        if (is_file($routesFile)) {
            // expõe helper $app para as rotas (é o router agora)
            $app = $self->router;
            $v = $self;
            require $routesFile;
        }

        return $self->router;
    }

    public function __construct(string $projectPath)
    {
        $this->projectPath = rtrim($projectPath, "\\/ ");

        // Registra as variáveis de ambiente do .env antes de qualquer outra coisa
        $this->loadEnv();

        $this->resolver = new ControllerResolver($this->projectPath);
    }

    /**
     * Carrega e registra as variáveis do arquivo .env
     */
    protected function loadEnv(): void
    {
        $path = $this->projectPath . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $value = trim($value, "\"'");

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    protected function bootRouter(): void
    {
        $this->router = new Router();
    }

    protected function bootDatabase(array $dbConfig): void
    {
        // Inicializa o seu próprio DB Manager usando PDO
        DB::init($dbConfig);
    }

    protected function autoloadMiddlewares(): void
    {
        $dir = $this->projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'middlewares';
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }

    protected function autoloadModels(): void
    {
        $dir = $this->projectPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'models';
        if (!is_dir($dir)) {
            return;
        }

        // Apenas carrega os arquivos em memória. O schema_sync
        // agora acontece no próprio Model, somente quando ele for usado.
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }

    protected function autoloadControllers(): void
    {
        $dir = $this->projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers';
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }

    protected function registerRpcRoute(): void
    {
        $this->router->post('/api/prpc', function (Request $request, Response $response) {
            $rpc = new RpcController();
            return $rpc->handle($request, $response);
        });
    }

    protected function registerFrontendFallback(): void
    {
        $env = getenv('APP_ENV') ?: 'dev';
        $port = getenv('DEV_SERVER_PORT') ?: 3000;

        $handler = new FrontendHandler($this->projectPath, $env, (int) $port);
        $this->router->fallback($handler);
    }

    public function action(string $action): callable
    {
        return $this->resolver->resolve($action);
    }
}