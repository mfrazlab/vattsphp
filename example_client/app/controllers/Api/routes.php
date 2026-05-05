<?php

namespace App\controllers\Api;

use App\controllers\Api\NodesHelper;
use App\controllers\Api\Users\Servers\ServerStartupApiController;
use App\controllers\Api\Users\UsersApiController;

class ApiRoutes
{
    public static function setup(\Vatts\Router\Router $router)
    {
        $router->group(["prefix" => "/nodes/helper"], function (\Vatts\Router\Router $router) {
            $router->post('/admin-permission', [NodesHelper::class, 'isAdmin']);
            $router->post('/permission', [NodesHelper::class, 'permission']);
            $router->post("/verify-sftp", [NodesHelper::class, 'verifysftp']);
        });

        $router->group(["prefix" => '/v1/users', 'middleware' => 'api'], function (\Vatts\Router\Router $router) {
            $router->get('/servers', [\App\controllers\Api\Users\UsersApiController::class, 'getServers']);

            $router->group(["middleware" => 'server', 'prefix' => '/server'], function (\Vatts\Router\Router $router) {
                $router->get('/[server_id]/status', [\App\controllers\Api\Users\UsersApiController::class, 'getStatus']);
                $router->get('/[server_id]', [\App\controllers\Api\Users\UsersApiController::class, 'getServer']);
                $router->post('/[server_id]/action', [\App\controllers\Api\Users\UsersApiController::class, 'sendAction']);
                $router->get('/[server_id]/action/[action]', [\App\controllers\Api\Users\UsersApiController::class, 'sendAction']);

                // modificações
                $router->post("/[server_id]/config", [UsersApiController::class, 'saveNameAndDesc']);

                // startup
                $router->get("/[server_id]/startup", [ServerStartupApiController::class, 'getCoreInfo']);
                $router->post("/[server_id]/startup/docker", [ServerStartupApiController::class, 'saveDockerImage']);
                $router->post("/[server_id]/startup/variable", [ServerStartupApiController::class, 'saveVariable']);

                $router->get("/[server_id]/allocations", [UsersApiController::class, 'getAdditionalAllocations']);
                $router->post("/[server_id]/allocations/add", [UsersApiController::class, 'addAdditionalAllocation']);
                $router->post("/[server_id]/allocations/remove", [UsersApiController::class, 'removeAdditionalAllocation']);
            });

        });
    }
}