<?php

namespace App\controllers\Api\Users\Servers;

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use models\Core;
use Vatts\Router\Request;
use Vatts\Router\Response;
use Rakit\Validation\Validator;

class ServerStartupApiController
{

    public static function getCoreInfo(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Invalid server.'
            ])->status(400);
        }
        $core = Core::get('id', $server->coreId);
        if(!$core) {
            return $response->json([
                'error' => 'Invalid core.'
            ])->status(400);
        }

        unset($core->view_map);
        return $response->json([
            'core' => $core,
        ]);
    }

    /**
     * Atualiza apenas a Imagem Docker do Servidor
     */
    public static function saveDockerImage(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Servidor inválido.'
            ])->status(400);
        }

        $core = Core::get('id', $server->coreId);
        if (!$core) {
            return $response->json([
                'error' => 'Core inválido ou não encontrado.'
            ])->status(400);
        }

        $body = $request->getBody();
        $dockerImage = $body['dockerImage'] ?? null;

        if ($dockerImage === null) {
            return $response->json([
                'error' => 'A imagem Docker é obrigatória.'
            ])->status(400);
        }

        $allowedImages = json_decode($core->dockerImages ?? '[]', true);
        $isValidImage = false;

        foreach ($allowedImages as $img) {
            if (isset($img['image']) && $img['image'] === $dockerImage) {
                $isValidImage = true;
                break;
            }
        }

        if (!$isValidImage) {
            return $response->json([
                'error' => 'A imagem Docker selecionada não é permitida para este Core.'
            ])->status(400);
        }

        $server->dockerImage = $dockerImage;
        $server->save();

        return $response->json([
            'success' => true,
            'message' => 'Imagem Docker atualizada com sucesso.'
        ]);
    }

    /**
     * Atualiza uma variável de ambiente específica
     */
    public static function saveVariable(Request $request, Response $response): Response
    {
        $server = $request->getParsed('server');
        if (!($server instanceof \models\Server)) {
            return $response->json([
                'error' => 'Servidor inválido.'
            ])->status(400);
        }

        $core = Core::get('id', $server->coreId);
        if (!$core) {
            return $response->json([
                'error' => 'Core inválido ou não encontrado.'
            ])->status(400);
        }

        $body = $request->getBody();
        $envKey = $body['key'] ?? null;
        $envValue = $body['value'] ?? null;

        if ($envKey === null || $envValue === null) {
            return $response->json([
                'error' => 'Chave e valor da variável são obrigatórios.'
            ])->status(400);
        }

        $coreVars = json_decode($core->variables ?? '[]', true);
        $varDef = null;

        // Busca a regra da variável enviada
        foreach ($coreVars as $v) {
            if (isset($v['envVariable']) && $v['envVariable'] === $envKey) {
                $varDef = $v;
                break;
            }
        }

        if (!$varDef) {
            return $response->json([
                'error' => 'Variável de ambiente não encontrada nas configurações originais deste Core.'
            ])->status(400);
        }

        // Instancia o validador do Rakit
        $validator = new Validator([
            'required' => ':attribute é obrigatório.',
            'numeric'  => ':attribute deve ser um número.',
            'min'      => ':attribute deve ser no mínimo :min.',
            'max'      => ':attribute não pode ser maior que :max.',
            'in'       => ':attribute deve ser um dos seguintes valores: :allowed_values.',
            'regex'    => ':attribute está com um formato inválido.'
        ]);

        // Monta o array de validação baseado nas regras do Core
        $rawRules = explode('|', $varDef['rules'] ?? '');

        // CORREÇÃO AQUI: Remove 'default:' e 'string' do array de validação
        $filteredRules = array_filter($rawRules, fn($r) => !str_starts_with($r, 'default:') && $r !== 'string');

        $finalRuleString = implode('|', $filteredRules);

        // Executa a validação
        $validation = $validator->make(
            ['value' => $envValue], // Dados
            ['value' => $finalRuleString] // Regras
        );

        // Define o nome amigável para o erro
        $validation->setAliases([
            'value' => $varDef['name'] ?? 'Variável'
        ]);

        $validation->validate();

        if ($validation->fails()) {
            return $response->json([
                'error' => $validation->errors()->first('value')
            ])->status(400);
        }

        // Se passou na validação, atualiza o JSON de envVars do Servidor
        $currentEnvVars = json_decode($server->envVars ?? '{}', true);
        $currentEnvVars[$envKey] = (string)$envValue;

        $server->envVars = json_encode($currentEnvVars);
        $server->save();

        return $response->json([
            'success' => true,
            'message' => 'Variável atualizada com sucesso.'
        ]);
    }
}