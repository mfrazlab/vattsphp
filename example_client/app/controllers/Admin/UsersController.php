<?php

namespace App\controllers\Admin;

use models\User;
use Vatts\Router\Request;
use Vatts\Router\Response;

class UsersController
{
    /**
     * Busca o usuário pelo ID
     */
    private function getUser(string $id): ?User
    {
        return User::get("id", $id);
    }


    /**
     * Verifica se o usuário pode ser deletado
     */
    private function canDelete(?User $user, $currentUser): bool
    {
        if (!$user || !$currentUser) {
            return false;
        }

        // Um usuário não pode se deletar
        if ($currentUser->id === $user->id) {
            return false;
        }

        // Verificar se o usuário a ser deletado é o único admin restante
        if ($user->role === 'admin') {
            $adminCount = count(User::where('role', 'admin'));
            if ($adminCount <= 1) {
                return false;
            }
        }

        // TODO: verificar servidores dps

        return true;
    }

    /**
     * Centraliza os dados repetitivos passados para as views
     */
    private function getViewData(Request $request, string $title, array $extraParams = []): array
    {
        $baseData = [
            'title'         => $title,
            'page_category' => 'admin',
            'page_name'     => 'users_list',
            'user'          => $request->getParsed('user'),
            'backTo'        => '/users',
        ];

        return array_merge($baseData, $extraParams);
    }

    /**
     * Centraliza as validações para Create e Edit
     */
    private function validateUserData(array $body, ?User $currentUser = null): ?string
    {
        $email      = $body['email'] ?? null;
        $username   = $body['name'] ?? null;
        $first_name = $body['first_name'] ?? null;
        $last_name  = $body['last_name'] ?? null;
        $role       = $body['role'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) return "O email precisa ser um email válido.";
        if (!$username) return "O nome de usuário é obrigatório.";
        if (!$first_name || !$last_name) return "Nome e sobrenome são obrigatórios.";
        if (!$role) return "O cargo (role) deve ser selecionado.";

        // Validar espaços e maiúsculas
        if (preg_match('/\s/', $username)) return "O nome de usuário não pode conter espaços.";
        if (preg_match('/[A-Z]/', $username)) return "O nome de usuário não pode conter letras maiúsculas.";

        // Verifica se o email já existe
        $existingEmailUser = User::get('email', $email);
        if ($existingEmailUser && (!$currentUser || $existingEmailUser->id !== $currentUser->id)) {
            return "O email fornecido já está em uso por outro usuário.";
        }

        // Verifica se o nome de usuário já existe
        $existingUsernameUser = User::get('name', $username);
        if ($existingUsernameUser && (!$currentUser || $existingUsernameUser->id !== $currentUser->id)) {
            return "O nome de usuário fornecido já está em uso por outro usuário.";
        }

        return null; // Sem erros
    }

    // ==============================================================================
    // ACTIONS DA CONTROLLER
    // ==============================================================================

    public function viewAll(Request $request, Response $response): Response
    {
        $users = User::all();

        $map = [
            ['label' => 'Identificador', 'key' => 'id', 'type' => 'text'],
            ['label' => 'Usuário', 'key' => 'name', 'type' => 'user'],
            ['label' => 'Data de Criação', 'key' => 'created_at', 'type' => 'date'],
        ];

        $viewData = [
            'resources' => $users,
            'map'       => $map,
            'see'       => 'users/[id]/edit',
            'create'    => 'users/create',
            'delete'    => 'users/[id]/delete?return=all'
        ];

        return $response->view('resources.view_resources', $this->getViewData($request, 'Usuários', $viewData));
    }

    public function viewEdit(Request $request, Response $response): Response
    {
        $user = $this->getUser($request->getParam("user"));

        if (!$user) {
            return $response->view("resources.resource_not_found", ['title' => 'usuário']);
        }

        $viewData = [
            'resource'  => $user,
            'map'       => $user->view_map,
            'canDelete' => $this->canDelete($user, $request->getParsed('user')),
            'deleteUrl' => 'users/[id]/delete?return=edit'
        ];

        return $response->view('resources.edit_create', $this->getViewData($request, "Usuário - {$user->first_name} {$user->last_name}", $viewData));
    }

    public function edit(Request $request, Response $response): Response
    {
        $user = $this->getUser($request->getParam("user"));

        if (!$user) {
            return $response->view("resources.resource_not_found", ['title' => 'usuário']);
        }

        $body = $request->getBody();
        $error = $this->validateUserData($body, $user);

        // Se houve erro, retorna a view com o erro (aqui mantemos view para preservar o input do usuário)
        if ($error) {
            return $response->setFlash(['error' => $error])
                ->redirect("/admin/users/{$user->id}/edit");
        }

        // Atualiza e salva
        $user->name       = $body['name'];
        $user->email      = $body['email'];
        $user->first_name = $body['first_name'];
        $user->last_name  = $body['last_name'];
        $user->role       = $body['role'];

        if (!empty($body['password'])) {
            $user->password = password_hash($body['password'], PASSWORD_DEFAULT);
        }

        $user->save();

        // Redireciona com flash message de sucesso
        return $response->setFlash(['success' => 'O usuário foi editado com sucesso!'])
            ->redirect("/admin/users/{$user->id}/edit");
    }

    public function viewCreate(Request $request, Response $response): Response
    {
        $user = new User();
        $map = $user->view_map;
        $map["Segurança"] = [['label' => 'Senha', 'key' => 'password', 'type' => 'password', 'desc' => '']];

        return $response->view('resources.edit_create', $this->getViewData($request, 'Usuário', [
            'map'       => $map,
            'see'       => 'users/[id]/edit',
            'canDelete' => false,
            'deleteUrl' => 'users/[id]/delete?return=edit'
        ]));
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getBody();
        $error = $this->validateUserData($body);

        // Validação extra apenas para o Create
        if (!$error && empty($body['password'])) {
            $error = "A senha é obrigatória.";
        }

        $user = new User();

        if ($error) {
            return $response->setFlash(['error' => $error])
                ->redirect("/admin/users/create");
        }

        // Popula e salva
        $user->name       = $body['name'];
        $user->email      = $body['email'];
        $user->first_name = $body['first_name'];
        $user->last_name  = $body['last_name'];
        $user->role       = $body['role'];
        $user->password   = password_hash($body['password'], PASSWORD_DEFAULT);
        $user->save();

        // Redireciona para a página de edição do novo usuário com sucesso
        return $response->setFlash(['success' => 'O usuário foi criado com sucesso!'])
            ->redirect("/admin/users/{$user->id}/edit");
    }

    public function delete(Request $request, Response $response): Response
    {
        $userId = $request->getParam("user");
        $user = $this->getUser($userId);
        $currentUser = $request->getParsed('user');

        $baseRoute = '/admin/users';

        if (!$user) {
            return $response->setFlash(['error' => 'Usuário não encontrado'])
                ->redirect($baseRoute);
        }

        if (!$this->canDelete($user, $currentUser)) {
            $returnTo = $request->getQuery()['return'] ?? 'edit';
            $errorMsg = 'Não é possível excluir este usuário.';

            if ($returnTo === 'all') {
                $redirect = $baseRoute;
            } else {
                $redirect = "{$baseRoute}/{$userId}/edit";
            }

            return $response->setFlash(['error' => $errorMsg])
                ->redirect($redirect);
        }

        $user->delete();

        $successMsg = 'O usuário foi excluído com sucesso!';

        return $response->setFlash(['success' => $successMsg])
            ->redirect($baseRoute);
    }
}