<?php

namespace Vatts\Router;

class Router
{
    protected array $routes = [];
    protected $fallbackHandler = null;

    /**
     * Pilha para gerenciar prefixos e middlewares de grupos aninhados
     */
    protected array $groupStack = [];
    protected array $globalMiddlewares = [];

    public function get(string $pattern, $handler): Route
    {
        return $this->register('GET', $pattern, $handler);
    }

    public function post(string $pattern, $handler): Route
    {
        return $this->register('POST', $pattern, $handler);
    }

    public function put(string $pattern, $handler): Route
    {
        return $this->register('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, $handler): Route
    {
        return $this->register('DELETE', $pattern, $handler);
    }

    public function patch(string $pattern, $handler): Route
    {
        return $this->register('PATCH', $pattern, $handler);
    }

    /**
     * Agrupa rotas sob um prefixo ou conjunto de middlewares
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function addMiddleware(string|callable|array|object $middleware): self
    {
        if (is_array($middleware) && !is_callable($middleware)) {
            $this->globalMiddlewares = array_merge($this->globalMiddlewares, $middleware);
        } else {
            $this->globalMiddlewares[] = $middleware;
        }

        return $this;
    }

    public function register(string $method, string $pattern, $handler): Route
    {
        $prefix = '';
        $groupMiddlewares = [];

        // Acumula atributos dos grupos pais
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $m = $group['middleware'];
                if (is_array($m)) {
                    $groupMiddlewares = array_merge($groupMiddlewares, $m);
                } else {
                    $groupMiddlewares[] = $m;
                }
            }
        }

        // Normalização do pattern final:
        // 1. Remove barras extras no final do prefixo e início do pattern
        // 2. Garante que o pattern final não termine com barra, a menos que seja a própria raiz '/'
        $finalPattern = rtrim($prefix, '/') . '/' . ltrim($pattern, '/');

        if ($finalPattern !== '/') {
            $finalPattern = rtrim($finalPattern, '/');
        }

        $route = new Route($method, $finalPattern, $handler);

        // Aplica os middlewares do grupo na rota
        if (!empty($groupMiddlewares)) {
            $route->middleware($groupMiddlewares);
        }

        $this->routes[] = $route;
        return $route;
    }

    public function fallback(callable $handler): void
    {
        $this->fallbackHandler = $handler;
    }

    public function dispatch(Request $request, Response $response): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Normaliza o path buscado para bater com a rota registrada (remove slash final se não for raiz)
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach ($this->globalMiddlewares as $middleware) {
            $result = $this->resolveMiddleware($middleware, $request, $response);
            if ($result instanceof Response) return $result;
            if ($result instanceof Request) $request = $result;
        }

        $route = null;
        foreach ($this->routes as $r) {
            if ($r->matches($method, $path)) {
                $route = $r;
                break;
            }
        }

        if (!$route) {
            if ($this->fallbackHandler) {
                try {
                    return ($this->fallbackHandler)($request, $response);
                } catch (\Throwable $e) {
                    error_log($e);
                    return $response->status(500)->json(['error' => $e->getMessage(), 'e' => $e]);
                }
            }
            return $response->status(404)->json(['error' => 'Route not found']);
        }

        $request->setParams($route->extractParams($path));

        foreach ($route->getMiddlewares() as $middleware) {
            $result = $this->resolveMiddleware($middleware, $request, $response);
            if ($result instanceof Response) return $result;
            if ($result instanceof Request) $request = $result;
        }

        try {
            return $route->call($request, $response);
        } catch (\Throwable $e) {
            return $response->status(500)->json(['error' => $e->getMessage()]);
        }
    }

    protected function resolveMiddleware($middleware, Request $request, Response $response)
    {
        if (is_callable($middleware)) {
            return $middleware($request, $response);
        }

        if (is_string($middleware)) {
            if (class_exists($middleware)) {
                $instance = new $middleware();
                return $instance->handle($request, $response);
            }

            foreach (get_declared_classes() as $class) {
                if (is_subclass_of($class, \Vatts\Utils\Middleware::class)) {
                    if (property_exists($class, 'name') && $class::$name === $middleware) {
                        $instance = new $class();
                        return $instance->handle($request, $response);
                    }
                }
            }
            throw new \Exception("Middleware '{$middleware}' não encontrado.");
        }

        if (is_object($middleware) && method_exists($middleware, 'handle')) {
            return $middleware->handle($request, $response);
        }

        return null;
    }

    public static function parseRequest(): Request
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptPath !== '/' && $scriptPath !== '.' && strpos($path, $scriptPath) === 0) {
            $path = substr($path, strlen($scriptPath));
        }
        $path = $path ?: '/';

        $body = [];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            if (strpos($contentType, 'application/json') !== false) {
                $body = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                $body = $_POST ?? [];
            }
        }

        return new Request($method, $path, $body, $_GET ?? [], getallheaders() ?: []);
    }

    public function run(): void
    {
        $request = self::parseRequest();
        $response = new Response();

        $response = $this->dispatch($request, $response);

        http_response_code($response->getStatus());
        foreach ($response->getHeaders() as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    header($key . ': ' . $v);
                }
            } else {
                header($key . ': ' . $value);
            }
        }

        $body = $response->getBody();
        if ($body !== null) {
            echo is_string($body) ? $body : json_encode($body);
        }
    }
}