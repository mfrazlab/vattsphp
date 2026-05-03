<?php

namespace App\controllers\Api\Users;

use models\Core;
use models\User;
use Vatts\Router\Request;
use Vatts\Router\Response;

class UsersApiController
{




    public static function saveNameAndDesc(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Servidor inválido.'
            ])->status(400);
        }

        $body = $request->getBody();
        $name = trim($body["name"] ?? '');
        $desc = trim($body["desc"] ?? '');
        $group = trim($body["group"] ?? '');
        $group = $group === '' ? null : $group;

        // ===== VALIDAÇÃO DO NOME =====
        if ($name === '') {
            return $response->json(['error' => 'O nome é obrigatório.'])->status(400);
        }

        if (mb_strlen($name) > 20) {
            return $response->json(['error' => 'O nome deve ter no máximo 20 caracteres.'])->status(400);
        }

        if (!preg_match('/^[a-zA-Z0-9 |\-]+$/', $name)) {
            return $response->json([
                'error' => 'O nome só pode conter letras, números, espaços, "|" e "-".'
            ])->status(400);
        }

        // ===== VALIDAÇÃO DA DESCRIÇÃO =====
        if (mb_strlen($desc) > 200) {
            return $response->json([
                'error' => 'A descrição deve ter no máximo 200 caracteres.'
            ])->status(400);
        }

        if ($desc !== '' && preg_match('/^\s+$/', $desc)) {
            return $response->json([
                'error' => 'A descrição não pode conter apenas espaços.'
            ])->status(400);
        }

        // ===== VALIDAÇÃO DO GROUP =====
        if ($group !== null) {
            if (mb_strlen($group) > 12) {
                return $response->json([
                    'error' => 'O grupo deve ter no máximo 12 caracteres.'
                ])->status(400);
            }

            if (!preg_match('/^[A-Za-zÀ-ÿ\s]+$/', $group)) {
                return $response->json([
                    'error' => 'O grupo só pode conter letras e espaços.'
                ])->status(400);
            }

            if (preg_match('/^\s+$/', $group)) {
                return $response->json([
                    'error' => 'O grupo não pode conter apenas espaços.'
                ])->status(400);
            }
        }

        // ===== SALVAR =====
        $server->name = $name;
        $server->description = $desc;
        $server->group = $group ?? ''; // <-- aqui
        $server->save();

        return $response->json([
            "success" => true
        ]);
    }


    public static function getServer(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Invalid server.'
            ])->status(400);
        }
        unset($server->view_map);
        $allocation = $server->getFirstAllocation();
        unset($allocation->view_map);
        $node = $server->getNode();
        return $response->json([
            'server' => $server,
            'nodeUrl' => $node->getUrl(),
            'nodeIp' => $node->ip,
            'nodeSftp' => $node->sftp,
            'allocation' => $allocation
        ]);
    }

    public static function sendAction(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Invalid server.'
            ])->status(400);
        }
        $action = $request->getBody()['action'] ?? null;
        if (!in_array($action, ['start', 'restart', 'stop', 'kill', 'command', 'install'])) {
            return $response->json([
                'error' => 'Invalid action. Allowed actions are: start, restart, stop, kill, command, install.'
            ])->status(400);
        }
        $result = $server->sendAction($action, $request->getParsed('user')->id);
        if ($result === false) {
            return $response->json([
                'error' => 'Failed to send action. Node might be offline or unreachable.'
            ])->status(503);
        }
        return $response->json([
            'success' => true,
            'result' => $result
        ]);
    }

    public static function getStatus(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Invalid server.'
            ])->status(400);
        }

        $status = $server->getStatus();
        if ($status === false) {
            return $response->json([
                'error' => 'Node is offline or unreachable.'
            ])->status(503);
        }

        return $response->json([
            'status' => $status
        ]);
    }


    public static function getServers(Request $request, Response $response): Response
    {
        $user = $request->getParsed('user');
        if ($user instanceof User) {
            $servers = array_map(function ($server) {
                unset($server->view_map);
                $allocation = $server->getFirstAllocation()->toArray();
                unset($allocation["view_map"]);
                $server->allocation = $allocation;
                return $server;
            }, $user->getServers());
            $type = $request->getQuery()['type'] ?? 'not_set';



            if($user->isAdmin() && $type === 'others') {
                $servers = $user->getOthersServers();
            }

            return $response->json([
                'servers' => $servers
            ]);
        }
        return $response->json([
            'error' => 'Invalid user.'
        ], 501);
    }

}