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
            return $this->serveSPA($request, $response);
        }

        return $this->proxyToDevServer($request, $response);
    }

    /**
     * Serve a SPA em produção (index.html)
     */
    protected function serveSPA(Request $request, Response $response): Response
    {
        $exportedPath = $this->projectPath . DIRECTORY_SEPARATOR . 'exported' . DIRECTORY_SEPARATOR . 'index.html';

        if (!is_file($exportedPath)) {
            return $response->status(404)->html('<h1>404 - SPA not found</h1>');
        }

        $html = file_get_contents($exportedPath);
        return $response->header('Content-Type', 'text/html')->send($html);
    }

    /**
     * Faz proxy para o servidor de desenvolvimento (Vite, Next.js, etc)
     */
    protected function proxyToDevServer(Request $request, Response $response): Response
    {
        $path = $request->getPath();
        $devServerUrl = 'http://localhost:' . $this->devServerPort . $path;

        // Adiciona query string
        $query = $request->getQuery();
        if (!empty($query)) {
            $devServerUrl .= '?' . http_build_query($query);
        }

        $ch = curl_init($devServerUrl);

        // 1. Repassar Headers corretamente
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            // Ignorar Host para não confundir o server de destino
            if (strtolower($name) !== 'host') {
                $headers[] = $name . ': ' . (is_array($values) ? implode(', ', $values) : $values);
            }
        }

        // 2. Configurações do cURL
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request->getMethod());

        // Timeouts para não bloquear o servidor em HMR/Live Reload
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

        // 3. Repassar o Body (se houver)
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $body = $request->getBody();
            if (is_array($body)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        }

        // 4. Executar
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);

            return $response->status(502)->html(
                '<h1>502 Bad Gateway</h1>' .
                '<p>O frontend não está rodando ou excedeu o tempo.</p>' .
                '<p>Detalhes: ' . htmlspecialchars($error) . '</p>' .
                '<p>Certifique-se que o dev server está rodando em localhost:' . $this->devServerPort . '</p>'
            );
        }

        // 5. Separar Headers da Resposta (Imagens, JS, CSS, etc)
        $resHeaders = substr($result, 0, $headerSize);
        $resBody = substr($result, $headerSize);
        curl_close($ch);

        $response = $response->status($httpCode);

        // Passa headers da resposta do dev server
        $headerLines = explode("\r\n", rtrim($resHeaders));
        foreach ($headerLines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $key = trim($key);
                // Não repassamos Transfer-Encoding para não bugar
                if (strtolower($key) !== 'transfer-encoding' && strtolower($key) !== 'connection') {
                    $response->header($key, trim($value));
                }
            }
        }

        // 6. Escrever o corpo (funciona com binários e imagens)
        return $response->send($resBody);
    }
}

