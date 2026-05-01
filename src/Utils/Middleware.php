<?php

namespace Vatts\Utils;

use Vatts\Router\Request;
use Vatts\Router\Response;

/**
 * Classe abstrata para middlewares do Vatts.
 * Suporta parsing e transformação de dados que são passados para controllers.
 */
abstract class Middleware
{
    // Cada middleware do projeto cliente deve definir um nome estático único
    public static string $name = '';

    /**
     * Método principal do middleware. Recebe a request e a response.
     * Pode modificar/parsear dados e retornar a Request para continuar,
     * ou retornar uma Response para interromper o fluxo (ex: redirecionamento ou erro).
     */
    abstract public function handle(Request $request, Response $response): Request|Response;
}