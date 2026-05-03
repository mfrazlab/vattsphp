<?php

namespace App\middlewares;

use models\User;
use Vatts\Utils\Middleware;
use Vatts\Router\Request;
use Vatts\Router\Response;

class ApiMiddleware extends Middleware
{
    public static string $name = 'api';

    public function handle(Request $request, Response $response): Request
    {
        $auth = require __DIR__ . '/../Auth.php';
        $user = null;

        // 1. Tenta pegar pela Sessão (Cookie/Navegador)
        $session = $auth->getSession();

        if ($session !== null) {
            $user = User::get('id', $session['id']);
        }

        // 2. Se não tem sessão, tenta pelo Bearer Token
        if (!$user) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

            if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];

            }
        }

        // 3. Se após as duas tentativas continuar nulo, barra a requisição
        if (!$user) {
            $response->json([
                'error' => true,
                'message' => 'Unauthorized.'
            ], 401);
            exit; // Interrompe a execução para não seguir para o Controller
        }

        // Define o usuário no request para usar no Controller
        $request->setParsed('user', $user);

        return $request;
    }
}