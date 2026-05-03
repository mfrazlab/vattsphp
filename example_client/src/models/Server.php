<?php

namespace models;

use Vatts\Database\Model;
use Vatts\Database\DB;

class Server extends Model
{
    protected static ?string $table = 'servers';

    // Mapeamento para renderização automática no Painel
    public array $view_map = [
        'Informações Básicas' => [
            ['label' => 'Nome do Servidor', 'key' => 'name', 'type' => 'text', 'desc' => 'Nome de exibição do servidor.'],
            ['label' => 'Descrição', 'key' => 'description', 'type' => 'textarea', 'desc' => 'Breve descrição do servidor.'],
            ['label' => 'Dono (Owner ID)', 'key' => 'ownerId', 'type' => 'text', 'desc' => 'UUID do usuário proprietário.', 'form' => false],
            ['label' => 'Grupo', 'key' => 'group', 'type' => 'text', 'desc' => 'Grupo organizacional do servidor.'],
        ],
        'Instância e Core' => [
            ['label' => 'ID do Core', 'key' => 'coreId', 'type' => 'text', 'desc' => 'Referência ao Core utilizado.', 'form' => false],
            ['label' => 'UUID do Node', 'key' => 'nodeUuid', 'type' => 'text', 'desc' => 'Identificador do nó de hospedagem.', 'form' => false],
            ['label' => 'Imagem Docker', 'key' => 'dockerImage', 'type' => 'text', 'desc' => 'Imagem docker utilizada na instância.', 'form' => false],
            ['label' => 'Comando de Startup', 'key' => 'startupCommand', 'type' => 'text', 'desc' => 'Comando final aplicado ao servidor.', 'form' => false],
        ],
        'Recursos de Hardware' => [
            ['label' => 'Memória RAM (MB)', 'key' => 'ram', 'type' => 'number', 'desc' => 'Limite de memória RAM.'],
            ['label' => 'CPU (%)', 'key' => 'cpu', 'type' => 'number', 'desc' => 'Limite de processamento.'],
            ['label' => 'Disco (MB)', 'key' => 'disk', 'type' => 'number', 'desc' => 'Limite de armazenamento.'],
        ],
    ];

    public static array $schema = [
        'id'          => 'id',
        'name'        => 'string',
        'description' => 'string',
        'ownerId'     => 'string',
        'ram'         => 'int',
        'cpu'         => 'int',
        'disk'        => 'int',
        'coreId'      => 'string',
        'nodeUuid'    => 'string',
        'dockerImage' => 'string',
        'startupCommand' => 'string',
        'envVars'     => 'text',
        'group'       => 'string',
        'serverUuid'  => 'string',

        'allocationId' => 'foreign:allocations.id',
    ];

    // Propriedades Obrigatórias
    public int $id;
    public string $name;
    public string $ownerId;
    public int $ram;
    public int $cpu;
    public int $disk;
    public string $coreId;
    public string $nodeUuid;

    // Propriedades Opcionais
    public ?string $description = null;
    public ?string $dockerImage = null;
    public ?string $group = null;
    public ?string $startupCommand = null;
    public ?string $envVars = null;

    // Allocation (definida na criação)
    public ?int $allocationId = null;
    // UUID do servidor (gerado antes do envio para o node ou retornado pelo node)
    public ?string $serverUuid = null;

    public function getFirstAllocation(): Allocation
    {
        return Allocation::get('id', $this->allocationId);
    }

    public static function getByShortUuid(string $short): ?self
    {
        $pdo = DB::getPdo();
        $table = self::getTableName();
        $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `serverUuid` LIKE :p LIMIT 1");
        $stmt->execute(['p' => $short . '-%']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? new self($row, false) : null; // false = Bypassa syncSchema para + velocidade
    }

    public function getNode(): Node
    {
        return Node::get('id', $this->nodeUuid);
    }

    public function getStatus(?string $requestUserUuid = null): array|bool
    {
        $node = $this->getNode();

        if (!$node) {
            return false;
        }

        $payload = [
            'serverId' => $this->serverUuid,
            'userUuid' => $requestUserUuid ?? $this->ownerId
        ];

        $request = $node->apiRequest("POST", '/api/v1/servers/status', $payload);
        $request2 = $node->apiRequest("POST", '/api/v1/servers/usage', $payload);

        if (!$request || !$request2 || empty($request['success']) || empty($request2['success'])) {
            return false;
        }

        return [
            'status' => $request['body']['serverStatus'] ?? 'unknown',
            'usage'  => $request2['body']['usage'] ?? null
        ];
    }

    public function hasPermission(User $user): bool
    {
        return $this->ownerId === (string) $user->id || $user->isAdmin();
    }

    /**
     * Envia uma ação (start, restart, stop, kill, command) para o Node remoto
     * Totalmente otimizado: Agrupa consultas SQL num único JOIN e reduz instâncias do ORM.
     */
    public function sendAction(string $action, ?string $requestUserUuid = null, ?string $command = null): array|bool
    {
        $timeStart = microtime(true);
        try {
            $payload = [
                'serverId' => $this->serverUuid,
                'userUuid' => $requestUserUuid ?? $this->ownerId,
                'action'   => $action
            ];

            $pdo = DB::getPdo(); // Usamos conexão direta do cache para máxima velocidade

            switch ($action) {
                case 'install':
                case 'start':
                case 'restart':
                    // MEGA OTIMIZAÇÃO: Busca o Core e a Alocação Primária num JOIN rápido.
                    // Evita criar classes do ORM e reduz o tempo de banco de dados para < 1ms.
                    $stmt = $pdo->prepare("
                        SELECT 
                            c.id as core_id, c.name as core_name, 
                            c.startupCommand as core_startup, c.stopCommand as core_stop, 
                            c.installScript,
                            c.installImage,
                            c.installEntrypoint,
                            c.startupParser, c.configSystem,
                            c.variables,
                            a.ip, a.port, a.externalIp
                        FROM `cores` c
                        LEFT JOIN `allocations` a ON a.id = :allocId
                        WHERE c.id = :coreId
                        LIMIT 1
                    ");
                    $stmt->execute([
                        'allocId' => $this->allocationId,
                        'coreId'  => $this->coreId
                    ]);
                    $related = $stmt->fetch(\PDO::FETCH_ASSOC);

                    $payload['memory'] = (int) $this->ram;
                    $payload['cpu'] = (int) $this->cpu;
                    $payload['disk'] = (int) $this->disk;
                    $payload['image'] = $this->dockerImage;
                    $payload['additionalAllocation'] = []; // Pode expandir dps, mantido vazio por padrão

                    // --- INÍCIO PROCESSAMENTO DE VARIÁVEIS COM DEFAULTS ---
                    $serverEnv = json_decode($this->envVars ?? '{}', true) ?: [];
                    $finalEnv = [];

                    if ($related && !empty($related['variables'])) {
                        $coreVars = json_decode($related['variables'], true) ?: [];

                        foreach ($coreVars as $cv) {
                            $envName = $cv['envVariable'] ?? null;
                            $rules = $cv['rules'] ?? '';

                            if (!$envName) continue;

                            $default = '';
                            // Extrair o default das rules (ex: "required|string|default:latest")
                            $rulesArray = explode('|', $rules);
                            foreach ($rulesArray as $rule) {
                                if (str_starts_with($rule, 'default:')) {
                                    $default = substr($rule, 8); // Pega o que tem depois de "default:"
                                    break;
                                }
                            }

                            // Adiciona no Array final a variável com seu respectivo valor default
                            $finalEnv[$envName] = $default;
                        }
                    }

                    // array_merge vai sobrescrever as defaults do core com as específicas configuradas no servidor (se existirem)
                    $payload['environment'] = array_merge($finalEnv, $serverEnv);
                    // --- FIM PROCESSAMENTO DE VARIÁVEIS COM DEFAULTS ---

                    if ($related) {
                        $payload['primaryAllocation'] = $related['ip'] ? [
                            'ip'         => $related['ip'],
                            'port'       => $related['port'],
                            'externalIp' => $related['externalIp']
                        ] : null;

                        $payload['core'] = [
                            'id'             => $related['core_id'],
                            'name'           => $related['core_name'],
                            'startupCommand' => !empty($this->startupCommand) ? $this->startupCommand : $related['core_startup'],
                            'stopCommand'    => $related['core_stop'],
                            'startupParser'  => json_decode($related['startupParser'] ?? '{}', true),
                            'configSystem'   => json_decode($related['configSystem'] ?? '{}', true),
                            'installScript' => $related["installScript"] ?? null,
                            'installImage' => $related["installImage"] ?? null,
                            'installEntrypoint' => $related['installEntrypoint'] ?? null
                        ];
                    } else {
                        $payload['primaryAllocation'] = null;
                        $payload['core'] = null;
                    }
                    break;

                case 'stop':
                    // Otimização: Busca apenas a coluna stopCommand invés de carregar a tabela `cores` inteira na RAM
                    $stmt = $pdo->prepare("SELECT `stopCommand` FROM `cores` WHERE `id` = :id LIMIT 1");
                    $stmt->execute(['id' => $this->coreId]);
                    $cmd = $stmt->fetchColumn() ?: 'stop';
                    $payload['command'] = $cmd;
                    break;

                case 'command':
                    $payload['command'] = $command;
                    break;

                case 'kill':
                    break;

                default:
                    return false;
            }

            // OTIMIZAÇÃO EXTREMA: Removido o $this->getNode() para evitar o syncSchema() na tabela nodes
            $stmtNode = $pdo->prepare("SELECT * FROM `nodes` WHERE `id` = :id LIMIT 1");
            $stmtNode->execute(['id' => $this->nodeUuid]);
            $nodeRow = $stmtNode->fetch(\PDO::FETCH_ASSOC);

            if (!$nodeRow) return false;

            // Instancia passando "false" como segundo parâmetro para ignorar o schema checker
            $node = new Node($nodeRow, false);

            // Fim da contagem de tempo de processamento interno do PHP (CPU + MySQL Local)
            $timePayloadReady = microtime(true);

            // Dispara requisição HTTP cURL para a rede
            $request = $node->apiRequest("POST", '/api/v1/servers/action', $payload);

            // Fim da contagem de tempo de rede (Latência + Tempo do Daemon Processar)
            $timeApiDone = microtime(true);

            $ms_php = round(($timePayloadReady - $timeStart) * 1000, 2);
            $ms_api = round(($timeApiDone - $timePayloadReady) * 1000, 2);
            $ms_total = round(($timeApiDone - $timeStart) * 1000, 2);

            error_log("[sendAction Otimizado] PHP Payload: {$ms_php}ms | cURL Node.js: {$ms_api}ms | Total: {$ms_total}ms");

            if (!$request || empty($request['success'])) {
                if (isset($request['body']['error'])) {
                    error_log("Daemon API Error: " . $request['body']['error']);
                }
                return false;
            }

            return $request['body'] ?? true;
        } catch (\Exception $e) {
            error_log("Error sending action to node: " . $e->getMessage());
            return false;
        }
    }
}