<?php

namespace App\controllers\Admin;

require_once __DIR__ . '/../../utils/EnvVarUtils.php';

use models\Server;
use models\Node;
use models\Core;
use models\Allocation;
use App\Utils\EnvVarUtils;
use models\User;
use Vatts\Router\Request;
use Vatts\Router\Response;
use Vatts\Database\DB;

class ServerController
{
    /**
     * Salva temporariamente os inputs na sessão para restaurar em caso de erro
     */
    private function flashOldInput(array $data): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_old_input'] = $data;
    }

    /**
     * Recupera os inputs salvos e limpa a sessão
     */
    private function getOldInput(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $old = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);
        return is_array($old) ? $old : [];
    }

    private function getFreeAllocationForNodeById(string $nodeId, int $allocationId): ?Allocation
    {
        $pdo = DB::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM `allocations` WHERE `id` = :id AND `nodeId` = :nodeId AND (`assignedTo` IS NULL OR `assignedTo` = '') LIMIT 1");
        $stmt->execute([
            'id' => $allocationId,
            'nodeId' => $nodeId,
        ]);
        $row = $stmt->fetch();
        return $row ? new Allocation($row) : null;
    }

    private function getNode(string $id): ?Node
    {
        return Node::find($id);
    }

    private function getCore(string $id): ?Core
    {
        return Core::find($id);
    }

    private function getViewData(Request $request, string $title, array $extraParams = []): array
    {
        $baseData = [
            'title'         => $title,
            'page_category' => 'admin',
            'page_name'     => 'servers',
            'user'          => $request->getParsed('user'),
            'backTo'        => '/servers',
        ];

        return array_merge($baseData, $extraParams);
    }

    // Recebe o array $old para preencher os valores padrão caso a página recarregue com erro
    private function getCreateMap(array $old = []): array
    {
        return [
            'Informações Básicas' => [
                ['label' => 'Nome do Servidor', 'key' => 'name', 'type' => 'text', 'desc' => 'Nome de exibição do servidor.', 'required' => true, 'default' => $old['name'] ?? ''],
                ['label' => 'Descrição', 'key' => 'description', 'type' => 'textarea', 'desc' => 'Breve descrição do servidor.', 'default' => $old['description'] ?? ''],
            ],
            'Recursos' => [
                ['label' => 'Memória RAM (MB)', 'key' => 'ram', 'type' => 'number', 'desc' => 'Quantidade de RAM em MB.', 'default' => $old['ram'] ?? 1024],
                ['label' => 'CPU (%)', 'key' => 'cpu', 'type' => 'number', 'desc' => 'Porcentagem de CPU.', 'default' => $old['cpu'] ?? 10],
                ['label' => 'Disco (MB)', 'key' => 'disk', 'type' => 'number', 'desc' => 'Espaço em disco em MB.', 'default' => $old['disk'] ?? 2048],
            ],
        ];
    }

    private static function generateUuid(): string
    {
        // simples UUID v4 gerado em PHP
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function viewCreate(Request $request, Response $response): Response
    {
        // Resgata os inputs antigos, caso existam
        $old = $this->getOldInput();

        // Tenta recuperar o ownerUser caso ele já tenha sido preenchido
        $ownerUser = null;
        if (!empty($old['ownerId'])) {
            $ownerUser = User::find($old['ownerId']);
        }

        return $response->view('resources.edit_create', $this->getViewData($request, 'Servidor', [
            'map' => $this->getCreateMap($old), // Envia os inputs antigos pros campos genéricos
            'isBelow' => true,
            'old' => $old, // Disponibiliza a variável $old na view para cards customizados
            'ownerUser' => $ownerUser,
            'custom_cards' => [
                'partials.server.cards.allocation',
                'partials.server.cards.core_docker',
                'partials.server.cards.startup',
                'partials.server.cards.env',
            ],
        ]));
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getBody();

        $name = trim((string)($body['name'] ?? ''));
        $ownerId = trim((string)($body['ownerId'] ?? ''));
        $nodeId = trim((string)($body['nodeId'] ?? ''));
        $coreId = trim((string)($body['coreId'] ?? ''));
        $dockerImage = trim((string)($body['dockerImage'] ?? ''));
        $startupCommand = (string)($body['startupCommand'] ?? '');
        $envVars = isset($body['env']) && is_array($body['env']) ? $body['env'] : [];
        $allocationId = (int) ($body['allocationId'] ?? 0);

        if ($name === '' || $ownerId === '' || $nodeId === '' || $coreId === '') {
            $this->flashOldInput($body);
            return $response->setFlash(['error' => 'Campos obrigatórios ausentes (name, ownerId, nodeId, coreId)'])->redirect('/admin/servers/create');
        }

        if ($allocationId <= 0) {
            $this->flashOldInput($body);
            return $response->setFlash(['error' => 'Selecione uma allocation (porta) para o servidor'])->redirect('/admin/servers/create');
        }

        $node = $this->getNode($nodeId);
        if (!$node) {
            $this->flashOldInput($body);
            return $response->setFlash(['error' => 'Node não encontrado'])->redirect('/admin/servers/create');
        }

        $core = $this->getCore($coreId);
        if (!$core) {
            $this->flashOldInput($body);
            return $response->setFlash(['error' => 'Core não encontrado'])->redirect('/admin/servers/create');
        }

        // aplica defaults e valida as env vars conforme regras do core
        try {
            $envVars = EnvVarUtils::validateAndApplyDefaults($core->getVariables(), $envVars);
        } catch (\Throwable $e) {
            $this->flashOldInput($body);
            return $response->setFlash(['error' => $e->getMessage()])->redirect('/admin/servers/create');
        }

        // allocation escolhida pelo usuário (precisa estar livre e pertencer ao node)
        $allocation = $this->getFreeAllocationForNodeById((string) $node->id, $allocationId);
        if (!$allocation) {
            $this->flashOldInput($body);
            return $response->setFlash(['error' => 'Allocation inválida ou já está em uso'])->redirect('/admin/servers/create');
        }

        // Gera um server UUID local e usa como serverId para enviar ao node
        $serverUuid = self::generateUuid();

        $url = $node->getUrl() . '/api/v1/servers/create';

        $payload = json_encode([
            'token' => $node->token,
            'serverId' => $serverUuid,
            'userUuid' => $ownerId,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($payload)]);

        // CORREÇÃO: Aumentado o tempo de timeout para 60 segundos
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        if ($node->ssl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $resp = curl_exec($ch);
        $curlError = curl_error($ch); // Captura a mensagem de erro exata do cURL
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($resp === false) {
            // Agora exibirá o erro real, como "Operation timed out after 10000 milliseconds"
            $this->flashOldInput($body);
            return $response->setFlash(['error' => "Erro ao conectar com o node: {$curlError}"] )->redirect('/admin/servers/create');
        }

        $data = json_decode($resp, true);

        if ($httpCode !== 200 || ($data !== null && isset($data['error']))) {
            $msg = $data['error'] ?? 'Erro desconhecido ao criar servidor no node';
            $this->flashOldInput($body);
            return $response->setFlash(['error' => "Node API: {$msg}"])->redirect('/admin/servers/create');
        }

        // Se chegou aqui é sucesso conforme espec. Agora salva no banco
        $server = new Server();
        $server->name = $name;
        $server->description = $body['description'] ?? '';
        $server->ownerId = $ownerId;
        $server->ram = (int)($body['ram'] ?? 1024);
        $server->cpu = (int)($body['cpu'] ?? 10);
        $server->disk = (int)($body['disk'] ?? 2048);
        $server->coreId = $coreId;
        // Armazena referência ao node (usando id para simplicidade)
        $server->nodeUuid = (string)$node->id;
        $server->dockerImage = $dockerImage !== '' ? $dockerImage : null;
        $server->startupCommand = trim($startupCommand) !== '' ? trim($startupCommand) : ($core->startupCommand ?? null);
        $server->envVars = !empty($envVars) ? json_encode($envVars, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null;
        $server->serverUuid = $serverUuid;

        // salva a allocation escolhida
        $server->allocationId = $allocation->id ?? null;

        $server->save();

        // marca allocation como atribuída ao server
        try {
            $allocation->assignedTo = (string) $server->id;
            $allocation->save();
        } catch (\Throwable $e) {
            // não bloqueia o fluxo, mas deixa rastreável
        }

        return $response->setFlash(['success' => 'Servidor criado com sucesso e requisitado ao node'])->redirect("/admin/servers/{$server->id}/edit");
    }

    public function viewAll(Request $request, Response $response): Response
    {
        $servers = Server::all();

        $map = [
            ['label' => 'ID', 'key' => 'id', 'type' => 'text'],
            ['label' => 'Nome', 'key' => 'name', 'type' => 'text'],
            ['label' => 'Owner', 'key' => 'ownerId', 'type' => 'text'],
        ];

        $viewData = [
            'resources' => $servers,
            'map' => $map,
            'see' => 'servers/[id]/edit',
            'create' => 'servers/create',
            'delete' => 'servers/[id]/delete?return=all',
        ];

        return $response->view('resources.view_resources', $this->getViewData($request, 'Servidores', $viewData));
    }

    private function getServer(string $id): ?Server
    {
        return Server::find($id);
    }

    public function viewEdit(Request $request, Response $response): Response
    {
        $server = $this->getServer($request->getParam('server'));
        if (!$server) {
            return $response->view('resources.resource_not_found', ['title' => 'server']);
        }

        // Recupera os dados velhos, se houver falha
        $old = $this->getOldInput();
        if (!empty($old)) {
            // Se falhou, sobrescreve os dados do $server apenas na memória para renderizar o erro com o dado que o usuário digitou
            foreach ($old as $key => $val) {
                if ($key === 'env') {
                    $server->envVars = is_array($val) ? json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $val;
                } else {
                    $server->{$key} = $val;
                }
            }
        }

        $ownerUser = User::get('id', $server->ownerId);
        $viewData = [
            'ownerUser' => $ownerUser,
            'resource' => $server,
            'map' => $server->view_map,
            'canDelete' => true,
            'deleteUrl' => 'servers/[id]/delete?return=edit',
            // Ao editar, o startup e env estarão inclusos dinamicamente dentro do core_docker
            'custom_tabs' => [
                ['view' => 'partials.server.cards.allocation', 'label' => 'Alocação'],
                ['view' => 'partials.server.cards.core_docker', 'label' => 'Core, Docker & Startup'],
            ],
        ];

        return $response->view('resources.edit_create', $this->getViewData($request, "Servidor - {$server->name}", $viewData));
    }

    public function edit(Request $request, Response $response): Response
    {
        $server = $this->getServer($request->getParam('server'));
        if (!$server) {
            return $response->view('resources.resource_not_found', ['title' => 'server']);
        }

        $body = $request->getBody();
        $server->name = $body['name'] ?? $server->name;
        $server->description = $body['description'] ?? $server->description;

        if (isset($body['ownerId'])) $server->ownerId = (string)$body['ownerId'];
        // NODE ID FOI REMOVIDO DAQUI PARA NÃO MUDAR EM EDIÇÕES (o front end já estará visualmente bloqueado tbm)

        if (isset($body['coreId'])) $server->coreId = (string)$body['coreId'];
        if (isset($body['dockerImage'])) $server->dockerImage = (string)$body['dockerImage'];

        // Handling Allocation changes
        $newAllocationId = isset($body['allocationId']) ? (int) $body['allocationId'] : 0;

        if ($newAllocationId > 0 && $newAllocationId != $server->allocationId) {
            // Check if it's free (Usa o nodeUuid antigo com segurança, pq não alteramos acima)
            $allocation = $this->getFreeAllocationForNodeById((string) $server->nodeUuid, $newAllocationId);
            if (!$allocation) {
                $this->flashOldInput($body);
                return $response->setFlash(['error' => 'A allocation selecionada é inválida ou já está em uso'])->redirect("/admin/servers/{$server->id}/edit");
            }

            // Release old allocation
            if ($server->allocationId) {
                try {
                    $oldAlloc = Allocation::find($server->allocationId);
                    if ($oldAlloc) {
                        $oldAlloc->assignedTo = null;
                        $oldAlloc->save();
                    }
                } catch (\Throwable $e) {}
            }

            // Assign new allocation
            $server->allocationId = $allocation->id;
            try {
                $allocation->assignedTo = (string) $server->id;
                $allocation->save();
            } catch (\Throwable $e) {}
        }


        $core = $this->getCore((string)$server->coreId);
        if (!$core) {
            $this->flashOldInput($body);
            return $response->setFlash(['error' => 'Core não encontrado'])->redirect("/admin/servers/{$server->id}/edit");
        }

        // startupCommand pode ser custom, mas se vier vazio volta pro default do core
        if (isset($body['startupCommand'])) {
            $server->startupCommand = trim((string)$body['startupCommand']);
        }
        if ($server->startupCommand === null || trim((string)$server->startupCommand) === '') {
            $server->startupCommand = $core->startupCommand ?? null;
        }

        // stopCommand não é personalizável: sempre vem do core selecionado
        $server->stopCommand = $core->stopCommand ?? null;

        if (isset($body['env']) && is_array($body['env'])) {
            try {
                $env = EnvVarUtils::validateAndApplyDefaults($core->getVariables(), $body['env']);
                $server->envVars = !empty($env)
                    ? json_encode($env, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                    : null;
            } catch (\Throwable $e) {
                $this->flashOldInput($body);
                return $response->setFlash(['error' => $e->getMessage()])->redirect("/admin/servers/{$server->id}/edit");
            }
        }
        $server->ram = isset($body['ram']) ? (int)$body['ram'] : $server->ram;
        $server->cpu = isset($body['cpu']) ? (int)$body['cpu'] : $server->cpu;
        $server->disk = isset($body['disk']) ? (int)$body['disk'] : $server->disk;
        $server->save();

        return $response->setFlash(['success' => 'Servidor atualizado'])->redirect("/admin/servers/{$server->id}/edit");
    }

    public function delete(Request $request, Response $response): Response
    {
        $server = $this->getServer($request->getParam('server'));
        if (!$server) return $response->setFlash(['error'=>'Server não encontrado'])->redirect('/admin/servers');

        // libera allocations atribuídas a esse server
        try {
            $allocs = Allocation::where(['assignedTo' => (string) $server->id]);
            foreach ($allocs as $a) {
                $a->assignedTo = null;
                $a->save();
            }
        } catch (\Throwable $e) {}

        $server->delete();
        return $response->setFlash(['success'=>'Servidor excluído'])->redirect('/admin/servers');
    }

    // --- API helpers used by partials ---
    public function apiUsersSearch(Request $request, Response $response): Response
    {
        $q = trim((string) ($request->getQuery()['email'] ?? ''));
        if ($q === '') return $response->json([]);

        $pdo = \Vatts\Database\DB::getPdo();
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` LIKE :q LIMIT 20");
        $stmt->execute(['q' => "%{$q}%"]);
        $rows = $stmt->fetchAll();

        $out = array_map(function($r){
            return [
                'id' => $r['id'] ?? null,
                'uuid' => $r['uuid'] ?? ($r['id'] ?? null),
                'email' => $r['email'] ?? null,
                'name' => $r['name'] ?? null,
            ];
        }, $rows);

        return $response->json($out);
    }

    public function apiNodesList(Request $request, Response $response): Response
    {
        $nodes = Node::all();
        $out = [];
        foreach ($nodes as $n) {
            $status = false;
            try { $st = $n->getStatus(); $status = (bool)$st; } catch (\Throwable $e) { $status = false; }
            $out[] = [
                'id' => $n->id ?? null,
                'name' => $n->name ?? null,
                'ip' => $n->ip ?? null,
                'port' => $n->port ?? null,
                'ssl' => $n->ssl ?? false,
                'online' => $status,
                'location' => $n->location ?? null,
                'token' => $n->token ?? null,
            ];
        }
        return $response->json($out);
    }

    public function apiCoresList(Request $request, Response $response): Response
    {
        $cores = Core::all();
        $out = array_map(function($c){
            return [
                'id' => $c->id ?? null,
                'name' => $c->name ?? null,
                'description' => $c->description ?? null,
                'dockerImages' => method_exists($c, 'getDockerImages') ? $c->getDockerImages() : json_decode($c->dockerImages ?? '[]', true),
                'startupCommand' => $c->startupCommand ?? '',
                'stopCommand' => $c->stopCommand ?? '',
                'variables' => method_exists($c, 'getVariables') ? $c->getVariables() : json_decode($c->variables ?? '[]', true),
            ];
        }, $cores);
        return $response->json($out);
    }

    public function apiAllocationsList(Request $request, Response $response): Response
    {
        $nodeId = trim((string) ($request->getQuery()['nodeId'] ?? ''));
        if ($nodeId === '') {
            return $response->json([]);
        }

        $serverId = trim((string) ($request->getQuery()['serverId'] ?? ''));

        $pdo = DB::getPdo();

        // Return allocations that are free, OR are assigned to this specific server id
        $query = "SELECT `id`, `ip`, `externalIp`, `port`, `assignedTo` FROM `allocations` WHERE `nodeId` = :nodeId AND (`assignedTo` IS NULL OR `assignedTo` = ''";
        $params = ['nodeId' => $nodeId];

        if ($serverId !== '') {
            $query .= " OR `assignedTo` = :serverId";
            $params['serverId'] = $serverId;
        }

        $query .= ") ORDER BY `port` ASC LIMIT 500";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $out = array_map(function ($r) use ($serverId) {
            return [
                'id' => $r['id'] ?? null,
                'ip' => $r['ip'] ?? null,
                'externalIp' => $r['externalIp'] ?? null,
                'port' => $r['port'] ?? null,
                'isAssignedToMe' => ($serverId !== '' && ($r['assignedTo'] ?? '') === $serverId),
            ];
        }, $rows);

        return $response->json($out);
    }
}