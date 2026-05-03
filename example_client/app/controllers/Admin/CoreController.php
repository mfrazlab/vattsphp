<?php

namespace App\controllers\Admin;

require_once __DIR__ . '/../../utils/CoreUtils.php';
use models\Core;
use Vatts\Router\Request;
use Vatts\Router\Response;

class CoreController
{
    /**
     * Busca o core pelo ID
     */
    private function getCore(string $id): ?Core
    {
        return Core::get('id', $id);
    }

    /**
     * Verifica se o core pode ser deletado.
     */
    private function canDelete(?Core $core, $currentUser): bool
    {
        return (bool) $core;
    }

    /**
     * Dados base para as views geradas por esta controller
     */
    private function getViewData(Request $request, string $title, array $extraParams = []): array
    {
        $baseData = [
            'title'         => $title,
            'page_category' => 'admin',
            'page_name'     => 'cores_list',
            'user'          => $request->getParsed('user'),
            'backTo'        => '/cores',
        ];

        return array_merge($baseData, $extraParams);
    }

    /**
     * Validação básica dos campos do Core
     */
    private function validateCoreData(array $body): ?string
    {
        return \App\Utils\CoreUtils::validatePayload($body);
    }

    /**
     * Popula um model com os campos normalizados do form
     */
    private function fillCore(Core $core, array $data): void
    {
        foreach ($data as $key => $value) {
            $core->{$key} = $value;
        }
    }

    /**
     * Campos básicos para criação rápida do Core
     */
    private function getCreateMap(): array
    {
        return [
            'Informações Básicas' => [
                ['label' => 'Nome do Core', 'key' => 'name', 'type' => 'text', 'desc' => 'Nome único de identificação do core.', 'required' => true],
                ['label' => 'Descrição', 'key' => 'description', 'type' => 'textarea', 'desc' => 'Breve resumo sobre a finalidade deste core.', 'required' => true],
                ['label' => 'E-mail do Criador', 'key' => 'creatorEmail', 'type' => 'email', 'desc' => 'Identificação do autor original.', 'required' => true],
            ],
        ];
    }

    // ======================================================================
    // ACTIONS
    // ======================================================================

    public function viewAll(Request $request, Response $response): Response
    {
        $cores = Core::all();

        $map = [
            ['label' => 'Identificador', 'key' => 'id', 'type' => 'text'],
            ['label' => 'Nome', 'key' => 'name', 'type' => 'text'],
            ['label' => 'E-mail do Criador', 'key' => 'creatorEmail', 'type' => 'text'],
            ['label' => 'Criado em', 'key' => 'created_at', 'type' => 'date'],
        ];

        $viewData = [
            'resources' => $cores,
            'map'       => $map,
            'see'       => 'cores/[id]/edit',
            'create'    => 'cores/create',
            'delete'    => 'cores/[id]/delete?return=all',
        ];

        return $response->view('resources.view_resources', $this->getViewData($request, 'Cores', $viewData));
    }

    public function viewCreate(Request $request, Response $response): Response
    {
        return $response->view('resources.edit_create', $this->getViewData($request, 'Core', [
            'map'       => $this->getCreateMap(),
            'see'       => 'cores/[id]/edit',
            'canDelete' => false,
            'deleteUrl' => 'cores/[id]/delete?return=edit',
        ]));
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getBody();
        $error = $this->validateCoreData($body);

        if ($error) {
            return $response->setFlash(['error' => $error])
                ->redirect('/admin/cores/create');
        }

        $core = new Core();
        $this->fillCore($core, \App\Utils\CoreUtils::normalizeForStorage($body));
        $core->save();

        return $response->setFlash(['success' => 'O core foi criado com sucesso!'])
            ->redirect("/admin/cores/{$core->id}/edit");
    }

    public function viewEdit(Request $request, Response $response): Response
    {
        $core = $this->getCore($request->getParam('core'));

        if (!$core) {
            return $response->view('resources.resource_not_found', ['title' => 'core']);
        }

        $viewData = [
            'resource'      => \App\Utils\CoreUtils::prepareForForm($core),
            'map'           => $core->view_map,
            'canDelete'     => $this->canDelete($core, $request->getParsed('user')),
            'deleteUrl'     => 'cores/[id]/delete?return=edit',
            'custom_cards'  => ['partials.core.import_export'], // Adicionado o custom card aqui
        ];

        return $response->view('resources.edit_create', $this->getViewData($request, "Core - {$core->name}", $viewData));
    }

    public function edit(Request $request, Response $response): Response
    {
        $core = $this->getCore($request->getParam('core'));

        if (!$core) {
            return $response->view('resources.resource_not_found', ['title' => 'core']);
        }

        $body = $request->getBody();
        $error = $this->validateCoreData($body);

        if ($error) {
            return $response->setFlash(['error' => $error])
                ->redirect("/admin/cores/{$core->id}/edit");
        }

        $normalized = \App\Utils\CoreUtils::normalizeForStorage($body, $core);
        $this->fillCore($core, $normalized);
        $core->save();

        return $response->setFlash(['success' => 'O core foi atualizado com sucesso!'])
            ->redirect("/admin/cores/{$core->id}/edit");
    }

    public function delete(Request $request, Response $response): Response
    {
        $coreId = $request->getParam('core');
        $core = $this->getCore($coreId);

        $baseRoute = '/admin/cores';

        if (!$core) {
            return $response->setFlash(['error' => 'Core não encontrado'])->redirect($baseRoute);
        }

        if (!$this->canDelete($core, $request->getParsed('user'))) {
            $returnTo = $request->getQuery()['return'] ?? 'edit';
            $errorMsg = 'Não é possível excluir este core.';

            $redirect = $returnTo === 'all'
                ? $baseRoute
                : "{$baseRoute}/{$coreId}/edit";

            return $response->setFlash(['error' => $errorMsg])->redirect($redirect);
        }

        $core->delete();

        return $response->setFlash(['success' => 'O core foi excluído com sucesso!'])->redirect($baseRoute);
    }

// ======================================================================
    // IMPORT / EXPORT ACTIONS
    // ======================================================================

    public function exportJson(Request $request, Response $response): Response
    {
        $core = $this->getCore($request->getParam('core'));

        if (!$core) {
            return $response->setFlash(['error' => 'Core não encontrado para exportação.'])
                ->redirect('/admin/cores');
        }

        // Pega os dados do core, mas exclui campos do sistema/dinâmicos
        $exportData = [];
        $exclude = ['id', 'created_at', 'updated_at', 'view_map'];

        foreach ($core as $key => $value) {
            if (!in_array($key, $exclude)) {
                $exportData[$key] = $value;
            }
        }

        // Converte o model filtrado para JSON
        $jsonData = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="core_' . $core->id . '_export.json"');
        echo $jsonData;
        exit;
    }

    public function importJson(Request $request, Response $response): Response
    {
        $core = $this->getCore($request->getParam('core'));

        if (!$core) {
            return $response->setFlash(['error' => 'Core não encontrado para importação.'])
                ->redirect('/admin/cores');
        }

        if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
            return $response->setFlash(['error' => 'Nenhum arquivo válido foi enviado.'])
                ->redirect("/admin/cores/{$core->id}/edit");
        }

        $fileContent = file_get_contents($_FILES['json_file']['tmp_name']);
        $decodedData = json_decode($fileContent, true);

        if (!$decodedData) {
            return $response->setFlash(['error' => 'O arquivo enviado não é um JSON válido.'])
                ->redirect("/admin/cores/{$core->id}/edit");
        }

        // Ignora campos sistêmicos que não devem ser sobrescritos e mapeia diretamente
        $exclude = ['id', 'created_at', 'updated_at', 'view_map'];

        foreach ($decodedData as $key => $value) {
            if (!in_array($key, $exclude)) {
                // Preenche o model diretamente para evitar que o Utils perca campos
                $core->{$key} = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
            }
        }

        $core->save();

        return $response->setFlash(['success' => 'Dados importados e salvos com sucesso!'])
            ->redirect("/admin/cores/{$core->id}/edit");
    }
}