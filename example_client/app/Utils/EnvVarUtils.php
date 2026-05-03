<?php

namespace App\Utils;

/**
 * Utilitário para aplicar defaults e validar variáveis de ambiente
 * definidas no Core (campo JSON `variables`).
 *
 * Formato esperado em cada variável:
 *  - name (string)
 *  - description (string)
 *  - envVariable (string)  // chave final
 *  - rules (string)        // ex: required|regex:/^(...)/|default:server.jar
 */
class EnvVarUtils
{
    /**
     * @param array $definitions Lista de variáveis (decoded do JSON do Core)
     * @param array $input       Valores recebidos do form (ex: $_POST['env'])
     *
     * @return array<string, string> Mapa final ENV_KEY => value
     */
    public static function validateAndApplyDefaults(array $definitions, array $input): array
    {
        $out = [];

        foreach ($definitions as $def) {
            if (!is_array($def)) {
                continue;
            }

            $key = trim((string)($def['envVariable'] ?? ''));
            if ($key === '') {
                continue;
            }

            $name = (string)($def['name'] ?? $key);
            $rulesRaw = (string)($def['rules'] ?? '');
            $rules = self::parseRules($rulesRaw);

            $value = $input[$key] ?? '';
            if (is_array($value) || is_object($value)) {
                $value = '';
            }
            $value = trim((string)$value);

            if ($value === '' && $rules['default'] !== null) {
                $value = (string)$rules['default'];
            }

            if ($rules['required'] && $value === '') {
                throw new \InvalidArgumentException("Variável obrigatória ausente: {$name} ({$key})");
            }

            if ($value !== '' && $rules['regex'] !== null) {
                $pattern = (string)$rules['regex'];
                // permite regex:/.../ (incluindo modificadores), ou apenas /^...$/
                $ok = @preg_match($pattern, $value);
                if ($ok !== 1) {
                    throw new \InvalidArgumentException("Valor inválido para {$name} ({$key}).");
                }
            }

            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array{required: bool, default: ?string, regex: ?string}
     */
    private static function parseRules(string $rules): array
    {
        $required = false;
        $default = null;
        $regex = null;

        $rules = trim($rules);
        if ($rules === '') {
            return [
                'required' => false,
                'default' => null,
                'regex' => null,
            ];
        }

        $parts = explode('|', $rules);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if ($part === 'required') {
                $required = true;
                continue;
            }

            if (str_starts_with($part, 'default:')) {
                $default = substr($part, strlen('default:'));
                continue;
            }

            if (str_starts_with($part, 'regex:')) {
                $regex = substr($part, strlen('regex:'));
                continue;
            }
        }

        return [
            'required' => $required,
            'default' => $default !== null ? (string)$default : null,
            'regex' => $regex !== null ? (string)$regex : null,
        ];
    }
}

