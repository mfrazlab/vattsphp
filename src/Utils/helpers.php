<?php

/**
 * VattsPHP Global Helpers
 * Auto-carregado pelo composer.json
 */

if (!function_exists('vatts_action')) {
    /**
     * Helper global para resolver "Controller@method" string em callable
     * Requer que $GLOBALS['vatts_instance'] seja definida por Vatts\Vatts\Vatts::init()
     */
    function vatts_action(string $action): callable
    {
        if (!isset($GLOBALS['vatts_instance'])) {
            throw new \RuntimeException(
                'Vatts instance não foi registrada globalmente. ' .
                'Chame Vatts\Vatts\Vatts::init() antes de usar este helper.'
            );
        }
        return $GLOBALS['vatts_instance']->action($action);
    }
}

if (!function_exists('action')) {
    /**
     * Alias curto para vatts_action()
     */
    function action(string $action): callable
    {
        return vatts_action($action);
    }
}

