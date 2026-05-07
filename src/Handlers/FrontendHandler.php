<?php

namespace Vatts\Handlers;

use Vatts\Router\Request;
use Vatts\Router\Response;

/**
 * Handler para fallback do Frontend
 * * Em produção: serve arquivos estáticos do projeto (exported/index.html para SPA)
 * Em desenvolvimento: faz proxy para o servidor de desenvolvimento (localhost:3000)
 */
class FrontendHandler
{
    protected string $projectPath;
    protected string $environment;
    protected int $devServerPort;
    protected array $additionalTags;

    public function __construct(string $projectPath, string $environment = 'dev', int $devServerPort = 3000, array $additionalTags = [])
    {
        $this->projectPath = rtrim($projectPath, '/\\');
        $this->environment = $environment;
        $this->devServerPort = $devServerPort;
        $this->additionalTags = $additionalTags;
    }

    /**
     * Executa o handler - retorna um callable
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $extraFileResponse = $this->tryServeExtraStatic($request, $response);
        if ($extraFileResponse !== null) {
            return $extraFileResponse;
        }

        if ($this->environment === 'production') {
            return $this->serveStaticOrSPA($request, $response);
        }

        return $this->proxyToDevServer($request, $response);
    }

    protected function tryServeExtraStatic(Request $request, Response $response): ?Response
    {
        $uri = ltrim($request->getPath(), '/');

        if (empty($uri) || str_starts_with($uri, 'dist/')) {
            return null;
        }

        $servePath = $this->projectPath . DIRECTORY_SEPARATOR . 'serve' . DIRECTORY_SEPARATOR;
        $filePath = $servePath . $uri;

        // Verifica se o arquivo existe (ou se existe uma versão comprimida dele)
        if ($this->fileExistsOrCompressed($filePath)) {
            $realPath = realpath($filePath) ?: realpath($filePath . '.gz') ?: realpath($filePath . '.br');

            if ($realPath && str_starts_with($realPath, realpath($servePath))) {
                $distPath = realpath($servePath . 'dist');
                if ($distPath && str_starts_with($realPath, $distPath)) {
                    return null;
                }
                return $this->serveFile($filePath, $request, $response, 'public, max-age=3600');
            }
        }

        return null;
    }

    protected function serveStaticOrSPA(Request $request, Response $response): Response
    {
        $uri = ltrim($request->getPath(), '/');
        $exportPath = $this->projectPath . DIRECTORY_SEPARATOR . 'serve' . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR;

        $targetFile = empty($uri) ? 'index.html' : $uri;
        $filePath = $exportPath . $targetFile;

        // Alteração: Verifica se o arquivo original OU versões comprimidas existem
        if (!$this->fileExistsOrCompressed($filePath)) {
            $fallbackPath = $exportPath . 'index.html';
            return $this->serveFile($fallbackPath, $request, $response->status(404), 'no-cache, no-store, must-revalidate');
        }

        return $this->serveFile($filePath, $request, $response, $this->resolveCachePolicy($filePath));
    }

    /**
     * Helper para verificar se o arquivo existe ou se existe uma versão .gz/.br
     */
    protected function fileExistsOrCompressed(string $filePath): bool
    {
        return is_file($filePath) || is_file($filePath . '.gz') || is_file($filePath . '.br');
    }

    protected function serveFile(string $filePath, Request $request, Response $response, ?string $cacheControl = null): Response
    {
        $mimeType = $this->getMimeType($filePath);
        $isHtml = ($mimeType === 'text/html');
        $needsInjection = $isHtml && !empty($this->additionalTags);

        $encoding = null;
        $servedPath = $filePath;

        // Se o arquivo original não existir, mas o comprimido sim, forçamos o uso do comprimido
        // independente de injeção (já que injeção só faz sentido em HTML e HTML raramente é servido só .gz)
        if (!$needsInjection) {
            $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

            if (str_contains($acceptEncoding, 'br') && file_exists($filePath . '.br')) {
                $servedPath = $filePath . '.br';
                $encoding = 'br';
            }
            elseif (str_contains($acceptEncoding, 'gzip') && file_exists($filePath . '.gz')) {
                $servedPath = $filePath . '.gz';
                $encoding = 'gzip';
            }
            // Fallback caso o arquivo original não exista mas o .gz sim (mesmo se o browser não pediu,
            // melhor tentar entregar gzippado do que dar 404, embora browsers modernos sempre peçam)
            elseif (!file_exists($filePath)) {
                if (file_exists($filePath . '.br')) {
                    $servedPath = $filePath . '.br';
                    $encoding = 'br';
                } elseif (file_exists($filePath . '.gz')) {
                    $servedPath = $filePath . '.gz';
                    $encoding = 'gzip';
                }
            }
        }

        if (!file_exists($servedPath)) {
            return $response->status(404)->send('File not found');
        }

        if ($encoding) {
            $response->header('Content-Encoding', $encoding);
            $response->header('Vary', 'Accept-Encoding');
        }

        $cacheControl = $cacheControl ?? $this->resolveCachePolicy($servedPath);
        $response->header('Cache-Control', $cacheControl);

        if ($needsInjection && file_exists($filePath)) {
            $content = file_get_contents($filePath);
            $tagsString = implode("\n", $this->additionalTags) . "\n";

            if (stripos($content, '</head>') !== false) {
                $content = str_ireplace('</head>', $tagsString . '</head>', $content);
            } else {
                $content .= $tagsString;
            }

            $etag = '"' . sha1($content) . '"';
            $lastModified = gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT';
        } else {
            $etag = $this->buildEtag($servedPath);
            $lastModified = gmdate('D, d M Y H:i:s', filemtime($servedPath)) . ' GMT';
            $content = file_get_contents($servedPath);
        }

        $response->header('ETag', $etag);
        $response->header('Last-Modified', $lastModified);

        $ifNoneMatch = $this->getRequestHeader($request, 'If-None-Match');
        if (!empty($ifNoneMatch) && trim($ifNoneMatch) === $etag) {
            return $response->status(304)->send('');
        }

        return $response->header('Content-Type', $mimeType)->send($content);
    }

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
            $lowerName = strtolower($name);
            if ($lowerName === 'accept-encoding' && !empty($this->additionalTags)) {
                continue;
            }
            if ($lowerName !== 'host') {
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
            return $response->status(502)->html('<h1>502 Bad Gateway</h1>');
        }

        $resHeaders = substr($result, 0, $headerSize);
        $resBody = substr($result, $headerSize);

        $response = $response->status($httpCode);
        $isHtml = false;
        foreach (explode("\r\n", rtrim($resHeaders)) as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = strtolower(trim($key));
                if ($key === 'content-type' && str_contains(strtolower($value), 'text/html')) $isHtml = true;
                if (!in_array($key, ['transfer-encoding', 'connection', 'content-length', 'content-encoding'])) {
                    $response->header($key, trim($value));
                }
            }
        }

        if ($isHtml && !empty($this->additionalTags)) {
            $tagsString = implode("\n", $this->additionalTags) . "\n";
            $resBody = stripos($resBody, '</head>') !== false ? str_ireplace('</head>', $tagsString . '</head>', $resBody) : $resBody . $tagsString;
        }

        return $response->send($resBody);
    }

    protected function getMimeType(string $path): string
    {
        $cleanPath = preg_replace('/\.(gz|br)$/', '', $path);
        $extension = pathinfo($cleanPath, PATHINFO_EXTENSION);
        $mimes = ['html' => 'text/html', 'js' => 'application/javascript', 'css' => 'text/css', 'json' => 'application/json', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'webp' => 'image/webp', 'woff' => 'font/woff', 'woff2'=> 'font/woff2'];
        return $mimes[$extension] ?? 'application/octet-stream';
    }

    protected function resolveCachePolicy(string $path): string
    {
        $extension = strtolower(pathinfo(preg_replace('/\.(gz|br)$/', '', $path), PATHINFO_EXTENSION));
        return $extension === 'html' ? 'no-cache, no-store, must-revalidate' : 'public, max-age=31536000, immutable';
    }

    protected function buildEtag(string $path): string
    {
        $stat = @stat($path);
        return $stat ? '"' . sha1($path . '|' . $stat['mtime'] . '|' . $stat['size']) . '"' : '"0"';
    }

    protected function getRequestHeader(Request $request, string $name): ?string
    {
        foreach ($request->getHeaders() as $key => $value) {
            if (strcasecmp($key, $name) === 0) return is_array($value) ? implode(', ', $value) : $value;
        }
        return null;
    }
}