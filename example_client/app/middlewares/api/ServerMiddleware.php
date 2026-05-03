<?php

namespace App\middlewares\api;

use models\Server;
use models\User;
use Vatts\Utils\Middleware;
use Vatts\Router\Request;
use Vatts\Router\Response;

class ServerMiddleware extends Middleware
{
    public static string $name = 'server';

    public function handle(Request $request, Response $response): Request
    {
        $user = $request->getParsed('user');

        if(!($user instanceof User)) {
            $this->sendError(401, 'Unauthorized.');
        }

        $serverId = $request->getBody()['server_id'] ?? $request->getParam('server_id') ?? $request->getQuery()['server_id'] ?? null;

        if (!$serverId) {
            $this->sendError(400, 'Server ID is required.');
        }

        $server = Server::get('id', $serverId) ?? Server::getByShortUuid($serverId) ?? null;

        if (!$server) {
            $this->sendError(404, 'Server not found.');
        }

        // Corrigido: Bloqueia se o usuário NÃO tiver permissão
        if(!$server->hasPermission($user)) {
            $this->sendError(403, 'Forbidden. You do not have access to this server.');
        }

        $request->setParsed('server', $server);

        return $request;
    }

    /**
     * Helper nativo para interromper a execução e retornar JSON
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => $message
        ]);
        exit;
    }
}