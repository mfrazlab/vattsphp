<?php

namespace models;

use Vatts\Database\Model;

class Allocation extends Model
{
    protected static ?string $table = 'allocations';

    // Campos que não devem ser expostos em JSON (caso necessário)
    protected static array $hidden = [];

    /**
     * Mapa de visualização para o painel administrativo.
     */
    public array $view_map = [
        'Relacionamento' => [
            ['label' => 'Atribuído a', 'key' => 'assignedTo', 'type' => 'text', 'desc' => 'ID do servidor utilizando esta porta (null se disponível).'],
        ],
        'Rede' => [
            ['label' => 'Endereço IP', 'key' => 'ip', 'type' => 'text', 'desc' => 'Endereço IP interno para a alocação.'],
            ['label' => 'IP Externo', 'key' => 'externalIp', 'type' => 'text', 'desc' => 'Endereço IP público (opcional).'],
            ['label' => 'Porta', 'key' => 'port', 'type' => 'number', 'desc' => 'Porta numérica para conexão.'],
        ]
    ];

    /**
     * Definição do Schema para o ORM.
     */
    public static array $schema = [
        'id'         => 'id',
        'nodeId'     => 'string',
        'ip'         => 'string',
        'externalIp' => 'string',
        'port'       => 'int',
        'assignedTo' => 'string'
    ];

    // Propriedades tipadas para Autocomplete e segurança de tipos
    public int $id;
    public string $nodeId;
    public string $ip;
    public ?string $externalIp = null;
    public int $port;
    public ?string $assignedTo = null;

    /**
     * Verifica se a alocação já está em uso por algum servidor.
     * @return bool
     */
    public function isAssigned(): bool
    {
        return !empty($this->assignedTo);
    }
}