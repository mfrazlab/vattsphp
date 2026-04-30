<?php

namespace Vatts\Utils;

use Vatts\Router\Request;

/**
 * Classe abstrata para middlewares do Vatts.
 * Suporta parsing e transformação de dados que são passados para controllers.
 */
abstract class Middleware
{
    // Cada middleware do projeto cliente deve definir um nome estático único
    public static string $name = '';

    /**
     * Método principal do middleware. Recebe a request e pode modificar/parsear dados.
     * Retorna a request (modificada ou não).
     *
     * Para passar dados parsados para o controller:
     * $request->setParsed('chave', $valor_parsado);
     */
    abstract public function handle(Request $request): Request;
}

