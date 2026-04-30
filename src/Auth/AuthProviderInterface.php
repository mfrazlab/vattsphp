<?php

namespace Vatts\Auth;

use Vatts\Router\Request;

interface AuthProviderInterface
{
    /** @return string Retorna o ID do provider (ex: 'credentials', 'google') */
    public function getId(): string;

    /**
     * @param array $credentials Dados enviados pelo cliente
     * @param Request $req A requisição atual
     * @return array|string|null Pode retornar os dados do User (array), uma URL de redirecionamento (string) ou null em caso de erro.
     */
    public function handleSignIn(array $credentials, Request $req);

    /** @return array Configurações públicas do provider para o Front-end */
    public function getConfig(): array;

    /** @return array Rotas adicionais específicas do provider (ex: callbacks de OAuth) */
    public function getAdditionalRoutes(): array;
}