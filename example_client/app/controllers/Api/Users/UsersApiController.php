<?php

namespace App\controllers\Api\Users;

use models\Core;
use models\User;
use models\Allocation;
use Vatts\Database\DB;
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

    public static function getAdditionalAllocations(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Invalid server.'
            ])->status(400);
        }

        $payload = self::buildAdditionalAllocationsPayload($server);
        return $response->json($payload);
    }

    public static function addAdditionalAllocation(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Invalid server.'
            ])->status(400);
        }

        $allocationId = (int) ($request->getBody()['allocationId'] ?? 0);

        $map = $server->getAdditionalAllocationsMap();
        $allIds = array_values(array_unique(array_merge($map['FIXED'], $map['BYUSER'])));
        $maxAdditional = isset($server->maxAdditionalAllocations) ? (int) $server->maxAdditionalAllocations : null;
        $currentUserAdds = count($map['BYUSER']);

        if ($maxAdditional !== null && $maxAdditional >= 0 && $currentUserAdds >= $maxAdditional) {
            return $response->json(['error' => 'Limite de alocações adicionais do usuário atingido.'])->status(403);
        }

        if ($allocationId <= 0) {
            $allocationId = self::pickRandomAvailableAllocation((string) $server->nodeUuid, $allIds);
            if ($allocationId <= 0) {
                return $response->json(['error' => 'Sem portas disponiveis no node.'])->status(404);
            }
        }

        if (in_array($allocationId, $allIds, true)) {
            return $response->json(['error' => 'Allocation já adicionada.'])->status(409);
        }

        $allocation = Allocation::find($allocationId);
        if (!$allocation || (string) $allocation->nodeId !== (string) $server->nodeUuid) {
            return $response->json(['error' => 'Allocation não pertence ao node do servidor.'])->status(400);
        }

        if (!empty($allocation->assignedTo)) {
            return $response->json(['error' => 'Allocation já está em uso.'])->status(409);
        }

        $allocation->assignedTo = (string) $server->id;
        $allocation->save();

        $map['BYUSER'][] = $allocationId;
        $map['BYUSER'] = array_values(array_unique($map['BYUSER']));

        $server->additionalAllocations = json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $server->save();

        return $response->json(self::buildAdditionalAllocationsPayload($server));
    }

    private static function pickRandomAvailableAllocation(string $nodeId, array $excludeIds = []): int
    {
        $pdo = DB::getPdo();
        $params = [$nodeId];
        $excludeClause = '';

        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
            $excludeClause = " AND `id` NOT IN ({$placeholders})";
            $params = array_merge($params, $excludeIds);
        }

        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $randomFn = $driver === 'sqlite' ? 'RANDOM()' : 'RAND()';

        $stmt = $pdo->prepare(
            "SELECT `id` FROM `allocations` WHERE `nodeId` = ? AND (`assignedTo` IS NULL OR `assignedTo` = ''){$excludeClause} ORDER BY {$randomFn} LIMIT 1"
        );
        $stmt->execute($params);
        $id = (int) $stmt->fetchColumn();
        return $id > 0 ? $id : 0;
    }

    public static function removeAdditionalAllocation(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Invalid server.'
            ])->status(400);
        }

        $allocationId = (int) ($request->getBody()['allocationId'] ?? 0);
        if ($allocationId <= 0) {
            return $response->json(['error' => 'Allocation inválida.'])->status(400);
        }

        $map = $server->getAdditionalAllocationsMap();
        if (in_array($allocationId, $map['FIXED'], true)) {
            return $response->json(['error' => 'Allocation fixa não pode ser removida.'])->status(403);
        }

        if (!in_array($allocationId, $map['BYUSER'], true)) {
            return $response->json(['error' => 'Allocation não encontrada na lista.'])->status(404);
        }

        $allocation = Allocation::find($allocationId);
        if ($allocation && (string) $allocation->assignedTo === (string) $server->id) {
            $allocation->assignedTo = null;
            $allocation->save();
        }

        $map['BYUSER'] = array_values(array_diff($map['BYUSER'], [$allocationId]));
        $server->additionalAllocations = json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $server->save();

        return $response->json(self::buildAdditionalAllocationsPayload($server));
    }

    private static function buildAdditionalAllocationsPayload(\models\Server $server): array
    {
        $map = $server->getAdditionalAllocationsMap();
        $ids = array_values(array_unique(array_merge($map['FIXED'], $map['BYUSER'])));
        $allocationsById = self::fetchAllocationsByIds($ids);

        $additional = [];
        foreach ($map['FIXED'] as $id) {
            if (isset($allocationsById[$id])) {
                $additional[] = $allocationsById[$id] + ['type' => 'FIXED'];
            }
        }
        foreach ($map['BYUSER'] as $id) {
            if (isset($allocationsById[$id])) {
                $additional[] = $allocationsById[$id] + ['type' => 'BYUSER'];
            }
        }

        return [
            'additionalAllocations' => $additional,
            'availableAllocations' => self::fetchAvailableAllocations((string) $server->nodeUuid),
        ];
    }

    private static function fetchAllocationsByIds(array $ids): array
    {
        if (empty($ids)) return [];

        $pdo = DB::getPdo();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT `id`, `nodeId`, `ip`, `externalIp`, `port`, `assignedTo` FROM `allocations` WHERE `id` IN ({$placeholders})");
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) continue;
            $out[$id] = [
                'id' => $id,
                'nodeId' => $row['nodeId'] ?? null,
                'ip' => $row['ip'] ?? null,
                'externalIp' => $row['externalIp'] ?? null,
                'port' => (int) ($row['port'] ?? 0),
            ];
        }

        return $out;
    }

    private static function fetchAvailableAllocations(string $nodeId): array
    {
        $pdo = DB::getPdo();
        $stmt = $pdo->prepare("SELECT `id`, `nodeId`, `ip`, `externalIp`, `port` FROM `allocations` WHERE `nodeId` = :nodeId AND (`assignedTo` IS NULL OR `assignedTo` = '') ORDER BY `port` ASC LIMIT 500");
        $stmt->execute(['nodeId' => $nodeId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'nodeId' => $row['nodeId'] ?? null,
                'ip' => $row['ip'] ?? null,
                'externalIp' => $row['externalIp'] ?? null,
                'port' => (int) ($row['port'] ?? 0),
            ];
        }, $rows);
    }

}
