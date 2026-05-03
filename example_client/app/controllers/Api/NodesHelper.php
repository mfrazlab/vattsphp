<?php

namespace App\controllers\Api;

use Vatts\Router\Request;
use Vatts\Router\Response;

class NodesHelper
{


    public function verifysftp(Request $request, Response $response): Response
    {
        $body = $request->getBody();
        $token = $body["token"] ?? null;
        $username = $body["userName"] ?? null;
        $password = $body["password"] ?? null;
        $serverUuid = $body["serverUuid"] ?? null;


        if(!$token || !$username || !$password || !$serverUuid) {
            return $response->json(['error' => 'missing parameters']);
        }
        $node = \models\Node::getNodeByToken($token);
        if (!$node) {
            return $response->json(['error' => 'unauthorized']);
        }

        $user = \models\User::get("name", $username);
        if (!$user) {
            return $response->json(['error' => 'invalid user']);
        }
        $server = \models\Server::get("serverUuid", $serverUuid);
        if(!$server) {
            return $response->json(['error' => 'invalid server']);
        }

        // verificar senha
        $verify = password_verify($password, $user->password);
        if(!$verify) {
            return $response->json(["error" => "invalid user"]);
        }

        if(!$server->hasPermission($user)) {
            return $response->json(["error" => "invalid user"]);
        }

        return $response->json([
            'success' => true,
            'permission' => $server->hasPermission($user)
        ]);
    }

    public function permission(Request $request, Response $response): Response
    {
        $body = $request->getBody();
        $token = $body["token"] ?? null;
        $userUuid = $body["userUuid"] ?? null;
        $serverUuid = $body["serverUuid"] ?? null;
        if(!$token || !$userUuid || !$serverUuid) {
            return $response->json(['error' => 'missing parameters']);
        }
        $node = \models\Node::getNodeByToken($token);
        if (!$node) {
            return $response->json(['error' => 'unauthorized']);
        }

        $user = \models\User::get("id", $userUuid);
        if (!$user) {
            return $response->json(['error' => 'invalid user']);
        }
        $server = \models\Server::get("serverUuid", $serverUuid);
        if(!$server) {
            return $response->json(['error' => 'invalid server']);
        }

        return $response->json([
            'success' => true,
            'permission' => $server->hasPermission($user)
        ]);
    }

    public function isAdmin(Request $request, Response $response): Response
    {
        error_log("aqui");
        $body = $request->getBody();
        $token = $body["token"];
        $userUuid = $body["userUuid"];

        $node = \models\Node::getNodeByToken($token);
        if (!$node) {
            return $response->json(['error' => 'unauthorized']);
        }

        $user = \models\User::get("id", $userUuid);
        if (!$user) {
            return $response->json(['error' => 'invalid user']);
        }

        return $response->json([
            'success' => true,
            'isAdmin' => $user->isAdmin()
        ]);
    }


}