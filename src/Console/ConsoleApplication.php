<?php

namespace Vatts\Console;

class ConsoleApplication
{
    protected array $commands = [];

    public function registerCommand(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function run(array $argv): void
    {
        $commandName = $argv[1] ?? 'help';

        if ($commandName === 'help' || !isset($this->commands[$commandName])) {
            $this->showHelp();
            return;
        }

        $args = array_slice($argv, 2);
        $this->commands[$commandName]->execute($args);
    }

    protected function showHelp(): void
    {
        echo "Vatts CLI - Framework Console\n";
        echo "Usage: php vatts [command] [options]\n\n";
        echo "Available Commands:\n";

        foreach ($this->commands as $name => $cmd) {
            printf("  %-20s %s\n", $name, $cmd->getDescription());
        }

        echo "\nRun 'php vatts help' for more information.\n";
    }
}

