<?php

namespace Vatts\Rpc;

use Vatts\Router\Request;
use Vatts\Router\Response;
use Vatts\Rpc\Attributes\Expose;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class RpcController
{
    public function handle(Request $request, Response $response)
    {
        try {
            $body = $request->getBody();
            $payload = is_array($body) ? $body : json_decode(json_encode($body), true);

            // Validação básica do payload
            if (!$payload || !isset($payload['file']) || !isset($payload['fn']) || !isset($payload['args'])) {
                $response->status(400)->json(['success' => false, 'error' => 'Invalid RPC payload']);
                return $response;
            }

            $file = $payload['file']; // ex: "UserActions" ou "Admin/Reports"
            $fn = $payload['fn'];
            $args = $payload['args'] ?? [];

            // --- SECURITY CHECK 1: Path Traversal ---
            // Não permite '../', caracteres nulos ou caminhos absolutos.
            // Limita a busca estritamente ao namespace App\Rpc\
            if (preg_match('/[^a-zA-Z0-9\/_]/', $file)) {
                $response->status(403)->json(['success' => false, 'error' => 'Invalid file path']);
                return $response;
            }

            // Converte "Admin/Reports" para "App\Rpc\Admin\Reports"
            $className = 'App\\Rpc\\' . str_replace('/', '\\', $file);

            if (!class_exists($className)) {
                $response->status(404)->json(['success' => false, 'error' => 'RPC file/class not found']);
                return $response;
            }

            $reflection = new ReflectionClass($className);

            if (!$reflection->hasMethod($fn)) {
                $response->status(404)->json(['success' => false, 'error' => "RPC function not found: {$fn}"]);
                return $response;
            }

            $method = $reflection->getMethod($fn);

            // --- SECURITY CHECK 2: Expose Annotation ---
            // Verifica se o método tem o atributo #[Expose]
            $attributes = $method->getAttributes(Expose::class);
            if (empty($attributes)) {
                $response->status(403)->json([
                    'success' => false,
                    'error' => "Function '{$fn}' is not exposed via RPC. Mark it with #[Expose]."
                ]);
                return $response;
            }

            // Instancia a classe que foi chamada
            $instance = $reflection->newInstance();

            // Opcional: Se a função pedir o $request do Vatts como primeiro parâmetro, nós injetamos.
            $params = $method->getParameters();
            if (!empty($params)) {
                $firstParamType = $params[0]->getType();
                if ($firstParamType && $firstParamType->getName() === Request::class) {
                    array_unshift($args, $request); // Coloca o $request no início do array de argumentos
                }
            }

            // Executa o método passando os argumentos do JS
            $result = $method->invokeArgs($instance, $args);

            $response->json(['success' => true, 'return' => $result]);
            return $response;

        } catch (Throwable $e) {
            $msg = $e->getMessage();
            $response->status(500)->json(['success' => false, 'error' => $msg]);
            return $response;
        }
    }
}

