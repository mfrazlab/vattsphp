<?php

namespace Vatts\Utils;

use eftec\bladeone\BladeOne;

class BladeConfig
{
    protected static ?BladeOne $blade = null;

    /**
     * Inicializa o motor do Blade.
     * Chame isso no bootstrap da sua aplicação (ex: index.php).
     */
    public static function init(string $viewsPath, string $cachePath): void
    {
        self::$blade = new BladeOne($viewsPath, $cachePath, BladeOne::MODE_AUTO);
    }

    /**
     * Retorna a instância do Blade para ser usada na Response.
     */
    public static function get(): BladeOne
    {
        if (!self::$blade) {
            throw new \RuntimeException("BladeConfig::init() não foi chamado. Configure os caminhos das views primeiro.");
        }

        return self::$blade;
    }
}