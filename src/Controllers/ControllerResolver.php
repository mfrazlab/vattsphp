<?php

namespace Vatts\Controllers;

class ControllerResolver
{
    protected string $projectPath;

    public function __construct(string $projectPath)
    {
        $this->projectPath = rtrim($projectPath, "\\/ ");
    }

    /**
     * Resolve uma string como "HomeController@index" em um callable compatível com Slim
     */
    public function resolve(string $action): callable
    {
        // suporta: Controller@method ou Namespace\\Controller@method
        if (strpos($action, '@') === false) {
            throw new \InvalidArgumentException('Action deve usar o formato Controller@method');
        }

        [$controllerShort, $method] = explode('@', $action, 2);

        // tenta prefixar com App\Controllers se não tiver namespace
        if (strpos($controllerShort, '\\') === false) {
            $controllerClass = 'App\\Controllers\\' . $controllerShort;
        } else {
            $controllerClass = $controllerShort;
        }

        return function (...$args) use ($controllerClass, $method) {
            if (!class_exists($controllerClass)) {
                throw new \RuntimeException("Controller $controllerClass não encontrado");
            }
            $controller = new $controllerClass();
            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Método $method não existe em $controllerClass");
            }

            return $controller->$method(...$args);
        };
    }
}

