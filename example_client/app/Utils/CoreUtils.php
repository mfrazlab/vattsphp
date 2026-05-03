<?php

namespace App\Utils;

use models\Core;

class CoreUtils
{
    private const JSON_ARRAY_FIELDS = [
        'dockerImages',
        'variables',
    ];

    private const JSON_OBJECT_FIELDS = [
        'configSystem',
        'startupParser',
    ];

    public static function defaults(): array
    {
        return [
            'startupCommand' => '',
            'stopCommand' => '',
            'dockerImages' => "[]",
            'startupParser' => "{}",
            'configSystem' => "{}",
            'variables' => "[]",
            'installScript' => '',
            'createdAt' => (int) round(microtime(true) * 1000),
        ];
    }

    public static function validatePayload(array $body): ?string
    {
        $name = trim((string) ($body['name'] ?? ''));
        $description = trim((string) ($body['description'] ?? ''));
        $creatorEmail = trim((string) ($body['creatorEmail'] ?? ''));

        if ($name === '') {
            return 'O nome do core é obrigatório.';
        }

        if ($description === '') {
            return 'A descrição do core é obrigatória.';
        }

        if ($creatorEmail === '' || !filter_var($creatorEmail, FILTER_VALIDATE_EMAIL)) {
            return 'O e-mail do criador precisa ser válido.';
        }

        foreach (array_merge(self::JSON_ARRAY_FIELDS, self::JSON_OBJECT_FIELDS) as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }

            $raw = trim((string) $body[$field]);
            if ($raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return sprintf('O campo %s precisa conter um JSON válido.', $field);
            }

            if (!is_array($decoded)) {
                return sprintf('O campo %s precisa conter um JSON em formato de objeto ou lista.', $field);
            }
        }

        return null;
    }

    public static function normalizeForStorage(array $body, ?Core $existing = null): array
    {
        $existingData = $existing ? $existing->toArray() : [];
        $defaults = self::defaults();

        $data = array_merge($defaults, $existingData);

        $data['name'] = trim((string) ($body['name'] ?? $data['name'] ?? ''));
        $data['description'] = trim((string) ($body['description'] ?? $data['description'] ?? ''));
        $data['creatorEmail'] = trim((string) ($body['creatorEmail'] ?? $data['creatorEmail'] ?? ''));
        $data['startupCommand'] = trim((string) ($body['startupCommand'] ?? $data['startupCommand'] ?? ''));
        $data['stopCommand'] = trim((string) ($body['stopCommand'] ?? $data['stopCommand'] ?? ''));
        $data['installScript'] = self::normalizeScript($body['installScript'] ?? $data['installScript'] ?? '');
        $data['installImage'] = trim((string) ($body['installImage'] ?? $data['installImage'] ?? ''));
        $data['installEntrypoint'] = trim((string) ($body['installEntrypoint'] ?? $data['installEntrypoint'] ?? ''));
        $data['createdAt'] = isset($existingData['createdAt']) && $existingData['createdAt'] !== ''
            ? (int) $existingData['createdAt']
            : $data['createdAt'];

        foreach (self::JSON_ARRAY_FIELDS as $field) {
            $source = array_key_exists($field, $body) ? $body[$field] : ($existingData[$field] ?? $defaults[$field]);
            $data[$field] = self::normalizeJsonString($source, []);
        }

        foreach (self::JSON_OBJECT_FIELDS as $field) {
            $source = array_key_exists($field, $body) ? $body[$field] : ($existingData[$field] ?? $defaults[$field]);
            $data[$field] = self::normalizeJsonString($source, (object) []);
        }

        return $data;
    }

    public static function prepareForForm(Core $core): Core
    {
        $clone = clone $core;

        foreach (self::JSON_ARRAY_FIELDS as $field) {
            $clone->{$field} = self::prettyJsonForForm($clone->{$field} ?? '[]', []);
        }

        foreach (self::JSON_OBJECT_FIELDS as $field) {
            $clone->{$field} = self::prettyJsonForForm($clone->{$field} ?? '{}', (object) []);
        }

        $clone->installScript = self::normalizeScript($clone->installScript ?? '');

        return $clone;
    }

    public static function decodeArrayField(mixed $value, array $fallback = []): array
    {
        $decoded = self::decodeJson($value);
        return is_array($decoded) ? $decoded : $fallback;
    }

    public static function decodeObjectField(mixed $value, array $fallback = []): array
    {
        $decoded = self::decodeJson($value);
        return is_array($decoded) ? $decoded : $fallback;
    }

    private static function decodeJson(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private static function normalizeJsonString(mixed $value, mixed $fallback): string
    {
        if ($value === null) {
            $value = $fallback;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                $value = $fallback;
            } else {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = $decoded;
                } else {
                    return $trimmed;
                }
            }
        }

        if (!is_array($value) && !is_object($value)) {
            $value = $fallback;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function prettyJsonForForm(mixed $value, mixed $fallback): string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            return $trimmed;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return json_encode($fallback, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function normalizeScript(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $script = (string) $value;
        $script = str_replace(["\r\n", "\r"], "\n", $script);

        return trim($script, "\n");
    }
}

