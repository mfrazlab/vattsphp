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

            if (!$payload || !isset($payload['file']) || !isset($payload['fn']) || !isset($payload['args'])) {
                $response->status(400)->json(['success' => false, 'error' => 'Invalid RPC payload']);
                return $response;
            }

            $file = $payload['file']; // ex: "app/Services/UserActions" ou "app/Rpc/TestActions"
            $fn = $payload['fn'];
            $args = $payload['args'] ?? [];

            // Permite letras, números, barras, sublinhados e hífens
            if (preg_match('/[^a-zA-Z0-9\/_\\-]/', $file)) {
                $response->status(403)->json(['success' => false, 'error' => 'Invalid file path characters']);
                return $response;
            }

            // 1. Localiza a raiz do projeto real
            // Se o processo rodar em /public, voltamos um nível para a raiz do cliente
            $basePath = getcwd();
            if (basename($basePath) === 'public') {
                $basePath = dirname($basePath);
            }

            // 2. Define o caminho do arquivo físico a partir da raiz (sem forçar pasta app/Rpc)
            $filePath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file) . '.php';

            if (!file_exists($filePath)) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => 'RPC file not found',
                    'resolvedPath' => $filePath
                ]);
                return $response;
            }

            // 3. Inclui o arquivo manualmente
            require_once $filePath;

            // 4. Resolve o nome da classe dinamicamente
            // Converte "/" para "\"
            $className = str_replace('/', '\\', $file);

            // Se o caminho começar com "app/", corrigimos para "App\" (padrão PSR-4)
            if (str_starts_with(strtolower($className), 'app\\')) {
                $className = 'App\\' . substr($className, 4);
            }

            if (!class_exists($className)) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => "Class '{$className}' not found inside the required file",
                    'file' => $filePath
                ]);
                return $response;
            }

            $reflection = new ReflectionClass($className);

            if (!$reflection->hasMethod($fn)) {
                $response->status(404)->json(['success' => false, 'error' => "RPC function not found: {$fn}"]);
                return $response;
            }

            $method = $reflection->getMethod($fn);

            // Verifica se o método tem o atributo #[Expose]
            $attributes = $method->getAttributes(Expose::class);
            if (empty($attributes)) {
                $response->status(403)->json([
                    'success' => false,
                    'error' => "Function '{$fn}' is not exposed via RPC. Mark it with #[Expose]."
                ]);
                return $response;
            }

            $instance = $reflection->newInstance();

            // Injeção de dependência do Request se for o primeiro parâmetro
            $params = $method->getParameters();
            if (!empty($params)) {
                $firstParamType = $params[0]->getType();
                if ($firstParamType && $firstParamType->getName() === Request::class) {
                    array_unshift($args, $request);
                }
            }

            $result = $method->invokeArgs($instance, $args);

            $response->json(['success' => true, 'return' => $result]);
            return $response;

        } catch (Throwable $e) {
            $response->status(500)->json(['success' => false, 'error' => $e->getMessage()]);
            return $response;
        }
    }
}