<?php

namespace App\controllers\Admin;

use models\Node;
use models\Allocation;
use Vatts\Router\Request;
use Vatts\Router\Response;

class NodesController
{
    /**
     * Busca o node pelo ID
     */
    private function getNode(string $id): ?Node
    {
        return Node::get('id', $id);
    }

    /**
     * Verifica se o node pode ser deletado.
     */
    private function canDelete(?Node $node, $currentUser): bool
    {
        if (!$node) return false;
        // placeholder para regras de negócio
        return true;
    }

    /**
     * Dados base para as views geradas por esta controller
     */
    private function getViewData(Request $request, string $title, array $extraParams = []): array
    {
        $baseData = [
            'title'         => $title,
            'page_category' => 'admin',
            'page_name'     => 'nodes_list',
            'user'          => $request->getParsed('user'),
            'backTo'        => '/nodes',
        ];

        return array_merge($baseData, $extraParams);
    }

    /**
     * Validação básica dos campos do Node
     */
    private function validateNodeData(array $body): ?string
    {
        $name = $body['name'] ?? null;
        $ip = $body['ip'] ?? null;

        if (!$name) return 'O nome do node é obrigatório.';
        if (!$ip) return 'O endereço IP / FQDN do node é obrigatório.';

        if (!empty($body['port']) && !is_numeric($body['port'])) return 'A porta do daemon deve ser numérica.';
        if (!empty($body['sftp']) && !is_numeric($body['sftp'])) return 'A porta SFTP deve ser numérica.';

        return null;
    }

    // ======================================================================
    // ACTIONS
    // ======================================================================

    public function viewAll(Request $request, Response $response): Response
    {
        $nodes = Node::all();
        $map = [
            ['label' => 'Identificador', 'key' => 'id', 'type' => 'text'],
            ['label' => 'Nome do Node', 'key' => 'name', 'type' => 'text'],
            ['label' => 'Localização', 'key' => 'location', 'type' => 'text'],
            ['label' => 'Porta SFTP', 'key' => 'sftp', 'type' => 'text'],
            ['label' => 'SSL', 'key' => 'ssl', 'type' => 'text'],
            ['label' => 'Status', 'key' => 'id', 'type' => 'custom', 'template' => 'partials.node.status_cell'],
        ];

        $viewData = [
            'resources'     => $nodes,
            'map'           => $map,
            'see'           => 'nodes/[id]/edit',
            'create'        => 'nodes/create',
            'delete'        => 'nodes/[id]/delete?return=all',
            'extra_scripts' => ['partials.node.status_script']
        ];

        return $response->view('resources.view_resources', $this->getViewData($request, 'Nodes', $viewData));
    }

    /**
     * Endpoint para buscar o status das nodes via AJAX
     * Retorna informações detalhadas (RAM, CPU, OS, Uptime)
     */
    public function getStatus(Request $request, Response $response)
    {
        $nodes = Node::all();
        $statuses = [];

        foreach ($nodes as $node) {
            $info = $node->getStatus();

            if ($info && isset($info['status']) && $info['status'] === 'success') {
                $statuses[$node->id] = [
                    'status' => 'Online',
                    'ram'    => $info['ram'] ?? '0%',
                    'cpu'    => $info['cpu'] ?? '0%',
                    'os'     => $info['os'] ?? 'Linux',
                    'uptime' => $info['uptime'] ?? '0h 0m'
                ];
            } else {
                $statuses[$node->id] = [
                    'status' => 'Offline',
                    'ram'    => '--',
                    'cpu'    => '--',
                    'os'     => '--',
                    'uptime' => '--'
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($statuses);
        exit;
    }

    public function viewEdit(Request $request, Response $response): Response
    {
        $node = $this->getNode($request->getParam('user') ?? $request->getParam('node'));

        if (!$node) {
            return $response->view('resources.resource_not_found', ['title' => 'node']);
        }

        // Buscar as alocações deste node
        $allocations = Allocation::where('nodeId', $node->id) ?? [];

        $viewData = [
            'resource'  => $node,
            'map'       => $node->view_map,
            'canDelete' => $this->canDelete($node, $request->getParsed('user')),
            'deleteUrl' => 'nodes/[id]/delete?return=edit',
            'custom_cards'  => [
                'partials.node.status_card',
            ],
            'custom_tabs' => [
                [
                    'label' => 'Configuração',
                    'view'  => 'partials.node.custom_tab.config_card',
                ],
                [
                    'label' => 'Alocações',
                    'key'   => 'tab-allocations',
                    'view'  => 'partials.node.custom_tab.allocations_tab',
                    'data'  => [
                        'node'        => $node,
                        'allocations' => $allocations
                    ]
                ],
            ],
            'extra_scripts' => [
                'partials.node.status_script'
            ]
        ];

        return $response->view('resources.edit_create', $this->getViewData($request, "Node - {$node->name}", $viewData));
    }

    public function edit(Request $request, Response $response): Response
    {
        $node = $this->getNode($request->getParam('user') ?? $request->getParam('node'));

        if (!$node) {
            return $response->view('resources.resource_not_found', ['title' => 'node']);
        }

        $body = $request->getBody();
        $error = $this->validateNodeData($body);

        if ($error) {
            return $response->setFlash(['error' => $error])
                ->redirect("/admin/nodes/{$node->id}/edit");
        }

        $node->name = $body['name'];
        $node->ip = $body['ip'] ?? '';
        $node->port = $body['port'] ?? '';
        $node->sftp = $body['sftp'] ?? '';
        $node->ssl = $body['ssl'] ?? '';
        $node->location = $body['location'] ?? '';
        $node->save();

        return $response->setFlash(['success' => 'O node foi atualizado com sucesso!'])
            ->redirect("/admin/nodes/{$node->id}/edit");
    }

    public function viewCreate(Request $request, Response $response): Response
    {
        $node = new Node();
        $map = $node->view_map;

        return $response->view('resources.edit_create', $this->getViewData($request, 'Node', [
            'map'       => $map,
            'see'       => 'nodes/[id]/edit',
            'canDelete' => false,
            'deleteUrl' => 'nodes/[id]/delete?return=edit'
        ]));
    }
    function uuidv4() {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versão 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getBody();
        $error = $this->validateNodeData($body);

        $node = new Node();

        if ($error) {
            return $response->setFlash(['error' => $error])
                ->redirect("/admin/nodes/create");
        }

        $node->name = $body['name'];
        $node->ip = $body['ip'] ?? '';
        $node->port = $body['port'] ?? '';
        $node->sftp = $body['sftp'] ?? '';
        $node->ssl = $body['ssl'] ?? '';
        $node->token = $this->uuidv4();//gerar token
        $node->location = $body['location'] ?? '';
        $node->save();

        return $response->setFlash(['success' => 'O node foi criado com sucesso!'])
            ->redirect("/admin/nodes/{$node->id}/edit");
    }

    public function delete(Request $request, Response $response): Response
    {
        $nodeId = $request->getParam('user') ?? $request->getParam('node');
        $node = $this->getNode($nodeId);

        $baseRoute = '/admin/nodes';

        if (!$node) {
            return $response->setFlash(['error' => 'Node não encontrado'])->redirect($baseRoute);
        }

        if (!$this->canDelete($node, $request->getParsed('user'))) {
            $returnTo = $request->getQuery()['return'] ?? 'edit';
            $errorMsg = 'Não é possível excluir este node.';

            if ($returnTo === 'all') {
                $redirect = $baseRoute;
            } else {
                $redirect = "{$baseRoute}/{$nodeId}/edit";
            }

            return $response->setFlash(['error' => $errorMsg])->redirect($redirect);
        }

        $node->delete();

        return $response->setFlash(['success' => 'O node foi excluído com sucesso!'])->redirect($baseRoute);
    }

    // ======================================================================
    // ACTIONS DE ALOCAÇÃO
    // ======================================================================

    public function createAllocation(Request $request, Response $response): Response
    {
        $nodeId = $request->getParam('node');
        $body = $request->getBody();

        if (empty($body['allocation_ip']) || empty($body['allocation_ports'])) {
            return $response->setFlash(['error' => 'O IP e as Portas são obrigatórios.'])
                ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
        }

        $portsInput = $body['allocation_ports'];
        $portsToCreate = [];
        // Quebra as portas pelas vírgulas
        $parts = explode(',', $portsInput);

        foreach ($parts as $part) {
            $part = trim($part);
            if (str_contains($part, '-')) {
                // Lógica para intervalos (ex: 25565-25575)
                $range = explode('-', $part, 2);
                $start = (int)trim($range[0]);
                $end = (int)trim($range[1]);

                if ($start > 0 && $end > 0 && $start <= $end) {
                    if (($end - $start) > 1000) {
                        return $response->setFlash(['error' => 'O intervalo máximo permitido é de 1000 portas de uma vez.'])
                            ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
                    }
                    for ($i = $start; $i <= $end; $i++) {
                        $portsToCreate[] = $i;
                    }
                }
            } elseif (is_numeric($part)) {
                // Porta única
                $portsToCreate[] = (int)$part;
            }
        }

        // Remove duplicatas
        $portsToCreate = array_unique($portsToCreate);

        if (empty($portsToCreate)) {
            return $response->setFlash(['error' => 'Nenhuma porta válida foi informada.'])
                ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
        }

        $added = 0;
        foreach ($portsToCreate as $port) {
            $allocation = new Allocation();
            $allocation->nodeId = $nodeId;
            $allocation->ip = $body['allocation_ip'];
            $allocation->externalIp = !empty($body['allocation_external_ip']) ? $body['allocation_external_ip'] : null;
            $allocation->port = $port;
            $allocation->assignedTo = null;
            $allocation->save();
            $added++;
        }

        return $response->setFlash(['success' => "{$added} alocações adicionadas com sucesso!"])
            ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
    }

    public function updateAllocationAlias(Request $request, Response $response): Response
    {
        $nodeId = $request->getParam('node');
        $body = $request->getBody();

        $aliases = $body['aliases'] ?? [];
        $updated = 0;

        if (is_array($aliases)) {
            foreach ($aliases as $id => $alias) {
                $allocation = Allocation::get('id', $id);
                // Garante que a alocação pertence a este node antes de editar
                if ($allocation && $allocation->nodeId == $nodeId) {
                    $newAlias = empty(trim($alias)) ? null : trim($alias);
                    // Salva apenas se houve alteração
                    if ($allocation->externalIp !== $newAlias) {
                        $allocation->externalIp = $newAlias;
                        $allocation->save();
                        $updated++;
                    }
                }
            }
        }

        $msg = $updated > 0 ? "{$updated} aliases atualizados com sucesso!" : "Nenhuma alteração de alias detectada.";
        return $response->setFlash(['success' => $msg])
            ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
    }

    public function deleteAllocation(Request $request, Response $response): Response
    {
        $nodeId = $request->getParam('node');
        $allocId = $request->getParam('allocation');

        $allocation = Allocation::get('id', $allocId);

        if (!$allocation) {
            return $response->setFlash(['error' => 'Alocação não encontrada.'])
                ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
        }

        if ($allocation->isAssigned()) {
            return $response->setFlash(['error' => 'Não é possível remover uma porta que está em uso por um servidor.'])
                ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
        }

        $allocation->delete();

        return $response->setFlash(['success' => 'Alocação removida com sucesso!'])
            ->redirect("/admin/nodes/{$nodeId}/edit#tab-allocations");
    }
}