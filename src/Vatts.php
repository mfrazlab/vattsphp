<?php

namespace Vatts;

use Illuminate\Database\Capsule\Manager as Capsule;
use Vatts\Controllers\ControllerResolver;
use Vatts\Router\Router;
use Vatts\Router\Request;
use Vatts\Router\Response;
use Vatts\Rpc\RpcController;
use Vatts\Utils\Middleware;
use Vatts\Handlers\FrontendHandler;

class Vatts
{
    protected Router $router;
    protected ControllerResolver $resolver;
    protected string $projectPath;

    /**
     * Inicia o Vatts e retorna a instância do Router.
     * Config keys:
     * - project_path: caminho do projeto cliente (onde estão app/, public/, models/)
     * - db: array com configuração do Eloquent (optional)
     * - autoload_helpers: bool (default: true) - registra helpers globais
     */
    public static function init(array $config = []): Router
    {
        $self = new self($config['project_path'] ?? getcwd());

        // Registra instância globalmente para helpers
        $GLOBALS['vatts_instance'] = $self;

        // bootstrap DB se houver
        if (!empty($config['db']) && is_array($config['db'])) {
            $self->bootEloquent($config['db']);
        }

        $self->bootRouter();

        // auto-require models e controllers se configurado
        if ($config['autoload_models'] ?? true) {
            $self->autoloadModels();
        }
        if ($config['autoload_controllers'] ?? true) {
            $self->autoloadControllers();
        }

        // registra middlewares automáticos
        $self->registerMiddlewares();

        // registra rota RPC automaticamente
        $self->registerRpcRoute();

        // registra fallback para frontend (prod/dev) automaticamente
        $self->registerFrontendFallback();

        // carrega as rotas do projeto cliente, se existir
        $routesFile = $self->projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'routes.php';
        if (is_file($routesFile)) {
            // expõe helper $app para as rotas (é o router agora)
            $app = $self->router;
            $v = $self;
            require $routesFile;
        }

        return $self->router;
    }

    public function __construct(string $projectPath)
    {
        $this->projectPath = rtrim($projectPath, "\\/ ");

        // Registra as variáveis de ambiente do .env antes de qualquer outra coisa
        $this->loadEnv();

        $this->resolver = new ControllerResolver($this->projectPath);
    }

    /**
     * Carrega e registra as variáveis do arquivo .env
     */
    protected function loadEnv(): void
    {
        $path = $this->projectPath . DIRECTORY_SEPARATOR . '.env';

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Pula comentários
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            // Divide apenas no primeiro '='
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove aspas se existirem
                $value = trim($value, "\"'");

                // Define se ainda não existir
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }

    protected function bootRouter(): void
    {
        // cria router próprio do Vatts
        $this->router = new Router();
    }

    protected function bootEloquent(array $dbConfig): void
    {
        $capsule = new Capsule();
        $capsule->addConnection($dbConfig);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }

    protected function registerMiddlewares(): void
    {
        $dir = $this->projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'middlewares';
        if (!is_dir($dir)) {
            return;
        }

        $before = get_declared_classes();
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') as $file) {
            require_once $file;
        }
        $after = get_declared_classes();

        $new = array_diff($after, $before);
        foreach ($new as $class) {
            if (is_subclass_of($class, Middleware::class)) {
                // o middleware deve definir uma propriedade estática $name
                $name = $class::$name ?? null;
                if (!$name) {
                    continue;
                }

                $instance = new $class();
                // Registra como middleware global no router
                $this->router->use(function (Request $request) use ($instance) {
                    return $instance->handle($request);
                });
            }
        }
    }

    protected function autoloadModels(): void
    {
        // Alterado de 'models' para 'src/models'
        $dir = $this->projectPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'models';
        if (!is_dir($dir)) {
            return;
        }

        $before = get_declared_classes();
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') as $file) {
            require_once $file;
        }
        $after = get_declared_classes();

        $newClasses = array_diff($after, $before);
        foreach ($newClasses as $class) {
            if (is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                $this->syncModelSchema($class);
            }
        }
    }

    /**
     * Sincroniza o schema do banco de dados com a propriedade estática $schema do Model.
     * Cria a tabela se não existir ou atualiza (adicionando/removendo colunas).
     */
    protected function syncModelSchema(string $modelClass): void
    {
        if (!property_exists($modelClass, 'schema')) {
            return; // Ignora se o model não tiver o array $schema definido
        }

        $instance = new $modelClass();
        $tableName = $instance->getTable();
        $schemaBuilder = Capsule::schema();
        $schemaDefinition = $modelClass::$schema;

        if (!$schemaBuilder->hasTable($tableName)) {
            // A tabela não existe: cria do zero
            $schemaBuilder->create($tableName, function ($table) use ($schemaDefinition, $instance) {
                $table->id();
                foreach ($schemaDefinition as $column => $type) {
                    $table->$type($column)->nullable(); // Colunas criadas como nullable por padrão para flexibilidade
                }
                if ($instance->usesTimestamps()) {
                    $table->timestamps();
                }
            });
        } else {
            // A tabela existe: verifica colunas modificadas, adicionadas ou removidas
            $currentColumns = $schemaBuilder->getColumnListing($tableName);

            $expectedColumns = array_keys($schemaDefinition);
            $expectedColumns[] = 'id';
            if ($instance->usesTimestamps()) {
                $expectedColumns[] = 'created_at';
                $expectedColumns[] = 'updated_at';
            }

            $toAdd = array_diff($expectedColumns, $currentColumns);
            $toDrop = array_diff($currentColumns, $expectedColumns);

            if (!empty($toAdd) || !empty($toDrop)) {
                $schemaBuilder->table($tableName, function ($table) use ($toAdd, $toDrop, $schemaDefinition) {
                    // Deleta as colunas que foram removidas do array $schema
                    foreach ($toDrop as $column) {
                        $table->dropColumn($column);
                    }

                    // Adiciona as novas colunas configuradas no array $schema
                    foreach ($toAdd as $column) {
                        if (isset($schemaDefinition[$column])) {
                            $type = $schemaDefinition[$column];
                            $table->$type($column)->nullable();
                        }
                    }
                });
            }
        }
    }

    protected function autoloadControllers(): void
    {
        $dir = $this->projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers';
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.php') as $file) {
            require_once $file;
        }
    }

    protected function registerRpcRoute(): void
    {
        // Registra automaticamente a rota POST /api/prpc para RPC
        $this->router->post('/api/prpc', function (Request $request, Response $response) {
            $rpc = new RpcController();
            return $rpc->handle($request, $response);
        });
    }

    protected function registerFrontendFallback(): void
    {
        // Registra automaticamente fallback para frontend (dev proxy ou prod serve)
        // Agora o getenv() vai funcionar pois o loadEnv() rodou no construtor
        $env = getenv('APP_ENV') ?: 'dev';
        $port = getenv('DEV_SERVER_PORT') ?: 3000;

        $handler = new FrontendHandler($this->projectPath, $env, (int) $port);
        $this->router->fallback($handler);
    }

    /**
     * Retorna um callable a partir de "Controller@method"
     */
    public function action(string $action): callable
    {
        return $this->resolver->resolve($action);
    }
}