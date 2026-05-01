<?php

namespace Vatts\Router;

class Route
{
    protected string $method;
    protected string $pattern;
    protected $handler;
    protected array $middlewares = [];
    protected array $paramNames = [];
    protected array $paramPatterns = [];

    public function __construct(string $method, string $pattern, $handler)
    {
        $this->method = strtoupper($method);
        $this->pattern = $pattern;
        $this->handler = $handler;
        $this->parsePattern();
    }

    protected function parsePattern(): void
    {
        // Exemplo: /users/[id]/posts/[[limit]]/comments/[...items]
        // Transforma em regex: /users/(?P<id>[^/]+)/posts/(?P<limit>[^/]*)?/comments/(?P<items>.*)

        $regex = $this->pattern;

        // [...]variadic (obrigatório)
        $regex = preg_replace_callback('#\[\.\.\.([a-zA-Z_][a-zA-Z0-9_]*)\]#', function ($m) {
            $name = $m[1];
            $this->paramNames[] = $name;
            $this->paramPatterns[$name] = 'variadic';
            return '(?P<' . $name . '>.*)';
        }, $regex);

        // [[...variadic]] (opcional)
        $regex = preg_replace_callback('#\[\[\.\.\.([a-zA-Z_][a-zA-Z0-9_]*)\]\]#', function ($m) {
            $name = $m[1];
            $this->paramNames[] = $name;
            $this->paramPatterns[$name] = 'variadic_optional';
            return '(?P<' . $name . '>.*)?';
        }, $regex);

        // [param] (obrigatório)
        $regex = preg_replace_callback('#\[([a-zA-Z_][a-zA-Z0-9_]*)\]#', function ($m) {
            $name = $m[1];
            $this->paramNames[] = $name;
            $this->paramPatterns[$name] = 'required';
            return '(?P<' . $name . '>[^/]+)';
        }, $regex);

        // [[param]] (opcional)
        $regex = preg_replace_callback('#\[\[([a-zA-Z_][a-zA-Z0-9_]*)\]\]#', function ($m) {
            $name = $m[1];
            $this->paramNames[] = $name;
            $this->paramPatterns[$name] = 'optional';
            return '(?P<' . $name . '>[^/]+)?';
        }, $regex);

        $this->pattern = '#^' . $regex . '$#';
    }

    public function matches(string $method, string $path): bool
    {
        if (strtoupper($method) !== $this->method) {
            return false;
        }
        return preg_match($this->pattern, $path) === 1;
    }

    public function extractParams(string $path): array
    {
        $matches = [];
        preg_match($this->pattern, $path, $matches);

        $params = [];
        foreach ($this->paramNames as $name) {
            $params[$name] = $matches[$name] ?? null;
        }

        return $params;
    }

    /**
     * Adiciona um ou múltiplos middlewares na rota.
     * Pode ser um Callable, o nome da classe, um alias ou um array misturado de ambos.
     */
    public function middleware(string|callable|array|object $middleware): self
    {
        // Se for array e não for um array-callable do PHP (ex: [Classe, metodo])
        if (is_array($middleware) && !is_callable($middleware)) {
            $this->middlewares = array_merge($this->middlewares, $middleware);
        } else {
            $this->middlewares[] = $middleware;
        }

        return $this;
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function call(Request $request, Response $response): Response
    {
        // Handler pode ser:
        // - Closure: function(Request, Response)
        // - Callable array: [ClassName::class, 'method'] -> instancia a classe e chama o método
        // - Callable array com instância: [$instance, 'method'] -> chama diretamente
        $h = $this->handler;

        if (is_array($h) && is_string($h[0]) && class_exists($h[0])) {
            $class = $h[0];
            $method = $h[1];
            $instance = new $class();
            return $instance->{$method}($request, $response);
        }

        // Handler pode ser closure ou already-callable
        return ($h)($request, $response);
    }
}