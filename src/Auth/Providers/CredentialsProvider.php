<?php

namespace Vatts\Auth\Providers;

use Vatts\Auth\AuthProviderInterface;
use Vatts\Router\Request;
use Exception;

class CredentialsProvider implements AuthProviderInterface
{
    public string $id;
    public string $name;
    public string $type = 'credentials';

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->id = $config['id'] ?? 'credentials';
        $this->name = $config['name'] ?? 'Credentials';
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function handleSignIn(array $credentials, Request $req): ?array
    {
        try {
            if (!isset($this->config['authorize']) || !is_callable($this->config['authorize'])) {
                throw new Exception('Authorize function not provided');
            }

            // Executa a função do desenvolvedor
            $user = call_user_func($this->config['authorize'], $credentials);

            if (!$user) {
                return null;
            }

            // Adiciona informações do provider ao usuário
            $user['provider'] = $this->id;
            $user['providerId'] = $user['id'] ?? $user['email'] ?? 'unknown';

            return $user;

        } catch (Exception $error) {
            error_log("[{$this->id} Provider] Error during sign in: " . $error->getMessage());
            return null;
        }
    }

    public function getConfig(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'credentials' => $this->config['credentials'] ?? []
        ];
    }

    public function getAdditionalRoutes(): array
    {
        return []; // Credentials não precisa de rotas adicionais de callback
    }

    /**
     * Validação robusta de email
     */
    public function validateCredentials(array $credentials): bool
    {
        foreach ($this->config['credentials'] as $key => $field) {
            if (empty($credentials[$key])) {
                error_log("[{$this->id} Provider] Missing required credential: {$key}");
                return false;
            }

            if (($field['type'] ?? '') === 'email' && !$this->isValidEmail($credentials[$key])) {
                error_log("[{$this->id} Provider] Invalid email format: {$credentials[$key]}");
                return false;
            }
        }
        return true;
    }

    private function isValidEmail(string $email): bool
    {
        if (empty($email) || strlen($email) > 320) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $parts = explode('@', $email);
        if (count($parts) !== 2) return false;

        [$local, $domain] = $parts;
        if (strlen($local) > 64 || strlen($domain) > 255) {
            return false;
        }

        if (str_contains($email, '..')) {
            return false;
        }

        return true;
    }
}