<?php

/** @var \Vatts\Router\Router $app */
/** @var \Vatts\Vatts $v */
use Vatts\Router\Request;
use Vatts\Router\Response;
use Vatts\Handlers\FrontendHandler;
use Vatts\Utils\BladeConfig;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


BladeConfig::init(__DIR__ . '/../src/views', __DIR__ . '/../t-cache');
// GET /hello/[name]
$app->get('/hello/[name]', function (Request $request, Response $response) use ($v) {
    $callable = $v->action('HomeController@hello');
    return $callable($request, $response);
});

$app->group(["prefix" => '/api'], function (\Vatts\Router\Router $router) {
    \App\controllers\Api\ApiRoutes::setup($router);
});

$app->group(["prefix" => "/admin", "middleware" => "admin"], function (\Vatts\Router\Router $router) {
    $router->get("/users", [\App\controllers\Admin\UsersController::class, 'viewAll']);
    $router->get("/users/create", [\App\controllers\Admin\UsersController::class, 'viewCreate']);
    $router->post("/users/create", [\App\controllers\Admin\UsersController::class, 'create']);
    $router->post("/users/[user]/edit", [\App\controllers\Admin\UsersController::class, 'edit']);
    $router->get("/users/[user]/edit", [\App\controllers\Admin\UsersController::class, 'viewEdit']);
    $router->get('/users/[user]/delete', [\App\controllers\Admin\UsersController::class, 'delete']);

    // Nodes CRUD
    $router->get("/nodes", [\App\controllers\Admin\NodesController::class, 'viewAll']);
    $router->get('/nodes/status', [\App\controllers\Admin\NodesController::class, 'getStatus']);
    $router->get("/nodes/create", [\App\controllers\Admin\NodesController::class, 'viewCreate']);
    $router->post("/nodes/create", [\App\controllers\Admin\NodesController::class, 'create']);
    $router->post("/nodes/[node]/edit", [\App\controllers\Admin\NodesController::class, 'edit']);
    $router->get("/nodes/[node]/edit", [\App\controllers\Admin\NodesController::class, 'viewEdit']);
    $router->get('/nodes/[node]/delete', [\App\controllers\Admin\NodesController::class, 'delete']);

    $router->post('/nodes/[node]/allocations', [\App\controllers\Admin\NodesController::class, 'createAllocation']);

    $router->post('/nodes/[node]/allocations/aliases', [\App\controllers\Admin\NodesController::class, 'updateAllocationAlias']);
    $router->get('/nodes/[node]/allocations/[allocation]/delete', [\App\controllers\Admin\NodesController::class, 'deleteAllocation']);
    // Cores CRUD
    $router->get('/cores', [\App\controllers\Admin\CoreController::class, 'viewAll']);
    $router->get('/cores/create', [\App\controllers\Admin\CoreController::class, 'viewCreate']);
    $router->post('/cores/create', [\App\controllers\Admin\CoreController::class, 'create']);
    $router->post('/cores/[core]/edit', [\App\controllers\Admin\CoreController::class, 'edit']);
    $router->get('/cores/[core]/edit', [\App\controllers\Admin\CoreController::class, 'viewEdit']);
    $router->get('/cores/[core]/delete', [\App\controllers\Admin\CoreController::class, 'delete']);
    $router->get('/cores/[core]/export', [\App\controllers\Admin\CoreController::class, 'exportJson']);
    $router->post('/cores/[core]/import', [\App\controllers\Admin\CoreController::class, 'importJson']);
    // Servers CRUD
    $router->get('/servers', [\App\controllers\Admin\ServerController::class, 'viewAll']);
    $router->get('/servers/create', [\App\controllers\Admin\ServerController::class, 'viewCreate']);
    $router->post('/servers/create', [\App\controllers\Admin\ServerController::class, 'create']);
    $router->post('/servers/[server]/edit', [\App\controllers\Admin\ServerController::class, 'edit']);
    $router->get('/servers/[server]/edit', [\App\controllers\Admin\ServerController::class, 'viewEdit']);
    $router->get('/servers/[server]/delete', [\App\controllers\Admin\ServerController::class, 'delete']);

    // Internal admin API used by the dynamic partials (user search, nodes list, cores list)
    $router->get('/api/users/search', [\App\controllers\Admin\ServerController::class, 'apiUsersSearch']);
    $router->get('/api/nodes/list', [\App\controllers\Admin\ServerController::class, 'apiNodesList']);
    $router->get('/api/cores/list', [\App\controllers\Admin\ServerController::class, 'apiCoresList']);
    $router->get('/api/allocations/list', [\App\controllers\Admin\ServerController::class, 'apiAllocationsList']);
});





$auth = require __DIR__ . '/Auth.php';
$auth->registerRoutes($app);
