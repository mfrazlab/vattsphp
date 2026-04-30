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

            $file = $payload['file'];
            $fn = $payload['fn'];
            $args = $payload['args'] ?? [];

            if (preg_match('/[^a-zA-Z0-9\/_]/', $file)) {
                $response->status(403)->json(['success' => false, 'error' => 'Invalid file path']);
                return $response;
            }

            // 1. Localiza a raiz do projeto do cliente
            $basePath = getcwd(); // Pega o diretório de execução (root do projeto)

            // 2. Define o caminho do arquivo físico (ex: app/Rpc/UserActions.php)
            // Você pode mudar 'app/Rpc/' para a pasta que preferir que os clientes usem
            $filePath = $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Rpc' . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file) . '.php';

            if (!file_exists($filePath)) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => 'RPC file not found',
                    'path' => $filePath
                ]);
                return $response;
            }

            // 3. Inclui o arquivo manualmente
            require_once $filePath;

            // 4. Resolve o nome da classe com Namespace
            // Como é uma lib, aqui você assume que o cliente segue o padrão App\Rpc
            // ou você pode extrair o namespace do arquivo se quiser ser 100% dinâmico
            $className = 'App\\Rpc\\' . str_replace('/', '\\', $file);

            if (!class_exists($className)) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => "Class '{$className}' not found inside file",
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

            $attributes = $method->getAttributes(Expose::class);
            if (empty($attributes)) {
                $response->status(403)->json([
                    'success' => false,
                    'error' => "Function '{$fn}' is not exposed via RPC. Mark it with #[Expose]."
                ]);
                return $response;
            }

            $instance = $reflection->newInstance();

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