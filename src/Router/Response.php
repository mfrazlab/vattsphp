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
        // [SEGURANÇA] Previne HTTP Response Splitting (CRLF Injection).
        // Impede que atacantes injetem quebras de linha para criar falsos headers ou forjar cookies.
        $key = str_replace(["\r", "\n", "\0"], '', $key);
        $value = str_replace(["\r", "\n", "\0"], '', $value);

        // Allow multiple header values for the same header name (e.g., Set-Cookie)
        if (isset($this->headers[$key])) {
            if (is_array($this->headers[$key])) {
                $this->headers[$key][] = $value;
            } else {
                $this->headers[$key] = [$this->headers[$key], $value];
            }
        } else {
            $this->headers[$key] = $value;
        }
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function json(mixed $data): self
    {
        // [SEGURANÇA] Adicionadas flags para escapar marcações HTML e prevenir XSS
        // caso esse JSON seja embutido no meio de uma página web acidentalmente.
        $this->body = json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
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
        $request = Router::parseRequest();

        // Flash handling: se existir o cookie de flash, decodifica e injeta na view
        $flashCookie = $_COOKIE['vatts_flash'] ?? null;
        if (!empty($flashCookie)) {
            $decoded = base64_decode(rawurldecode($flashCookie), true);
            if ($decoded !== false) {
                $flashData = json_decode($decoded, true);
                if (is_array($flashData)) {
                    if (!empty($flashData['success']) && !isset($data['success'])) {
                        $data['success'] = $flashData['success'];
                    }
                    if (!empty($flashData['error']) && !isset($data['error'])) {
                        $data['error'] = $flashData['error'];
                    }
                }
            }

            // Sinaliza remoção do cookie (será enviado no cabeçalho)
            $this->clearFlash();
        }

        // colocar request junto com data
        $data = array_merge($data, ['request' => $request]);
        // Pega a instância configurada e roda o template
        $this->body = BladeConfig::get()->run($template, $data);
        $this->header('Content-Type', 'text/html');

        return $this;
    }

    /**
     * Redireciona a requisição para uma nova URL.
     */
    public function redirect(string $url, int $status = 302): self
    {
        // [SEGURANÇA] Bloqueia quebras de linha na URL para impedir CRLF e Open Redirect malformado.
        $url = str_replace(["\r", "\n", "\0"], '', $url);

        $this->status($status);
        $this->header('Location', $url);

        return $this;
    }

    /**
     * Set a cookie header. Options supports: path, domain, max-age, expires (RFC), secure, httponly, samesite
     */
    public function setCookie(string $name, string $value, array $options = []): self
    {
        $parts = [];
        // Segurança garantida pelo str_replace no método header() chamado no final
        $parts[] = urlencode($name) . '=' . urlencode($value);

        if (!empty($options['path'])) $parts[] = 'Path=' . $options['path'];
        if (!empty($options['domain'])) $parts[] = 'Domain=' . $options['domain'];
        if (isset($options['max-age'])) $parts[] = 'Max-Age=' . intval($options['max-age']);
        if (!empty($options['expires'])) $parts[] = 'Expires=' . $options['expires'];
        if (!empty($options['secure'])) $parts[] = 'Secure';
        if (!empty($options['httponly'])) $parts[] = 'HttpOnly';
        if (!empty($options['samesite'])) $parts[] = 'SameSite=' . $options['samesite'];

        $this->header('Set-Cookie', implode('; ', $parts));
        return $this;
    }

    /**
     * Clear a cookie by setting expiration in the past
     */
    public function clearCookie(string $name, array $options = []): self
    {
        $opts = $options;
        $opts['max-age'] = 0;
        $opts['expires'] = 'Thu, 01 Jan 1970 00:00:00 GMT';
        // Ensure path default
        if (empty($opts['path'])) $opts['path'] = '/';
        return $this->setCookie($name, '', $opts);
    }

    /**
     * Convenience helper to set the standard flash cookie used by controllers.
     */
    public function setFlash(array $payload, int $ttl = 10): self
    {
        $value = rawurlencode(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)));
        return $this->setCookie('vatts_flash', $value, ['path' => '/', 'max-age' => $ttl, 'httponly' => true, 'samesite' => 'Lax']);
    }

    /**
     * Clear the flash cookie.
     */
    public function clearFlash(): self
    {
        return $this->clearCookie('vatts_flash', ['path' => '/']);
    }
}