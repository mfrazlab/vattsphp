<?php

namespace Vatts\Console\Commands;

use Vatts\Console\Command;

class MakeMiddleware extends Command
{
    public function getName(): string { return 'make:middleware'; }
    public function getDescription(): string { return 'Cria um novo Middleware em app/middlewares'; }

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->error('Por favor, forneça o nome do middleware. Ex: php vatts make:middleware AuthMiddleware');
            return;
        }

        $name = $args[0];
        $projectPath = getcwd();
        $dir = $projectPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'middlewares';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $name . '.php';

        if (file_exists($filePath)) {
            $this->error("Middleware $name já existe!");
            return;
        }

        $template = "<?php\n\nnamespace App\\Middlewares;\n\nuse Vatts\\Router\\Request;\nuse Vatts\\Router\\Response;\nuse Vatts\\Utils\\Middleware;\n\nclass $name extends Middleware\n{\n    public static string \$name = '" . strtolower($name) . "';\n\n    public function handle(Request \$request, Response \$response): Request|Response\n    {\n        // Lógica do middleware aqui\n        return \$request;\n    }\n}\n";

        file_put_contents($filePath, $template);
        $this->info("Middleware $name criado com sucesso em $filePath");
    }
}

