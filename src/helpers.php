<?php

if (!function_exists('env')) {
    /**
     * Obtém o valor de uma variável de ambiente com suporte a fallback e tipagem.
     *
     * @param string $key Chave da variável
     * @param mixed|null $default Valor padrão caso a chave não exista
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower((string) $value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        // Remove aspas caso a variável tenha sido definida entre elas
        if (is_string($value) && preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
            return $matches[2];
        }

        return $value;
    }
}