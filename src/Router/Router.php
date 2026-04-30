<?php

namespace Vatts\Router;

class Router
{
    protected array $routes = [];
    protected array $globalMiddlewares = [];
    protected $fallbackHandler = null;

    /**
     * Registra uma rota GET
     */
    public function get(string $pattern, $handler): Route
    {
        return $this->register('GET', $pattern, $handler);
    }

    /**
     * Registra uma rota POST
     */
    public function post(string $pattern, $handler): Route
    {
        return $this->register('POST', $pattern, $handler);
    }

    /**
     * Registra uma rota PUT
     */
    public function put(string $pattern, $handler): Route
    {
        return $this->register('PUT', $pattern, $handler);
    }

    /**
     * Registra uma rota DELETE
     */
    public function delete(string $pattern, $handler): Route
    {
        return $this->register('DELETE', $pattern, $handler);
    }

    /**
     * Registra uma rota PATCH
     */
    public function patch(string $pattern, $handler): Route
    {
        return $this->register('PATCH', $pattern, $handler);
    }

    /**
     * Registra uma rota com método arbitrário
     */
    public function register(string $method, string $pattern, $handler): Route
    {
        $route = new Route($method, $pattern, $handler);
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Registra um middleware global
     */
    public function use(callable $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    /**
     * Registra um fallback handler para rotas não encontradas
     * Útil para proxy do frontend em dev ou servir SPA em prod
     */
    public function fallback(callable $handler): void
    {
        $this->fallbackHandler = $handler;
    }

    /**
     * Despacha uma requisição
     */
    public function dispatch(Request $request, Response $response): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Encontra a rota que corresponde
        $route = null;
        foreach ($this->routes as $r) {
            if ($r->matches($method, $path)) {
                $route = $r;
                break;
            }
        }

        if (!$route) {
            // Se não encontrou a rota, usa o fallback (se registrado)
            if ($this->fallbackHandler) {
                $request = $this->executeFallbackMiddlewares($request);
                try {
                    $response = ($this->fallbackHandler)($request, $response);
                } catch (\Throwable $e) {
                    $response->status(500)->json(['error' => $e->getMessage()]);
                }
                return $response;
            }

            // Sem fallback, retorna 404
            $response->status(404)->json(['error' => 'Route not found']);
            return $response;
        }

        // Extrai parâmetros e coloca no request
        $params = $route->extractParams($path);
        $request->setParams($params);

        // Executa middlewares globais
        foreach ($this->globalMiddlewares as $middleware) {
            $request = $middleware($request) ?? $request;
        }

        // Executa middlewares da rota
        foreach ($route->getMiddlewares() as $middleware) {
            $request = $middleware($request) ?? $request;
        }

        // Executa o handler da rota
        try {
            $response = $route->call($request, $response);
        } catch (\Throwable $e) {
            $response->status(500)->json(['error' => $e->getMessage()]);
        }

        return $response;
    }

    /**
     * Executa apenas os middlewares globais (sem os da rota)
     */
    protected function executeFallbackMiddlewares(Request $request): Request
    {
        // Executa middlewares globais
        foreach ($this->globalMiddlewares as $middleware) {
            $request = $middleware($request) ?? $request;
        }

        return $request;
    }

    /**
     * Inicia o servidor e despacha a requisição atual
     */
    public function run(): void
    {
        // Detecta o método HTTP
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Detecta o caminho (remove query string)
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Remove o prefixo do app (se houver)
        $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptPath !== '/' && strpos($path, $scriptPath) === 0) {
            $path = substr($path, strlen($scriptPath));
        }
        if (!$path) $path = '/';

        // Parse body
        $body = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            if (strpos($contentType, 'application/json') !== false) {
                $body = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $body = $_POST ?? [];
            }
        }

        // Parse headers
        $headers = getallheaders() ?? [];

        // Cria request
        $request = new Request($method, $path, $body, $_GET ?? [], $headers);

        // Cria response
        $response = new Response();

        // Despacha
        $response = $this->dispatch($request, $response);

        // Envia response
        http_response_code($response->getStatus());
        foreach ($response->getHeaders() as $key => $value) {
            header($key . ': ' . $value);
        }

        $body = $response->getBody();
        if ($body !== null) {
            echo is_string($body) ? $body : json_encode($body);
        }
    }
}

