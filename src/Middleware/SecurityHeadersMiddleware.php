<?php

namespace Vatts\Middleware;

use Vatts\Router\Request;
use Vatts\Router\Response;
use Vatts\Utils\Middleware;

class SecurityHeadersMiddleware extends Middleware
{
    public static string $name = 'security';

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function handle(Request $request, Response $response): Request|Response
    {
        if (!($this->config['enabled'] ?? true)) {
            return $request;
        }

        $this->setHeaderIfMissing($response, 'X-Content-Type-Options', $this->config['x_content_type_options'] ?? 'nosniff');
        $this->setHeaderIfMissing($response, 'X-Frame-Options', $this->config['x_frame_options'] ?? 'DENY');
        $this->setHeaderIfMissing($response, 'X-XSS-Protection', $this->config['x_xss_protection'] ?? '0');
        $this->setHeaderIfMissing($response, 'Referrer-Policy', $this->config['referrer_policy'] ?? 'no-referrer');
        $this->setHeaderIfMissing($response, 'Permissions-Policy', $this->config['permissions_policy'] ?? 'geolocation=(), microphone=(), camera=()');
        $this->setHeaderIfMissing($response, 'X-Permitted-Cross-Domain-Policies', 'none');

        $coop = $this->config['cross_origin_opener_policy'] ?? 'same-origin';
        $corp = $this->config['cross_origin_resource_policy'] ?? 'same-origin';
        $coep = $this->config['cross_origin_embedder_policy'] ?? null;
        if (!empty($coop)) $this->setHeaderIfMissing($response, 'Cross-Origin-Opener-Policy', $coop);
        if (!empty($corp)) $this->setHeaderIfMissing($response, 'Cross-Origin-Resource-Policy', $corp);
        if (!empty($coep)) $this->setHeaderIfMissing($response, 'Cross-Origin-Embedder-Policy', $coep);

        $csp = $this->config['csp'] ?? null;
        if (!empty($csp)) {
            $cspHeader = ($this->config['csp_report_only'] ?? false) ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy';
            $this->setHeaderIfMissing($response, $cspHeader, $csp);
        }

        $hsts = $this->config['hsts'] ?? [];
        if (($hsts['enabled'] ?? true) && ($this->isHttps() || !($hsts['require_https'] ?? true))) {
            $maxAge = (int) ($hsts['max_age'] ?? 31536000);
            $hstsValue = 'max-age=' . $maxAge;
            if (($hsts['include_subdomains'] ?? true)) {
                $hstsValue .= '; includeSubDomains';
            }
            if (!empty($hsts['preload'])) {
                $hstsValue .= '; preload';
            }
            $this->setHeaderIfMissing($response, 'Strict-Transport-Security', $hstsValue);
        }

        return $request;
    }

    protected function setHeaderIfMissing(Response $response, string $header, string $value): void
    {
        foreach ($response->getHeaders() as $key => $_) {
            if (strcasecmp($key, $header) === 0) {
                return;
            }
        }
        $response->header($header, $value);
    }

    protected function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }
}

