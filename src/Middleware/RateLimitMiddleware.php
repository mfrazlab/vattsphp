<?php

namespace Vatts\Middleware;

use Vatts\Router\Request;
use Vatts\Router\Response;
use Vatts\Utils\Middleware;

class RateLimitMiddleware extends Middleware
{
    public static string $name = 'rate_limit';

    protected array $config;
    protected static array $memoryStore = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function handle(Request $request, Response $response): Request|Response
    {
        if (!($this->config['enabled'] ?? true)) {
            return $request;
        }

        $path = $request->getPath();
        if ($this->matchesAny($path, $this->config['exclude'] ?? [])) {
            return $request;
        }

        $clientKey = $this->resolveClientKey($request);
        if (in_array($clientKey, $this->config['allowlist'] ?? [], true)) {
            return $request;
        }

        $rule = $this->resolveRule($path);
        $max = (int) ($rule['max'] ?? 120);
        $window = (int) ($rule['window'] ?? 60);
        $bucket = $clientKey . ':' . $rule['key'] . ':' . (int) floor(time() / max(1, $window));

        $entry = $this->storeGet($bucket);
        if (!$entry || time() > ($entry['reset'] ?? 0)) {
            $entry = ['count' => 0, 'reset' => time() + $window];
        }

        $entry['count']++;
        $remaining = max(0, $max - $entry['count']);
        $this->storeSet($bucket, $entry, $window);

        $response->header('X-RateLimit-Limit', (string) $max);
        $response->header('X-RateLimit-Remaining', (string) $remaining);
        $response->header('X-RateLimit-Reset', (string) $entry['reset']);

        if ($entry['count'] > $max) {
            $retryAfter = max(1, $entry['reset'] - time());
            return $response
                ->status(429)
                ->header('Retry-After', (string) $retryAfter)
                ->json(['error' => 'Too Many Requests']);
        }

        return $request;
    }

    protected function resolveRule(string $path): array
    {
        $rules = $this->config['rules'] ?? [];
        foreach ($rules as $rule) {
            $pattern = $rule['pattern'] ?? null;
            if ($pattern && $this->matchPattern($path, $pattern)) {
                $rule['key'] = $rule['key'] ?? $pattern;
                return $rule;
            }
        }

        return [
            'key' => 'default',
            'max' => $this->config['max'] ?? 120,
            'window' => $this->config['window'] ?? 60,
        ];
    }

    protected function resolveClientKey(Request $request): string
    {
        $header = $this->config['proxy_header'] ?? 'X-Forwarded-For';
        $trustProxy = $this->config['trust_proxy'] ?? false;

        if ($trustProxy) {
            $value = $this->getHeader($request, $header);
            if (!empty($value)) {
                $parts = array_map('trim', explode(',', $value));
                if (!empty($parts[0])) {
                    return $parts[0];
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    protected function matchesAny(string $path, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->matchPattern($path, $pattern)) {
                return true;
            }
        }
        return false;
    }

    protected function matchPattern(string $path, string $pattern): bool
    {
        if (str_starts_with($pattern, '#')) {
            return preg_match($pattern, $path) === 1;
        }

        if (str_contains($pattern, '*')) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            return preg_match($regex, $path) === 1;
        }

        return $path === $pattern;
    }

    protected function getHeader(Request $request, string $name): ?string
    {
        foreach ($request->getHeaders() as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return is_array($value) ? implode(', ', $value) : $value;
            }
        }
        return null;
    }

    protected function storeGet(string $key): ?array
    {
        if (function_exists('apcu_fetch')) {
            $stored = apcu_fetch($key, $success);
            return $success ? $stored : null;
        }

        return self::$memoryStore[$key] ?? null;
    }

    protected function storeSet(string $key, array $value, int $ttl): void
    {
        if (function_exists('apcu_store')) {
            apcu_store($key, $value, $ttl);
            return;
        }

        self::$memoryStore[$key] = $value;
    }
}

