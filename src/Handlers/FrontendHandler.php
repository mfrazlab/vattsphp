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
        if ($this->environment === 'production') {
            return $this->serveStaticOrSPA($request, $response);
        }

        return $this->proxyToDevServer($request, $response);
    }

    /**
     * Tenta servir um arquivo estático (com compressão) ou cai no index.html da SPA
     */
    protected function serveStaticOrSPA(Request $request, Response $response): Response
    {
        $uri = ltrim($request->getPath(), '/');
        $exportPath = $this->projectPath . DIRECTORY_SEPARATOR . 'exported' . DIRECTORY_SEPARATOR;

        $targetFile = empty($uri) ? 'index.html' : $uri;
        $filePath = $exportPath . $targetFile;
        $realFilePath = realpath($filePath);

        // Segurança e verificação de existência
        if (!$realFilePath || !str_starts_with($realFilePath, realpath($exportPath)) || !is_file($realFilePath)) {

            // Se o arquivo original não existe, vamos checar se existem versões comprimidas (.br ou .gz)
            // antes de desistir e mandar para o index.html
            $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

            if (str_contains($acceptEncoding, 'br') && file_exists($filePath . '.br')) {
                return $this->serveFile($filePath, $response);
            }

            if (str_contains($acceptEncoding, 'gzip') && file_exists($filePath . '.gz')) {
                return $this->serveFile($filePath, $response);
            }

            // Se realmente não existe nada, manda o index.html com 404 como solicitado
            return $this->serveFile($exportPath . 'index.html', $response->status(404));
        }

        return $this->serveFile($realFilePath, $response);
    }

    /**
     * Serve um arquivo específico tratando compressão Brotli/Gzip
     */
    protected function serveFile(string $filePath, Response $response): Response
    {
        // Se chegamos aqui e o arquivo base não existe, mas o .gz ou .br sim,
        // a lógica abaixo vai detectar e servir.

        $mimeType = $this->getMimeType($filePath);
        $encoding = null;
        $servedPath = $filePath;

        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

        // Tenta Brotli (.br)
        if (str_contains($acceptEncoding, 'br') && file_exists($filePath . '.br')) {
            $servedPath = $filePath . '.br';
            $encoding = 'br';
        }
        // Tenta Gzip (.gz)
        elseif (str_contains($acceptEncoding, 'gzip') && file_exists($filePath . '.gz')) {
            $servedPath = $filePath . '.gz';
            $encoding = 'gzip';
        }
        // Se nem o comprimido nem o original existem (caso do index.html forçado), garante que o arquivo existe
        elseif (!file_exists($filePath)) {
            // Fallback de segurança caso o arquivo passado não exista de fato
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
        $devServerUrl = 'http://localhost:' . $this->devServerPort . $path;

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
            curl_close($ch);
            return $response->status(502)->html(
                '<h1>502 Bad Gateway</h1><p>' . htmlspecialchars($error) . '</p>'
            );
        }

        $resHeaders = substr($result, 0, $headerSize);
        $resBody = substr($result, $headerSize);
        curl_close($ch);

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
        // Remove extensões de compressão para pegar o mime original
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