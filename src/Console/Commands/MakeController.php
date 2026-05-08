<?php

namespace Vatts\Console\Commands;

use Vatts\Console\Command;

class MakeController extends Command
{
    public function getName(): string { return 'make:controller'; }
    public function getDescription(): string { return 'Cria um novo Controller em app/controllers'; }

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->error('Por favor, forneça o nome do controller. Ex: php vatts make:controller UserController');
            return;
        }

        $name = $args[0];
        $projectPath = getcwd();
        $dir = $projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'controllers';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $name . '.php';

        if (file_exists($filePath)) {
            $this->error("Controller $name já existe!");
            return;
        }

        $template = "<?php\n\nnamespace App\\Controllers;\n\nclass $name\n{\n    public function index(\$request, \$response)\n    {\n        return \$response->json(['message' => '$name Controller']);\n    }\n}\n";

        file_put_contents($filePath, $template);
        $this->info("Controller $name criado com sucesso em $filePath");
    }
}

