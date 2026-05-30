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

            // [SEGURANÇA] Validação estrita de tipos (Type Juggling / Anti-DOS)
            if (!$payload || empty($payload['file']) || empty($payload['fn']) ||
                !is_string($payload['file']) || !is_string($payload['fn'])) {

                $response->status(400)->json(['success' => false, 'error' => 'Invalid RPC payload format']);
                return $response;
            }

            if (isset($payload['args']) && !is_array($payload['args'])) {
                $response->status(400)->json(['success' => false, 'error' => 'RPC args must be an array']);
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
            $basePath = getcwd();
            if (basename($basePath) === 'public') {
                $basePath = dirname($basePath);
            }

            // 2. Define o caminho do arquivo físico a partir da raiz
            $filePath = $basePath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file) . '.php';

            // [SEGURANÇA] LFI Hardening: Garante que o arquivo existe E obrigatoriamente
            // está contido dentro da pasta raiz permitida da aplicação.
            $realFilePath = realpath($filePath);
            $realBasePath = realpath($basePath);

            if (!$realFilePath || !$realBasePath || !str_starts_with($realFilePath, $realBasePath)) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => 'RPC file not found'
                ]);
                return $response;
            }

            // 3. Inclui o arquivo manualmente
            require_once $realFilePath;

            // 4. Resolve o nome da classe dinamicamente
            $className = str_replace('/', '\\', $file);

            // Se o caminho começar com "app/", corrigimos para "App\" (padrão PSR-4)
            if (str_starts_with(strtolower($className), 'app\\')) {
                $className = 'App\\' . substr($className, 4);
            }

            if (!class_exists($className)) {
                $response->status(404)->json([
                    'success' => false,
                    'error' => "Class not found inside the required file"
                ]);
                return $response;
            }

            $reflection = new ReflectionClass($className);

            // [SEGURANÇA] Evita crash tentando instanciar interfaces, traits ou classes abstratas
            if (!$reflection->isInstantiable()) {
                $response->status(403)->json(['success' => false, 'error' => 'Target class is not instantiable']);
                return $response;
            }

            if (!$reflection->hasMethod($fn)) {
                $response->status(404)->json(['success' => false, 'error' => "RPC function not found"]);
                return $response;
            }

            $method = $reflection->getMethod($fn);

            // [SEGURANÇA] Garante que apenas métodos públicos possam ser acessados de fora
            if (!$method->isPublic()) {
                $response->status(403)->json(['success' => false, 'error' => 'Method is not public']);
                return $response;
            }

            // Verifica se o método tem o atributo #[Expose]
            $attributes = $method->getAttributes(Expose::class);
            if (empty($attributes)) {
                $response->status(403)->json([
                    'success' => false,
                    'error' => "Function is not exposed via RPC."
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

            // [SEGURANÇA] Captura exceções da invocação do método independentemente
            $result = $method->invokeArgs($instance, $args);

            $response->json(['success' => true, 'return' => $result]);
            return $response;

        } catch (Throwable $e) {
            // [SEGURANÇA] Information Disclosure: Impede o vazamento de erros de banco
            // de dados, linhas de código sensíveis ou stacktraces pelo RPC.
            error_log("[RPC Error] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

            $response->status(500)->json(['success' => false, 'error' => 'Internal Server Error']);
            return $response;
        }
    }
}