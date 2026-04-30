<?php

namespace Vatts\Router;

use Vatts\Utils\BladeConfig;

class Response
{
    protected int $statusCode = 200;
    protected array $headers = [];
    protected mixed $body = null;

    public function status(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->statusCode;
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function json(mixed $data): self
    {
        $this->body = json_encode($data);
        $this->header('Content-Type', 'application/json');
        return $this;
    }

    public function html(string $html): self
    {
        $this->body = $html;
        $this->header('Content-Type', 'text/html');
        return $this;
    }

    public function text(string $text): self
    {
        $this->body = $text;
        $this->header('Content-Type', 'text/plain');
        return $this;
    }

    public function send(mixed $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Renderiza um template usando a configuração centralizada do BladeConfig.
     */
    public function view(string $template, array $data = []): self
    {
        // Pega a instância configurada e roda o template
        $this->body = BladeConfig::get()->run($template, $data);
        $this->header('Content-Type', 'text/html');

        return $this;
    }
}