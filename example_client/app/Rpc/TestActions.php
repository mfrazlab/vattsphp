<?php

namespace App\Rpc;

use Vatts\Rpc\Attributes\Expose;
use Psr\Http\Message\ServerRequestInterface as Request;

class TestActions
{
    /**
     * Método exposto para ser chamado via RPC
     */
    #[Expose]
    public function greet(string $name): string
    {
        return "Olá, " . $name . "! (chamado via RPC)";
    }

    /**
     * Outro método exposto
     */
    #[Expose]
    public function sum(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * Método com Request injetado
     */
    #[Expose]
    public function getMethod(Request $request): string
    {
        return "Método HTTP: " . $request->getMethod();
    }

    /**
     * Método NÃO exposto (não marcado com #[Expose])
     */
    public function secretMethod(): string
    {
        return "Isto é secreto";
    }
}

