<?php

namespace Vatts\Handlers;

use Vatts\Router\Request;
use Vatts\Router\Response;

/**
 * Handler para fallback do Frontend
 *
 * Em produção: serve arquivos estáticos do projeto (exported/index.html para SPA)
 * Em desenvolvimento: faz proxy para o servidor de desenvolvimento (localhost:3000)
 */
class FrontendHandler
{
    protected string $projectPath;
    protected string $environment;
    protected int $devServerPort;

    public function __construct(string $projectPath, string $environment = 'dev', int $devServerPort = 3000)
    {
        $this->projectPath = rtrim($projectPath, '/\\');
        $this->environment = $environment;
        $this->devServerPort = $devServerPort;
    }

    /**
     * Executa o handler - retorna um callable
     */
    public function __invoke(Request $request, Response $response): Response
    {
        // 1. Tenta buscar arquivos extras em serve/ (ex: serve/admin/modal.js)
        // Mas ignora se o caminho começar com 'dist/' para não conflitar
        $extraFileResponse = $this->tryServeExtraStatic($request, $response);
        if ($extraFileResponse !== null) {
            return $extraFileResponse;
        }

        if ($this->environment === 'production') {
            return $this->serveStaticOrSPA($request, $response);
        }

        return $this->proxyToDevServer($request, $response);
    }

    /**
     * Tenta servir arquivos que estão diretamente na pasta 'serve',
     * exceto o que estiver em 'serve/dist'.
     */
    protected function tryServeExtraStatic(Request $request, Response $response): ?Response
    {
        $uri = ltrim($request->getPath(), '/');

        // Se estiver vazio (home) ou tentar acessar a dist por aqui, ignora
        if (empty($uri) || str_starts_with($uri, 'dist/')) {
            return null;
        }

        $servePath = $this->projectPath . DIRECTORY_SEPARATOR . 'serve' . DIRECTORY_SEPARATOR;
        $filePath = $servePath . $uri;
        $realFilePath = realpath($filePath);

        // Verifica se o arquivo existe e se está dentro da pasta serve (segurança)
        if ($realFilePath && str_starts_with($realFilePath, realpath($servePath)) && is_file($realFilePath)) {
            // Se o arquivo real estiver dentro de 'serve/dist', ignoramos para seguir a lógica da SPA
            $distPath = realpath($servePath . 'dist');
            if ($distPath && str_starts_with($realFilePath, $distPath)) {
                return null;
            }

            return $this->serveFile($realFilePath, $response);
        }

        return null;
    }

    /**
     * Tenta servir um arquivo estático (com compressão) ou cai no index.html da SPA
     */
    protected function serveStaticOrSPA(Request $request, Response $response): Response
    {
        $uri = ltrim($request->getPath(), '/');
        $exportPath = $this->projectPath . DIRECTORY_SEPARATOR . 'serve' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR;

        $targetFile = empty($uri) ? 'index.html' : $uri;
        $filePath = $exportPath . $targetFile;
        $realFilePath = realpath($filePath);

        // Segurança e verificação de existência
        if (!$realFilePath || !str_starts_with($realFilePath, realpath($exportPath)) || !is_file($realFilePath)) {

            $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

            if (str_contains($acceptEncoding, 'br') && file_exists($filePath . '.br')) {
                return $this->serveFile($filePath, $response);
            }

            if (str_contains($acceptEncoding, 'gzip') && file_exists($filePath . '.gz')) {
                return $this->serveFile($filePath, $response);
            }

            return $this->serveFile($exportPath . 'index.html', $response->status(404));
        }

        return $this->serveFile($realFilePath, $response);
    }

    /**
     * Serve um arquivo específico tratando compressão Brotli/Gzip
     */
    protected function serveFile(string $filePath, Response $response): Response
    {
        $mimeType = $this->getMimeType($filePath);
        $encoding = null;
        $servedPath = $filePath;

        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        if (str_contains($acceptEncoding, 'br') && file_exists($filePath . '.br')) {
            $servedPath = $filePath . '.br';
            $encoding = 'br';
        }
        elseif (str_contains($acceptEncoding, 'gzip') && file_exists($filePath . '.gz')) {
            $servedPath = $filePath . '.gz';
            $encoding = 'gzip';
        }
        elseif (!file_exists($filePath)) {
            return $response->status(404)->send('File not found');
        }

        if ($encoding) {
            $response->header('Content-Encoding', $encoding);
            $response->header('Vary', 'Accept-Encoding');
        }

        $content = file_get_contents($servedPath);
        return $response->header('Content-Type', $mimeType)->send($content);
    }

    /**
     * Faz proxy para o servidor de desenvolvimento (Vite, Next.js, etc)
     */
    protected function proxyToDevServer(Request $request, Response $response): Response
    {
        $path = $request->getPath();
        $devServerUrl = 'http://127.0.0.1:' . $this->devServerPort . $path;

        $query = $request->getQuery();
        if (!empty($query)) {
            $devServerUrl .= '?' . http_build_query($query);
        }

        $ch = curl_init($devServerUrl);

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            if (strtolower($name) !== 'host') {
                $headers[] = $name . ': ' . (is_array($values) ? implode(', ', $values) : $values);
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $body = $request->getBody();
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($body) ? json_encode($body) : $body);
        }

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            return $response->status(502)->html(
                '<h1>502 Bad Gateway</h1><p>' . htmlspecialchars($error) . '</p>'
            );
        }

        $resHeaders = substr($result, 0, $headerSize);
        $resBody = substr($result, $headerSize);

        $response = $response->status($httpCode);

        $headerLines = explode("\r\n", rtrim($resHeaders));
        foreach ($headerLines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = strtolower(trim($key));
                if (!in_array($key, ['transfer-encoding', 'connection', 'content-length'])) {
                    $response->header($key, trim($value));
                }
            }
        }

        return $response->send($resBody);
    }

    protected function getMimeType(string $path): string
    {
        $cleanPath = preg_replace('/\.(gz|br)$/', '', $path);
        $extension = pathinfo($cleanPath, PATHINFO_EXTENSION);

        $mimes = [
            'html' => 'text/html',
            'js'   => 'application/javascript',
            'css'  => 'text/css',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
        ];

        return $mimes[$extension] ?? 'application/octet-stream';
    }
}
