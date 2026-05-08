<?php

namespace Vatts\Console\Commands;

use Vatts\Console\Command;

class MakeModel extends Command
{
    public function getName(): string { return 'make:model'; }
    public function getDescription(): string { return 'Cria um novo Model em src/models'; }

    public function handle(array $args): void
    {
        if (empty($args)) {
            $this->error('Por favor, forneça o nome do model. Ex: php vatts make:model User');
            return;
        }

        $name = $args[0];
        $projectPath = getcwd();
        $dir = $projectPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'models';
        
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $name . '.php';

        if (file_exists($filePath)) {
            $this->error("Model $name já existe!");
            return;
        }

        $template = "<?php\n\nnamespace App\\Models;\n\nuse Vatts\\Database\\Model;\n\nclass $name extends Model\n{\n    protected static array \$schema = [\n        // 'column' => 'type'\n    ];\n}\n";

        file_put_contents($filePath, $template);
        $this->info("Model $name criado com sucesso em $filePath");
    }
}

