<?php

namespace models;

use Vatts\Database\Model;

class Node extends Model
{
    /**
     * Cache estático de handles cURL para FrankenPHP.
     * Mantém conexões TCP/SSL vivas entre requisições (Keep-Alive).
     */
    private static array $curlHandles = [];

    public static function getNodeByToken(string $token): ?Node
    {
        return self::get("token", $token);
    }

    protected static ?string $table = 'nodes';
    protected static array $hidden = ['password'];

    public array $view_map = [
        'Identificação' => [
            ['label' => 'Nome do Node', 'key' => 'name', 'type' => 'text', 'desc' => 'Nome de identificação deste node no painel.'],
            ['label' => 'Localização', 'key' => 'location', 'type' => 'text', 'desc' => 'Localização física ou datacenter onde o node está hospedado.'],
        ],
        'Comunicação' => [
            ['label' => 'Endereço IP', 'key' => 'ip', 'type' => 'text', 'desc' => 'Endereço IP ou FQDN (Domínio) do node.'],
            ['label' => 'Porta Daemon', 'key' => 'port', 'type' => 'number', 'desc' => 'Porta utilizada para a comunicação com o daemon do node.', 'default' => 8080],
            ['label' => 'Porta SFTP', 'key' => 'sftp', 'type' => 'number', 'desc' => 'Porta utilizada para a conexão de arquivos via SFTP.', 'default' => 2022],
        ],
        'Segurança' => [
            ['label' => 'Conexão SSL', 'key' => 'ssl', 'type' => 'select', 'options' => ['false' => 'Não (HTTP)', 'true' => 'Sim (HTTPS)'], 'desc' => 'Define se a comunicação com o daemon utilizará criptografia SSL/HTTPS.']
        ]
    ];

    public static array $schema = [
        'id'         => 'id',
        'name'       => 'string',
        'ip'         => 'string',
        'port'       => 'string',
        'sftp'       => 'string',
        'ssl'        => 'boolean',
        'token'      => 'string',
        'location'   => 'string'
    ];

    public int $id;
    public string $name;
    public string $ip;
    public string $port;
    public string $sftp;
    public ?string $token;
    public bool $ssl;
    public string $location;

    public function getUrl(): string
    {
        $protocol = 'http';
        return "{$protocol}://{$this->ip}:{$this->port}";
    }

    /**
     * Obtém um handle cURL persistente para este Node.
     */
    private function getPersistentHandle()
    {
        $key = "node_" . $this->id;
        if (!isset(self::$curlHandles[$key])) {
            self::$curlHandles[$key] = curl_init();
        }
        return self::$curlHandles[$key];
    }

    public function getStatus(): array|bool
    {
        $url = $this->getUrl() . "/api/v1/status";
        $ch = $this->getPersistentHandle();

        $payload = json_encode(['token' => $this->token]);

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload),
                'Expect:',
                'Connection: keep-alive'
            ],
            CURLOPT_NOSIGNAL => true,           // Essencial para timeouts em milissegundos não travarem no DNS
            CURLOPT_CONNECTTIMEOUT_MS => 100,   // 100ms para conectar (baixíssimo)
            CURLOPT_TIMEOUT_MS => 200,          // 200ms de tempo total de resposta
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1 // Força HTTP/1.1 para evitar problemas de negociação TLS
        ];

        error_log($this->ssl . '');
        // Se SSL estiver ativado, desativamos verificação (comum em Nodes com IPs diretos ou auto-assinados)
        // Se estiver desativado, garantimos que o cURL não tente usar configurações de SSL de uma requisição anterior no mesmo handle
        if ($this->ssl) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = false;
        } else {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            error_log("getStatus cURL Error (Node {$this->id}): " . curl_error($ch));
            return false;
        }

        $data = json_decode($response, true);
        return ($httpCode === 200 && isset($data['status']) && $data['status'] === 'success') ? $data : false;
    }

    public function apiRequest(string $method, string $endpoint, array $data = [], array $headers = []): array|bool
    {
        $endpoint = ltrim($endpoint, '/');
        $url = $this->getUrl() . "/{$endpoint}";

        $ch = $this->getPersistentHandle();
        $method = strtoupper($method);

        if ($this->token && !isset($data['token'])) {
            $data['token'] = $this->token;
        }

        $headers[] = 'Connection: keep-alive';
        $headers[] = 'Expect:';

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_NOSIGNAL => true,             // Essencial para timeouts curtos
            CURLOPT_CONNECTTIMEOUT_MS => 200,     // 200ms para conectar na API
            CURLOPT_TIMEOUT_MS => 500,            // 500ms máximo para responder
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1
        ];

        if ($method === 'GET') {
            if (!empty($data)) $options[CURLOPT_URL] = $url . '?' . http_build_query($data);
            $options[CURLOPT_POSTFIELDS] = null; // Reset de payload para GET
        } else {
            $payload = json_encode($data);
            $options[CURLOPT_POSTFIELDS] = $payload;
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($payload);
        }

        $options[CURLOPT_HTTPHEADER] = $headers;
        error_log($this->ssl . '');
        if ($this->ssl) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = false;
        } else {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $info = curl_getinfo($ch);
        $ttfb = round(($info['starttransfer_time'] - $info['pretransfer_time']) * 1000, 2);
        $total = round($info['total_time'] * 1000, 2);

        error_log("[Profiler] Node.js(TTFB): {$ttfb}ms | Total cURL: {$total}ms");

        if ($response === false) {
            error_log("apiRequest cURL Error (Node {$this->id}): " . curl_error($ch));
            return false;
        }

        $decoded = json_decode($response, true);
        return [
            'success' => $httpCode >= 200 && $httpCode < 300,
            'code'    => $httpCode,
            'body'    => $decoded !== null ? $decoded : $response
        ];
    }
}