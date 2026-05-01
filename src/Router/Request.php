<?php

namespace Vatts\Router;

class Request
{
    protected string $method;
    protected string $path;
    protected array $params = [];
    protected array $body = [];
    protected array $query = [];
    protected array $headers = [];
    protected array $parsed = [];

    public function __construct(string $method, string $path, array $body = [], array $query = [], array $headers = [])
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->body = $body;
        $this->query = $query;
        $this->headers = $headers;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Define dados parsados pelo middleware
     */
    public function setParsed(string $key, mixed $value): void
    {
        $this->parsed[$key] = $value;
    }

    /**
     * Obtém dados parsados
     */
    public function getParsed(string $key, mixed $default = null): mixed
    {
        return $this->parsed[$key] ?? $default;
    }

    /**
     * Obtém todos os dados parsados
     */
    public function getAllParsed(): array
    {
        return $this->parsed;
    }

    /**
     * Verifica se o caminho atual corresponde ao padrão fornecido
     */
    public function is(string $path): bool
    {
        return $this->path === $path;
    }
}