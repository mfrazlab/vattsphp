<?php

namespace models;

use Vatts\Database\Model;

class Core extends Model
{
    protected static ?string $table = 'cores';

    // Mapeamento para renderização automática no Painel
    public array $view_map = [
        'Informações Básicas' => [
            ['label' => 'Nome do Core', 'key' => 'name', 'type' => 'text', 'desc' => 'Nome único de identificação do core.'],
            ['label' => 'Descrição', 'key' => 'description', 'type' => 'textarea', 'desc' => 'Breve resumo sobre a finalidade deste core.'],
            ['label' => 'E-mail do Criador', 'key' => 'creatorEmail', 'type' => 'email', 'desc' => 'Identificação do autor original.'],
        ],
        'Scripts de Automação' => [
            ['label' => 'Comando de Início', 'key' => 'startupCommand', 'type' => 'text', 'desc' => 'Comando base para iniciar o processo.'],
            ['label' => 'Comando de Parada', 'key' => 'stopCommand', 'type' => 'text', 'desc' => 'Comando enviado para encerrar o processo graciosamente.'],
        ],
        'Configurações Avançadas' => [
            ['label' => 'Imagens Docker (JSON)', 'key' => 'dockerImages', 'type' => 'monaco:json', 'desc' => 'Lista de imagens compatíveis no formato JSON.'],
            ['label' => 'Startup Parser', 'key' => 'startupParser', 'type' => 'monaco:json', 'desc' => 'JSON com as regras para identificar quando o servidor iniciou.'],
            ['label' => 'Sistema de Configuração', 'key' => 'configSystem', 'type' => 'monaco:json', 'desc' => 'Mapeamento de arquivos de configuração.'],
            ['label' => 'Variáveis (JSON)', 'key' => 'variables', 'type' => 'monaco:json', 'desc' => 'Definição de variáveis de ambiente personalizáveis.'],
        ],
        'Instalação' => [
            ['label' => 'Script de Instalação', 'key' => 'installScript', 'type' => 'monaco:bash', 'desc' => 'Script para instalação/configuração inicial do servidor.'],
            ['label' => 'Imagem de instalação', 'key' => 'installImage', 'type' => 'text', 'desc' => 'Imagem que será usada para a instalação.', 'default' => 'alpine'],
            ["label" => "Entrypoint de instalação", 'key' => "installEntrypoint", 'type' => "text", 'desc' => "O comando de ponto de entrada a ser usado para este script.", 'default' => 'ash']
        ],
    ];

    public static array $schema = [
        'id'             => 'id',
        'name'           => 'string',
        'startupCommand' => 'string',
        'stopCommand'    => 'string',
        'dockerImages'   => 'text', // Corrigido de string para text para suportar JSONs grandes
        'startupParser'  => 'text', // Corrigido para text
        'configSystem'   => 'text', // Corrigido para text
        'variables'      => 'text', // Corrigido para text
        'installScript'  => 'text',
        'installImage' => 'text',
        'installEntrypoint' => 'text',
        'description'    => 'string',
        'creatorEmail'   => 'string',
    ];

    // Propriedades para Autocomplete
// Obrigatórios (Não podem ser null)
    public int $id;
    public string $name;
    public string $description;
    public string $creatorEmail;

// Opcionais (Podem ser null)
    public ?string $installScript = null;
    public ?string $startupCommand = null;
    public ?string $stopCommand = null;
    public ?string $dockerImages = null;
    public ?string $startupParser = null;
    public ?string $installImage = null;
    public ?string $installEntrypoint = null;
    public ?string $configSystem = null;
    public ?string $variables = null;

    /**
     * Decodifica as imagens docker para uso direto no PHP.
     */
    public function getDockerImages(): array
    {
        return json_decode($this->dockerImages, true) ?? [];
    }

    /**
     * Decodifica as variáveis de ambiente para uso direto no PHP.
     */
    public function getVariables(): array
    {
        return json_decode($this->variables, true) ?? [];
    }
}